<?php
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Formhammer_Gravity_Forms_Integration
{
    public function register(): void
    {
        if (!class_exists('GFForms')) {
            return;
        }

        add_filter('gform_validation', [$this, 'validate'], 10, 2);
        add_filter('gform_get_form_filter', [$this, 'inject_fields'], 10, 2);
        add_filter('gform_form_tag', [$this, 'add_form_attribute'], 10, 2);
    }

    public function validate(array $validation_result, string $context): array
    {
        if ($this->is_opted_out($validation_result)) {
            return $validation_result;
        }

        $validation = formhammer_validate(Formhammer_Validator::sanitize_post_data($_POST), $this->form_id($validation_result));

        if (!in_array($validation->verdict(), ['BLOCK', 'REJECT'], true)) {
            return $validation_result;
        }

        $validation_result['is_valid'] = false;

        foreach ($validation_result['form']['fields'] ?? [] as $field) {
            if (!is_object($field)) {
                continue;
            }

            $field->failed_validation = true;
            $field->validation_message = __('Submission blocked.', 'formhammer');
        }

        return $validation_result;
    }

    public function inject_fields(string $form_string, array|object $form): string
    {
        if ($this->is_opted_out($form)) {
            return $form_string;
        }

        $form_id = $this->form_id($form);

        ob_start();
        formhammer_fields($form_id);
        $fields = ob_get_clean() ?: '';

        if (str_contains($form_string, '</form>')) {
            return str_replace('</form>', $fields . '</form>', $form_string);
        }

        return $form_string . $fields;
    }

    public function add_form_attribute(string $form_tag, array|object $form): string
    {
        if ($form_tag === '' || $this->is_opted_out($form) || str_contains($form_tag, 'data-formhammer=')) {
            return $form_tag;
        }

        $attribute = htmlspecialchars($this->form_id($form), ENT_QUOTES, 'UTF-8');

        return preg_replace('/<form\b([^>]*)>/i', '<form$1 data-formhammer="' . $attribute . '">', $form_tag, 1) ?? $form_tag;
    }

    private function form_id(array|object $value): string
    {
        $form_id = 0;

        if (is_array($value) && isset($value['form']['id'])) {
            $form_id = (int) $value['form']['id'];
        } elseif (is_array($value) && isset($value['id'])) {
            $form_id = (int) $value['id'];
        } elseif (is_object($value) && isset($value->id)) {
            $form_id = (int) $value->id;
        }

        return 'gf-' . (string) $form_id;
    }

    private function is_opted_out(array|object $value): bool
    {
        $opt_out = '';

        if (is_array($value)) {
            $opt_out = $value['form']['meta']['formhammer']
                ?? $value['meta']['formhammer']
                ?? $value['form']['formhammer']
                ?? $value['formhammer']
                ?? '';
        } elseif (isset($value->meta) && is_array($value->meta) && isset($value->meta['formhammer'])) {
            $opt_out = $value->meta['formhammer'];
        } elseif (isset($value->formhammer)) {
            $opt_out = $value->formhammer;
        }

        return is_string($opt_out) && strtolower(trim($opt_out)) === 'off';
    }
}
