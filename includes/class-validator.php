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

final class Formhammer_Validator
{
    private const SIGNATURE_ALGORITHM = 'sha256';
    private const NONCE_BYTES = 8;
    public const DEFAULT_MIN_TIME = 3000;
    public const DEFAULT_MAX_TIME = 3600000;
    public const DEFAULT_MAX_AGE = 3600;
    public const DEFAULT_BLOCK_THRESHOLD = 60;
    public const DEFAULT_FLAG_THRESHOLD = 30;
    public const TIMING_TOO_FAST_SCORE = 40;
    public const TIMING_MISSING_SCORE = 20;

    private string $secret_key;
    private int $max_age;
    private \Closure $clock;
    private \Closure $random_bytes;
    private Formhammer_Logger|null $logger;

    public function __construct(
        string $secret_key,
        int $max_age = self::DEFAULT_MAX_AGE,
        ?callable $clock = null,
        ?callable $random_bytes = null,
        ?Formhammer_Logger $logger = null
    ) {
        if ($secret_key === '') {
            throw new \InvalidArgumentException('Secret key must not be empty.');
        }

        if ($max_age < 0) {
            throw new \InvalidArgumentException('Max age must not be negative.');
        }

        $this->secret_key = $secret_key;
        $this->max_age = $max_age;
        $this->clock = \Closure::fromCallable($clock ?? 'time');
        $this->random_bytes = \Closure::fromCallable($random_bytes ?? 'random_bytes');
        $this->logger = $logger;
    }

    public function generate_token(string $form_id): string
    {
        $payload = [
            'ts' => ($this->clock)(),
            'form_id' => $form_id,
            'nonce' => bin2hex(($this->random_bytes)(self::NONCE_BYTES)),
        ];

        $encoded_payload = $this->base64url_encode(json_encode($payload, JSON_THROW_ON_ERROR));

        return $encoded_payload . '.' . $this->sign($encoded_payload);
    }

    public static function sanitize_post_data(array $post_data): array
    {
        $sanitized = [];

        foreach ($post_data as $key => $value) {
            $sanitized_key = self::sanitize_text((string) $key);

            if ($sanitized_key === 'hl_elapsed') {
                $sanitized[$sanitized_key] = $value === null || $value === '' ? $value : self::sanitize_absint($value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$sanitized_key] = self::sanitize_post_data($value);
                continue;
            }

            $sanitized[$sanitized_key] = is_scalar($value) ? self::sanitize_text((string) $value) : '';
        }

        return $sanitized;
    }

    public function verify_token(?string $token, string $expected_form_id): Formhammer_Validation_Result
    {
        if ($token === null || trim($token) === '') {
            return Formhammer_Validation_Result::failure('missing_token');
        }

        if (substr_count($token, '.') !== 1) {
            return Formhammer_Validation_Result::failure('malformed_token');
        }

        [$encoded_payload, $signature] = explode('.', $token, 2);

        if ($encoded_payload === '' || $signature === '') {
            return Formhammer_Validation_Result::failure('malformed_token');
        }

        if (!hash_equals($this->sign($encoded_payload), $signature)) {
            return Formhammer_Validation_Result::failure('invalid_signature');
        }

        $payload = $this->decode_payload($encoded_payload);
        if ($payload === null) {
            return Formhammer_Validation_Result::failure('invalid_payload');
        }

        $payload_errors = $this->validate_payload_shape($payload);
        if ($payload_errors !== []) {
            return Formhammer_Validation_Result::failure('invalid_payload', $payload_errors);
        }

        if (!hash_equals($payload['form_id'], $expected_form_id)) {
            return Formhammer_Validation_Result::failure('form_id_mismatch');
        }

        if (($this->clock)() - $payload['ts'] > $this->option_int('formhammer_max_age', $this->max_age)) {
            return Formhammer_Validation_Result::failure('expired_token');
        }

        return Formhammer_Validation_Result::success($payload);
    }

    public function validate(array $post_data, string $form_id): Formhammer_Validation_Result
    {
        if (!$this->option_bool('formhammer_enabled', true)) {
            return Formhammer_Validation_Result::pass('disabled');
        }

        if ($this->is_bypassed()) {
            return Formhammer_Validation_Result::pass('bypass');
        }

        $honeypot = self::sanitize_text((string) ($post_data['hl_website'] ?? ''));

        if (trim($honeypot) !== '') {
            return $this->finalize($form_id, Formhammer_Validation_Result::reject('honeypot_filled'));
        }

        $token = isset($post_data['hl_token']) ? self::sanitize_text((string) $post_data['hl_token']) : null;
        $token_result = $this->verify_token(
            $token,
            $form_id
        );

        if (!$token_result->is_valid()) {
            return $this->finalize(
                $form_id,
                Formhammer_Validation_Result::reject($token_result->code(), $token_result->payload_errors())
            );
        }

        $elapsed = $post_data['hl_elapsed'] ?? null;
        $elapsed = $elapsed === null || $elapsed === '' ? $elapsed : self::sanitize_absint($elapsed);
        $score = $this->timing_score($elapsed);
        $block_threshold = $this->option_int('formhammer_block_threshold', self::DEFAULT_BLOCK_THRESHOLD);
        $flag_threshold = $this->option_int('formhammer_flag_threshold', self::DEFAULT_FLAG_THRESHOLD);

        if ($score >= $block_threshold) {
            return $this->finalize($form_id, Formhammer_Validation_Result::block('score_threshold_block', $score));
        }

        if ($score >= $flag_threshold) {
            return $this->finalize($form_id, Formhammer_Validation_Result::flag('score_threshold_flag', $score));
        }

        return $this->finalize($form_id, Formhammer_Validation_Result::pass('pass', $score));
    }

