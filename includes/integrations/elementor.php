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

final class Formhammer_Elementor_Integration
{
    public function register(): void
    {
        if (!class_exists('ElementorPro\Plugin')) {
            return;
        }

        add_action('elementor_pro/forms/validation', [$this, 'validate'], 10, 2);
        add_action('elementor_pro/forms/render_field/after', [$this, 'inject_fields'], 10, 4);
        add_filter('elementor/widget/render_content', [$this, 'add_form_attribute'], 10, 2);
    }

    public function validate(object $record, object $ajax_handler): void
    {
        if ($this->is_opted_out($record)) {
            return;
        }

        $validation = formhammer_validate(Formhammer_Validator::sanitize_post_data($_POST), $this->validation_form_id($record));

        if (in_array($validation->verdict(), ['BLOCK', 'REJECT'], true)) {
            $ajax_handler->add_error_message(__('Submission blocked.', 'formhammer'));
        }
    }

    public function inject_fields(string $field_type, array $item, int $item_index, object $form): void
    {
        if ($item_index !== 0 || $this->is_opted_out($form)) {
            return;
        }

        formhammer_fields($this->render_form_id($form));
    }

    public function add_form_attribute(string $content, object $widget): string
    {
        if (
            !method_exists($widget, 'get_name')
            || $widget->get_name() !== 'form'
            || $this->is_opted_out($widget)
            || str_contains($content, 'data-formhammer=')
        ) {
            return $content;
        }

        $attribute = htmlspecialchars($this->render_form_id($widget), ENT_QUOTES, 'UTF-8');

        return preg_replace('/<form\b([^>]*)>/i', '<form$1 data-formhammer="' . $attribute . '">', $content, 1) ?? $content;
    }

    private function validation_form_id(object $record): string
    {
        if (method_exists($record, 'get_form_settings')) {
            return 'elementor-' . (string) $record->get_form_settings('id');
        }

        return 'elementor-0';
    }

    private function render_form_id(object $form): string
    {
        if (method_exists($form, 'get_settings')) {
            return 'elementor-' . (string) $form->get_settings('id');
        }

        return 'elementor-0';
    }

    private function is_opted_out(object $source): bool
    {
        $value = '';

        if (method_exists($source, 'get_form_settings')) {
            $value = (string) $source->get_form_settings('formhammer');
        } elseif (method_exists($source, 'get_settings')) {
            $value = (string) $source->get_settings('formhammer');
        }

        return strtolower(trim($value)) === 'off';
    }
}
