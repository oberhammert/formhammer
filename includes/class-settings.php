<?php

declare(strict_types=1);

final class Formhammer_Settings
{
    private const OPTION_GROUP = 'formhammer_settings';
    private const PAGE_SLUG = 'formhammer';
    private const SECTION_MAIN = 'formhammer_main';

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
    }

    public function register_settings(): void
    {
        add_settings_section(
            self::SECTION_MAIN,
            __('Formhammer Settings', 'formhammer'),
            '__return_null',
            self::PAGE_SLUG
        );

        foreach (self::OPTIONS as $option => $config) {
            register_setting(
                self::OPTION_GROUP,
                $option,
                [
                    'type' => $config['type'],
                    'default' => $config['default'],
                    'sanitize_callback' => $this->sanitize_callback($config['type']),
                ]
            );

            add_settings_field(
                $option,
                esc_html__($config['label'], 'formhammer'),
                [$this, 'render_field'],
                self::PAGE_SLUG,
                self::SECTION_MAIN,
                [
                    'option' => $option,
                    'type' => $config['type'],
                    'default' => $config['default'],
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
        print '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::PAGE_SLUG);
        submit_button();
        print '</form>';
        print '</div>';
    }

    public function render_field(array $args): void
    {
        $option = (string) $args['option'];
        $type = (string) $args['type'];
        $default = $args['default'];
        $value = get_option($option, $default);

        if ($type === 'boolean') {
            printf(
                '<input type="hidden" name="%s" value="0"><label><input type="checkbox" name="%s" value="1" %s> %s</label>',
                esc_attr($option),
                esc_attr($option),
                checked((bool) $value, true, false),
                esc_html__('Enabled', 'formhammer')
            );

            return;
        }

        $input_type = $type === 'integer' ? 'number' : 'text';

        printf(
            '<input type="%s" name="%s" value="%s" class="regular-text">',
            esc_attr($input_type),
            esc_attr($option),
            esc_attr((string) $value)
        );
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

    private function sanitize_callback(string $type): callable
    {
        return match ($type) {
            'integer' => [$this, 'sanitize_int'],
            'boolean' => [$this, 'sanitize_bool'],
            default => [$this, 'sanitize_string'],
        };
    }
}