    private function timing_score(mixed $elapsed): int
    {
        if ($elapsed === null || $elapsed === '') {
            return self::TIMING_MISSING_SCORE;
        }

        if (!is_numeric($elapsed)) {
            return self::TIMING_MISSING_SCORE;
        }

        $elapsed = (int) $elapsed;
        if ($elapsed > self::DEFAULT_MAX_TIME) {
            return 0;
        }

        if ($elapsed < $this->option_int('formhammer_min_time', self::DEFAULT_MIN_TIME)) {
            return self::TIMING_TOO_FAST_SCORE;
        }

        return 0;
    }

    private function is_bypassed(): bool
    {
        $bypass_token = $this->option_string('formhammer_bypass_token', '');
        $header_token = $_SERVER['HTTP_X_FORMHAMMER_BYPASS'] ?? '';

        return $bypass_token !== ''
            && is_string($header_token)
            && hash_equals($bypass_token, $header_token);
    }

    private function finalize(string $form_id, Formhammer_Validation_Result $result): Formhammer_Validation_Result
    {
        $this->logger()?->log($form_id, $result);

        return $result;
    }

    private function option_int(string $option, int $default): int
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        $value = get_option($option, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function option_string(string $option, string $default): string
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        $value = get_option($option, $default);

        return is_string($value) ? $value : $default;
    }

    private function option_bool(string $option, bool $default): bool
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        return (bool) get_option($option, $default);
    }

    private function logger(): ?Formhammer_Logger
    {
        if ($this->logger !== null) {
            return $this->logger;
        }

        if (!class_exists('Formhammer_Logger')) {
            return null;
        }

        $this->logger = new Formhammer_Logger(clock: $this->clock);

        return $this->logger;
    }

    private function sign(string $encoded_payload): string
    {
        return hash_hmac(self::SIGNATURE_ALGORITHM, $encoded_payload, $this->secret_key);
    }

    private function decode_payload(string $encoded_payload): ?array
    {
        $json = $this->base64url_decode($encoded_payload);
        if ($json === null) {
            return null;
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function validate_payload_shape(array $payload): array
    {
        $errors = [];

        if (!isset($payload['ts']) || !is_int($payload['ts'])) {
            $errors[] = 'missing_or_invalid_ts';
        }

        if (!isset($payload['form_id']) || !is_string($payload['form_id']) || $payload['form_id'] === '') {
            $errors[] = 'missing_or_invalid_form_id';
        }

        if (!isset($payload['nonce']) || !is_string($payload['nonce']) || $payload['nonce'] === '') {
            $errors[] = 'missing_or_invalid_nonce';
        }

        return $errors;
    }

    private function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64url_decode(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value) === 1) {
            return null;
        }

        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        // base64_decode() is safe here: input has passed HMAC verification above.
        // Strict mode enabled. This is intentional - not a security risk.
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private static function sanitize_text(string $value): string
    {
        if (function_exists('wp_unslash')) {
            $value = (string) wp_unslash($value);
        }

        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($value);
        }

        return trim(strip_tags($value));
    }

    private static function sanitize_absint(mixed $value): int
    {
        if (function_exists('wp_unslash')) {
            $value = wp_unslash($value);
        }

        if (function_exists('absint')) {
            return absint($value);
        }

        return max(0, (int) $value);
    }
}

final class Formhammer_Validation_Result
{
    private function __construct(
        private bool $valid,
        private string $code,
        private array $payload = [],
        private array $payload_errors = [],
        private string $verdict = 'PASS',
        private int $score = 0
    ) {
    }

    public static function success(array $payload): self
    {
        return new self(true, 'valid', $payload, [], 'PASS', 0);
    }

    public static function failure(string $code, array $payload_errors = []): self
    {
        return new self(false, $code, [], $payload_errors, 'REJECT', 0);
    }

    public static function pass(string $code = 'pass', int $score = 0): self
    {
        return new self(true, $code, [], [], 'PASS', $score);
    }

    public static function flag(string $code, int $score): self
    {
        return new self(true, $code, [], [], 'FLAG', $score);
    }

    public static function block(string $code, int $score): self
    {
        return new self(false, $code, [], [], 'BLOCK', $score);
    }

    public static function reject(string $code, array $payload_errors = []): self
    {
        return new self(false, $code, [], $payload_errors, 'REJECT', 0);
    }

    public function is_valid(): bool
    {
        return $this->valid;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function payload_errors(): array
    {
        return $this->payload_errors;
    }

    public function verdict(): string
    {
        return $this->verdict;
    }

    public function score(): int
    {
        return $this->score;
    }
}
