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

final class Formhammer_CF7_Integration
{
    private object|null $contact_form = null;

    public function register(): void
    {
        if (!class_exists('WPCF7')) {
            return;
        }

        add_action('wpcf7_contact_form', [$this, 'capture_contact_form'], 10, 1);
        add_filter('wpcf7_validate', [$this, 'validate'], 20, 2);
        add_filter('wpcf7_form_elements', [$this, 'inject_fields'], 20, 1);
        add_filter('wpcf7_form_additional_atts', [$this, 'add_form_attribute'], 20, 1);
    }

    public function capture_contact_form(object $contact_form): void
    {
        $this->contact_form = $contact_form;
    }

    public function validate(object $result, array $tags): object
    {
        if ($this->is_opted_out()) {
            return $result;
        }

        $validation = formhammer_validate(Formhammer_Validator::sanitize_post_data($_POST), $this->form_id());

        if (in_array($validation->verdict(), ['BLOCK', 'REJECT'], true)) {
            $result->invalidate($tags[0] ?? '', __('Submission blocked.', 'formhammer'));
        }

        return $result;
    }

    public function inject_fields(string $html): string
    {
        if ($this->is_opted_out()) {
            return $html;
        }

        ob_start();
        formhammer_fields($this->form_id());
        $fields = ob_get_clean();

        return $html . $fields;
    }

    public function add_form_attribute(array $atts): array
    {
        if ($this->is_opted_out()) {
            return $atts;
        }

        $atts['data-formhammer'] = $this->form_id();

        return $atts;
    }

    private function form_id(): string
    {
        $posted_form_id = isset($_POST['_wpcf7']) ? absint(wp_unslash($_POST['_wpcf7'])) : 0;

        if ($posted_form_id > 0) {
            return 'cf7-' . (string) $posted_form_id;
        }

        if ($this->contact_form !== null && method_exists($this->contact_form, 'id')) {
            return 'cf7-' . (string) $this->contact_form->id();
        }

        return 'cf7-0';
    }

    private function is_opted_out(): bool
    {
        if ($this->contact_form === null) {
            return false;
        }

        $value = '';

        if (method_exists($this->contact_form, 'shortcode_attr')) {
            $value = (string) $this->contact_form->shortcode_attr('formhammer');
        } elseif (
            isset($this->contact_form->shortcode_atts)
            && is_array($this->contact_form->shortcode_atts)
            && isset($this->contact_form->shortcode_atts['formhammer'])
        ) {
            $value = (string) $this->contact_form->shortcode_atts['formhammer'];
        }

        return strtolower(trim($value)) === 'off';
    }
}
