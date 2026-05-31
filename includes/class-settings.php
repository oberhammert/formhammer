<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Formhammer_Settings
{
    private const OPTION_GROUP = 'formhammer_settings';
    private const PAGE_SLUG = 'formhammer';
    private const BYPASS_FLASH_TRANSIENT = 'formhammer_bypass_token_flash';

    private const OPTIONS = [
        'formhammer_enabled' => [
            'label' => 'Enable Formhammer protection',
            'type' => 'boolean',
            'default' => true,
        ],
        'formhammer_min_time' => [
            'label' => 'Minimum time before submit (ms)',
            'type' => 'integer',
            'default' => Formhammer_Validator::DEFAULT_MIN_TIME,
        ],
        'formhammer_max_age' => [
            'label' => 'Token max age (seconds)',
            'type' => 'integer',
            'default' => Formhammer_Validator::DEFAULT_MAX_AGE,
        ],
        'formhammer_block_threshold' => [
            'label' => 'Block threshold',
            'type' => 'integer',
            'default' => Formhammer_Validator::DEFAULT_BLOCK_THRESHOLD,
        ],
        'formhammer_flag_threshold' => [
            'label' => 'Flag threshold',
            'type' => 'integer',
            'default' => Formhammer_Validator::DEFAULT_FLAG_THRESHOLD,
        ],
        'formhammer_log_enabled' => [
            'label' => 'Enable logging',
            'type' => 'boolean',
            'default' => false,
        ],
        'formhammer_log_retention' => [
            'label' => 'Log retention (days)',
            'type' => 'integer',
            'default' => 7,
        ],
        'formhammer_bypass_token' => [
            'label' => 'Bypass token',
            'type' => 'string',
            'default' => '',
        ],
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_formhammer/formhammer.php', [$this, 'plugin_action_links']);
    }

    public function register_settings(): void
    {
        foreach (self::OPTIONS as $option => $config) {
            $sanitize_callback = $option === 'formhammer_bypass_token'
                ? [$this, 'sanitize_bypass_token']
                : $this->sanitize_callback($config['type']);

            register_setting(
                self::OPTION_GROUP,
                $option,
                [
                    'type' => $config['type'],
                    'default' => $config['default'],
                    'sanitize_callback' => $sanitize_callback,
                ]
            );
        }
    }

    public function add_settings_page(): void
    {
        add_options_page(
            __('Formhammer', 'formhammer'),
            __('Formhammer', 'formhammer'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function plugin_action_links(array $links): array
    {
        $settings_url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url($settings_url),
                esc_html__('Settings', 'formhammer')
            )
        );

        return $links;
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'formhammer'));
        }

        print '<div class="wrap">';
        print '<h1>' . esc_html__('Formhammer', 'formhammer') . '</h1>';
        if (!(bool) get_option('formhammer_log_enabled', false)) {
            print '<div class="notice notice-warning inline"><p>';
            print esc_html__('Logging is disabled. Enable it temporarily to debug blocking behavior.', 'formhammer');
            print '</p></div>';
        }
        $this->render_bypass_token_flash();
        print '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GROUP);
        $this->render_section(
            __('Protection', 'formhammer'),
            [
                $this->render_checkbox_row(
                    'formhammer_enabled',
                    __('Enable Formhammer protection', 'formhammer'),
                    __('Disable all validation hooks without changing the form setup.', 'formhammer')
                ),
                $this->render_number_row(
                    'formhammer_min_time',
                    __('Minimum time before submit (ms)', 'formhammer'),
                    __('Submissions faster than this are scored as suspicious.', 'formhammer')
                ),
                $this->render_number_row(
                    'formhammer_max_age',
                    __('Token max age (seconds)', 'formhammer'),
                    __('Freshness window for the HMAC token fetched by JavaScript.', 'formhammer')
                ),
            ]
        );
        $this->render_section(
            __('Thresholds', 'formhammer'),
            [
                $this->render_number_row(
                    'formhammer_block_threshold',
                    __('Block threshold', 'formhammer'),
                    __('Scores at or above this value are blocked.', 'formhammer')
                ),
                $this->render_number_row(
                    'formhammer_flag_threshold',
                    __('Flag threshold', 'formhammer'),
                    __('Scores at or above this value are flagged.', 'formhammer')
                ),
            ]
        );
        $this->render_section(
            __('Logging', 'formhammer'),
            [
                $this->render_checkbox_row(
                    'formhammer_log_enabled',
                    __('Enable logging', 'formhammer'),
                    __('Write verdicts to the log table for debugging.', 'formhammer')
                ),
                $this->render_number_row(
                    'formhammer_log_retention',
                    __('Log retention (days)', 'formhammer'),
                    __('Old log rows are removed automatically by WP-Cron.', 'formhammer')
                ),
            ]
        );
        $this->render_section(
            __('Security', 'formhammer'),
            [
                $this->render_bypass_token_row(),
            ]
        );
        submit_button();
        print '</form>';
        print '</div>';
    }

    private function render_section(string $heading, array $rows): void
    {
        print '<h2>' . esc_html($heading) . '</h2>';
        print '<table class="form-table" role="presentation">';

        foreach ($rows as $row) {
            print $row;
        }

        print '</table>';
    }

    private function render_checkbox_row(string $option, string $label, string $description): string
    {
        $value = (bool) get_option($option, $this->option_default($option));

        return sprintf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><label><input id="%1$s" type="checkbox" name="%1$s" value="1" %3$s> %4$s</label><p class="description">%5$s</p></td></tr>',
            esc_attr($option),
            esc_html($label),
            checked($value, true, false),
            esc_html__('Enabled', 'formhammer'),
            esc_html($description)
        );
    }

    private function render_number_row(string $option, string $label, string $description): string
    {
        $value = get_option($option, $this->option_default($option));

        return sprintf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input id="%1$s" type="number" name="%1$s" value="%3$s" class="small-text"><p class="description">%4$s</p></td></tr>',
            esc_attr($option),
            esc_html($label),
            esc_attr((string) $value),
            esc_html($description)
        );
    }

    private function render_bypass_token_row(): string
    {
        $option = 'formhammer_bypass_token';

        return sprintf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input id="%1$s" type="password" name="%1$s" value="" class="regular-text" autocomplete="new-password"><button type="button" class="button" onclick="(function(input){if(window.crypto&&window.crypto.getRandomValues){var bytes=new Uint8Array(24);window.crypto.getRandomValues(bytes);input.value=Array.from(bytes,function(byte){return byte.toString(16).padStart(2,\'0\');}).join(\'\');}else{input.value=Math.random().toString(36).slice(2)+Math.random().toString(36).slice(2);}})(document.getElementById(\'%1$s\')); return false;">%3$s</button><p class="description">%4$s</p></td></tr>',
            esc_attr($option),
            esc_html__('Bypass token', 'formhammer'),
            esc_html__('Regenerate', 'formhammer'),
            esc_html__('Leave blank to keep the current token. The replacement token is shown only once after saving.', 'formhammer')
        );
    }

    private function sanitize_callback(string $type): callable
    {
        return match ($type) {
            'integer' => [$this, 'sanitize_int'],
            'boolean' => [$this, 'sanitize_bool'],
            'string' => [$this, 'sanitize_string'],
            default => [$this, 'sanitize_string'],
        };
    }

    public function sanitize_int(mixed $value): int
    {
        return max(0, (int) $value);
    }

    public function sanitize_bool(mixed $value): bool
    {
        return (bool) $value;
    }

    public function sanitize_string(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    public function sanitize_bypass_token(mixed $value): string
    {
        $current = $this->option_string('formhammer_bypass_token', '');

        if (!is_scalar($value)) {
            return $current;
        }

        $token = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $value));

        if ($token === '') {
            return $current;
        }

        if ($token !== $current && function_exists('set_transient')) {
            set_transient(self::BYPASS_FLASH_TRANSIENT, $token, 60);
        }

        return $token;
    }

    private function option_default(string $option): mixed
    {
        return self::OPTIONS[$option]['default'] ?? '';
    }

    private function option_string(string $option, string $default): string
    {
        $value = get_option($option, $default);

        return is_string($value) ? $value : $default;
    }

    private function render_bypass_token_flash(): void
    {
        if (!function_exists('get_transient')) {
            return;
        }

        $token = get_transient(self::BYPASS_FLASH_TRANSIENT);

        if (!is_string($token) || $token === '') {
            return;
        }

        print '<div class="notice notice-success"><p>' . esc_html__('This token will only be shown once. Save it now.', 'formhammer') . '</p>';
        print '<p><code id="formhammer-bypass-token-flash">' . esc_html($token) . '</code> ';
        print '<button type="button" class="button" onclick="if(window.navigator&&navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(document.getElementById(\'formhammer-bypass-token-flash\').textContent);}">' . esc_html__('Copy', 'formhammer') . '</button></p></div>';

        if (function_exists('delete_transient')) {
            delete_transient(self::BYPASS_FLASH_TRANSIENT);
        }
    }
}
