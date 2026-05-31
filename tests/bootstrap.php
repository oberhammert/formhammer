<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private array $params = [])
        {
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = '',
            private array $data = []
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        private array $headers = [];

        public function __construct(private mixed $data = null)
        {
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (!defined('WP_REST_Server::READABLE')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
    }
}

if (!function_exists('__return_true')) {
    function __return_true(): bool
    {
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        $GLOBALS['formhammer_registered_rest_routes'][] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];

        return true;
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response(mixed $data): WP_REST_Response
    {
        return $data instanceof WP_REST_Response ? $data : new WP_REST_Response($data);
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['formhammer_test_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value): bool
    {
        $GLOBALS['formhammer_test_options'][$option] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS['formhammer_test_options'][$option]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        return $GLOBALS['formhammer_test_transients'][$transient]['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['formhammer_test_transients'][$transient] = [
            'value' => $value,
            'expiration' => $expiration,
        ];

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        unset($GLOBALS['formhammer_test_transients'][$transient]);

        return true;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta(string $sql): array
    {
        $GLOBALS['formhammer_dbdelta_calls'][] = $sql;

        return [$sql];
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['formhammer_registered_filters'][] = [
            'hook' => $hook_name,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return $GLOBALS['formhammer_current_user_can'][$capability] ?? true;
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback): string
    {
        $GLOBALS['formhammer_registered_admin_pages'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug', 'callback');

        return $menu_slug;
    }
}

if (!function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, array $args = []): bool
    {
        $GLOBALS['formhammer_registered_settings'][] = compact('option_group', 'option_name', 'args');

        return true;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields(string $option_group): void
    {
        $GLOBALS['formhammer_settings_fields'][] = $option_group;
        echo '<input type="hidden" name="option_page" value="' . htmlspecialchars($option_group, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections(string $page): void
    {
        $GLOBALS['formhammer_do_settings_sections'][] = $page;
    }
}

if (!function_exists('submit_button')) {
    function submit_button(string $text = 'Save Changes'): void
    {
        echo '<p class="submit"><button type="submit" class="button button-primary">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</button></p>';
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'http://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array|string $key, mixed $value = null, string $url = ''): string
    {
        if (is_array($key)) {
            $query = http_build_query($key);
            return $url . (str_contains($url, '?') ? '&' : '?') . $query;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('checked')) {
    function checked(mixed $checked, mixed $current = true, bool $echo = true): string
    {
        $result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';

        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['formhammer_registered_actions'][] = [
            'hook' => $hook_name,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook): int|false
    {
        return $GLOBALS['formhammer_scheduled_events'][$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool
    {
        $GLOBALS['formhammer_scheduled_events'][$hook] = $timestamp;
        $GLOBALS['formhammer_scheduled_event_meta'][$hook] = [
            'timestamp' => $timestamp,
            'recurrence' => $recurrence,
        ];

        return true;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('formhammer_validate')) {
    function formhammer_validate(array $post_data, string $form_id): Formhammer_Validation_Result
    {
        $GLOBALS['formhammer_validate_calls'][] = [
            'post_data' => $post_data,
            'form_id' => $form_id,
        ];

        return $GLOBALS['formhammer_validate_result']
            ?? Formhammer_Validation_Result::pass('pass');
    }
}

if (!function_exists('formhammer_fields')) {
    function formhammer_fields(string $form_id): void
    {
        $GLOBALS['formhammer_fields_calls'][] = $form_id;
        echo $GLOBALS['formhammer_fields_markup'] ?? '';
    }
}

if (!function_exists('wpforms')) {
    function wpforms(): object
    {
        return $GLOBALS['formhammer_wpforms_instance'];
    }
}

require_once __DIR__ . '/../includes/class-validator.php';
require_once __DIR__ . '/../includes/class-injector.php';
require_once __DIR__ . '/../includes/class-rest.php';
require_once __DIR__ . '/../includes/class-logger.php';
require_once __DIR__ . '/../includes/class-settings.php';
