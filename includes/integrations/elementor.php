<?php

declare(strict_types=1);

final class Formhammer_Elementor_Integration
{
    public function register(): void
    {
        if (!class_exists('ElementorPro\Plugin')) {
            return;
        }

        add_action('elementor_pro/forms/validation', [$this, 'validate'], 10, 2);
        add_action('elementor_pro/forms/render_field/after', [$this, 'inject_fields'], 10, 4);
    }

    public function validate(object $record, object $ajax_handler): void
    {
        if ($this->is_opted_out($record)) {
            return;
        }

        $validation = formhammer_validate($_POST, $this->validation_form_id($record));

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
