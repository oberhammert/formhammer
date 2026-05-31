<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Formhammer_Injector
{
    private const HONEYPOT_WRAPPER_CLASS = 'formhammer-hp';
    private const HONEYPOT_FIELD_NAME = 'hl_website';
    private const TIMING_FIELD_NAME = 'hl_elapsed';
    private const TOKEN_FIELD_NAME = 'hl_token';
    private const FALLBACK_FORM_SUFFIX = 'form';

    private string $honeypot_label;

    public function __construct(string $honeypot_label = 'Website (leave empty)')
    {
        if ($honeypot_label === '') {
            throw new \InvalidArgumentException('Honeypot label must not be empty.');
        }

        $this->honeypot_label = $honeypot_label;
    }

    public function render_honeypot(string $form_id): string
    {
        $field_id = self::HONEYPOT_FIELD_NAME . '_' . $this->sanitize_form_id($form_id);
        $escaped_label = $this->escape_html($this->honeypot_label);
        $escaped_field_id = $this->escape_attribute($field_id);
        $escaped_field_name = $this->escape_attribute(self::HONEYPOT_FIELD_NAME);

        return sprintf(
            '<div class="%s" aria-hidden="true"><label for="%s">%s</label><input type="text" name="%s" id="%s" autocomplete="off" tabindex="-1"></div>',
            self::HONEYPOT_WRAPPER_CLASS,
            $escaped_field_id,
            $escaped_label,
            $escaped_field_name,
            $escaped_field_id
        );
    }

    public function render_timing_field(string $form_id): string
    {
        return $this->render_hidden_field(self::TIMING_FIELD_NAME, $form_id);
    }

    public function render_token_field(string $form_id): string
    {
        return $this->render_hidden_field(self::TOKEN_FIELD_NAME, $form_id);
    }

    public function render_fields(string $form_id): string
    {
        return $this->render_honeypot($form_id)
            . $this->render_timing_field($form_id)
            . $this->render_token_field($form_id);
    }

    private function render_hidden_field(string $field_name, string $form_id): string
    {
        $field_id = $field_name . '_' . $this->sanitize_form_id($form_id);
        $escaped_field_id = $this->escape_attribute($field_id);
        $escaped_field_name = $this->escape_attribute($field_name);

        return sprintf(
            '<input type="hidden" name="%s" id="%s" value="">',
            $escaped_field_name,
            $escaped_field_id
        );
    }

    private function sanitize_form_id(string $form_id): string
    {
        $sanitized = strtolower($form_id);
        $sanitized = preg_replace('/[^a-z0-9-]+/', '_', $sanitized) ?? '';
        $sanitized = trim($sanitized, '_');

        return $sanitized !== '' ? $sanitized : self::FALLBACK_FORM_SUFFIX;
    }

    private function escape_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escape_attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
