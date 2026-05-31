<?php

declare(strict_types=1);

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

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['formhammer_test_options'][$option] ?? $default;
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
