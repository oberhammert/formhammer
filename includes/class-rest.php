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

final class Formhammer_REST
{
    private const NAMESPACE = 'formhammer/v1';
    private const TOKEN_ROUTE = '/token';
    private const MAX_FORM_ID_LENGTH = 100;
    private const RATE_LIMIT_MAX_REQUESTS = 20;
    private const RATE_LIMIT_WINDOW = 60;

    public function __construct(private Formhammer_Validator $validator)
    {
    }

    public function register_routes(): void
    {
        // Public frontend forms fetch visitor tokens from this endpoint.
        // Keep it unauthenticated, but enforce same-site Origin/Referer and IP rate limits.
        register_rest_route(
            self::NAMESPACE,
            self::TOKEN_ROUTE,
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_token_request'],
                'permission_callback' => '__return_true',
                'args' => [
                    'form_id' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ]
        );
    }

    public function handle_token_request(WP_REST_Request $request): mixed
    {
        if (!$this->is_allowed_origin()) {
            return new WP_Error(
                'formhammer_invalid_origin',
                'Invalid request origin.',
                ['status' => 403]
            );
        }

        if (!$this->check_rate_limit()) {
            return new WP_Error(
                'formhammer_rate_limit_exceeded',
                'Too many token requests.',
                ['status' => 429]
            );
        }

        $form_id = $request->get_param('form_id');
        $form_id = $this->sanitize_form_id($form_id);

        if ($form_id === null) {
            return new WP_Error(
                'formhammer_invalid_form_id',
                'Invalid form_id.',
                ['status' => 400]
            );
        }

        return $this->token_response([
            'token' => $this->validator->generate_token($form_id),
        ]);
    }

    private function sanitize_form_id(mixed $form_id): ?string
    {
        if (!is_string($form_id)) {
            return null;
        }

        $form_id = trim($form_id);

        if ($form_id === '' || strlen($form_id) > self::MAX_FORM_ID_LENGTH) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $form_id) !== 1) {
            return null;
        }

        return $form_id;
    }

    private function token_response(array $data): mixed
    {
        if (!function_exists('rest_ensure_response')) {
            return $data;
        }

        $response = rest_ensure_response($data);

        if (is_object($response) && method_exists($response, 'header')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }

        return $response;
    }

    private function check_rate_limit(): bool
    {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return true;
        }

        $key = 'formhammer_rl_' . md5($this->client_ip());
        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, self::RATE_LIMIT_WINDOW);

            return true;
        }

        $count = is_numeric($count) ? (int) $count : 0;

        if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
            return false;
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);

        return true;
    }

    private function client_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        return is_string($ip) && $ip !== '' ? $ip : 'unknown';
    }

    private function is_allowed_origin(): bool
    {
        if (!function_exists('get_site_url') || !function_exists('wp_parse_url')) {
            return true;
        }

        $site_host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        if (!is_string($site_host) || $site_host === '') {
            return false;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        foreach ([$origin, $referer] as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $host = wp_parse_url($candidate, PHP_URL_HOST);

            if (is_string($host) && strcasecmp($host, $site_host) === 0) {
                return true;
            }
        }

        return false;
    }
}
