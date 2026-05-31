<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Formhammer_WPForms_Integration
{
    public function register(): void
    {
        if (!class_exists('WPForms')) {
            return;
        }

        add_action('wpforms_process', [$this, 'validate'], 10, 3);
        add_action('wpforms_display_submit_before', [$this, 'inject_fields'], 10, 1);
        add_filter('wpforms_frontend_form_atts', [$this, 'add_form_attribute'], 10, 2);
    }

    public function validate(array $fields, array $entry, array $form_data): void
    {
        if ($this->is_opted_out($form_data)) {
            return;
        }

        $validation = formhammer_validate($_POST, $this->form_id($form_data));

        if (!in_array($validation->verdict(), ['BLOCK', 'REJECT'], true)) {
            return;
        }

        $form_id = isset($form_data['id']) ? (int) $form_data['id'] : 0;
        wpforms()->process->errors[$form_id]['header'] = __('Submission blocked.', 'formhammer');
    }

    public function inject_fields(array $form_data): void
    {
        if ($this->is_opted_out($form_data)) {
            return;
        }

        formhammer_fields($this->form_id($form_data));
    }

    public function add_form_attribute(array $atts, array $form_data): array
    {
        if ($this->is_opted_out($form_data)) {
            return $atts;
        }

        if (!isset($atts['atts']) || !is_array($atts['atts'])) {
            $atts['atts'] = [];
        }

        $atts['atts']['data-formhammer'] = $this->form_id($form_data);

        return $atts;
    }

    private function form_id(array $form_data): string
    {
        return 'wpforms-' . (string) (isset($form_data['id']) ? (int) $form_data['id'] : 0);
    }

    private function is_opted_out(array $form_data): bool
    {
        $value = $form_data['meta']['formhammer']
            ?? $form_data['settings']['formhammer']
            ?? $form_data['formhammer']
            ?? '';

        return is_string($value) && strtolower(trim($value)) === 'off';
    }
}
