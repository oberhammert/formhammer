<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private const SECRET = 'test-secret-with-enough-entropy';
    private const FORM_ID = 'contact-form-1';

    protected function setUp(): void
    {
        $GLOBALS['formhammer_test_options'] = [];
        unset($_SERVER['HTTP_X_FORMHAMMER_BYPASS']);
    }

    public function testGeneratedTokenVerifiesSuccessfully(): void
    {
        $validator = $this->validator(now: 1_700_000_000);

        $token = $validator->generate_token(self::FORM_ID);
        $result = $validator->verify_token($token, self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('valid', $result->code());
        self::assertSame([], $result->payload_errors());
    }

    public function testMissingTokenFails(): void
    {
        $result = $this->validator()->verify_token('', self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('missing_token', $result->code());
    }

    public function testMalformedTokenFails(): void
    {
        $result = $this->validator()->verify_token('not-a-token', self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('malformed_token', $result->code());
    }

    public function testTamperedPayloadFails(): void
    {
        $validator = $this->validator(now: 1_700_000_000);
        $token = $validator->generate_token(self::FORM_ID);
        [$payload, $signature] = explode('.', $token, 2);

        $tamperedPayload = $this->base64url_encode(json_encode([
            'ts' => 1_700_000_000,
            'form_id' => 'other-form',
            'nonce' => '1234567890abcdef',
        ], JSON_THROW_ON_ERROR));

        $result = $validator->verify_token($tamperedPayload . '.' . $signature, self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('invalid_signature', $result->code());
        self::assertNotSame($payload, $tamperedPayload);
    }

    public function testTamperedSignatureFails(): void
    {
        $validator = $this->validator(now: 1_700_000_000);
        $token = $validator->generate_token(self::FORM_ID);
        [$payload] = explode('.', $token, 2);

        $result = $validator->verify_token($payload . '.bad-signature', self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('invalid_signature', $result->code());
    }

    public function testSignedInvalidBase64PayloadFails(): void
    {
        $payload = '*';
        $token = $payload . '.' . hash_hmac('sha256', $payload, self::SECRET);

        $result = $this->validator()->verify_token($token, self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('invalid_payload', $result->code());
    }

    public function testSignedInvalidJsonPayloadFails(): void
    {
        $payload = $this->base64url_encode('not json');
        $token = $payload . '.' . hash_hmac('sha256', $payload, self::SECRET);

        $result = $this->validator()->verify_token($token, self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('invalid_payload', $result->code());
    }

    public function testPayloadWithMissingFormIdFails(): void
    {
        $payload = $this->base64url_encode(json_encode([
            'ts' => 1_700_000_000,
            'nonce' => '1234567890abcdef',
        ], JSON_THROW_ON_ERROR));
        $token = $payload . '.' . hash_hmac('sha256', $payload, self::SECRET);

        $result = $this->validator()->verify_token($token, self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('invalid_payload', $result->code());
        self::assertSame(['missing_or_invalid_form_id'], $result->payload_errors());
    }

    public function testWrongFormIdFails(): void
    {
        $validator = $this->validator(now: 1_700_000_000);
        $token = $validator->generate_token(self::FORM_ID);

        $result = $validator->verify_token($token, 'other-form');

        self::assertFalse($result->is_valid());
        self::assertSame('form_id_mismatch', $result->code());
    }

    public function testExpiredTokenFails(): void
    {
        $validator = $this->validator(now: 1_700_000_000, maxAge: 60);
        $token = $validator->generate_token(self::FORM_ID);

        $expiredValidator = $this->validator(now: 1_700_000_061, maxAge: 60);
        $result = $expiredValidator->verify_token($token, self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('expired_token', $result->code());
    }

    public function testTokenAtMaxAgeBoundaryIsValid(): void
    {
        $validator = $this->validator(now: 1_700_000_000, maxAge: 60);
        $token = $validator->generate_token(self::FORM_ID);

        $boundaryValidator = $this->validator(now: 1_700_000_060, maxAge: 60);
        $result = $boundaryValidator->verify_token($token, self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('valid', $result->code());
    }

    public function testValidatePassesWithValidTokenEmptyHoneypotAndHumanTiming(): void
    {
        $validator = $this->validator(now: 1_700_000_000);

        $result = $validator->validate([
            'hl_token' => $validator->generate_token(self::FORM_ID),
            'hl_website' => '',
            'hl_elapsed' => '3000',
        ], self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('PASS', $result->verdict());
        self::assertSame(0, $result->score());
        self::assertSame('pass', $result->code());
    }

    public function testValidateRejectsFilledHoneypotImmediately(): void
    {
        $validator = $this->validator(now: 1_700_000_000);

        $result = $validator->validate([
            'hl_token' => $validator->generate_token(self::FORM_ID),
            'hl_website' => 'https://spam.example',
            'hl_elapsed' => '3000',
        ], self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('REJECT', $result->verdict());
        self::assertSame(0, $result->score());
        self::assertSame('honeypot_filled', $result->code());
    }

    public function testValidateRejectsMissingTokenImmediately(): void
    {
        $result = $this->validator()->validate([
            'hl_website' => '',
            'hl_elapsed' => '3000',
        ], self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('REJECT', $result->verdict());
        self::assertSame(0, $result->score());
        self::assertSame('missing_token', $result->code());
    }

    public function testValidateBlocksWhenTimingScoreMeetsBlockThreshold(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_min_time'] = 3000;
        $GLOBALS['formhammer_test_options']['formhammer_block_threshold'] = 40;

        $validator = $this->validator(now: 1_700_000_000);

        $result = $validator->validate([
            'hl_token' => $validator->generate_token(self::FORM_ID),
            'hl_website' => '',
            'hl_elapsed' => '1',
        ], self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('BLOCK', $result->verdict());
        self::assertSame(40, $result->score());
        self::assertSame('score_threshold_block', $result->code());
    }

    public function testValidateFlagsWhenTimingScoreMeetsFlagThresholdOnly(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_min_time'] = 3000;
        $GLOBALS['formhammer_test_options']['formhammer_block_threshold'] = 60;
        $GLOBALS['formhammer_test_options']['formhammer_flag_threshold'] = 30;

        $validator = $this->validator(now: 1_700_000_000);

        $result = $validator->validate([
            'hl_token' => $validator->generate_token(self::FORM_ID),
            'hl_website' => '',
            'hl_elapsed' => '1',
        ], self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('FLAG', $result->verdict());
        self::assertSame(40, $result->score());
        self::assertSame('score_threshold_flag', $result->code());
    }

    public function testValidateAddsScoreWhenTimingFieldIsMissing(): void
    {
        $validator = $this->validator(now: 1_700_000_000);

        $result = $validator->validate([
            'hl_token' => $validator->generate_token(self::FORM_ID),
            'hl_website' => '',
        ], self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('PASS', $result->verdict());
        self::assertSame(20, $result->score());
        self::assertSame('pass', $result->code());
    }

    public function testValidateIgnoresTimingAboveMaxTime(): void
    {
        $validator = $this->validator(now: 1_700_000_000);

        $result = $validator->validate([
            'hl_token' => $validator->generate_token(self::FORM_ID),
            'hl_website' => '',
            'hl_elapsed' => '3600001',
        ], self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('PASS', $result->verdict());
        self::assertSame(0, $result->score());
    }

    public function testValidateUsesMaxAgeFromWpOptions(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_max_age'] = 60;
        $validator = $this->validator(now: 1_700_000_000);
        $token = $validator->generate_token(self::FORM_ID);

        $expiredValidator = $this->validator(now: 1_700_000_061);
        $result = $expiredValidator->validate([
            'hl_token' => $token,
            'hl_website' => '',
            'hl_elapsed' => '3000',
        ], self::FORM_ID);

        self::assertFalse($result->is_valid());
        self::assertSame('REJECT', $result->verdict());
        self::assertSame('expired_token', $result->code());
    }

    public function testValidateBypassHeaderReturnsPassWithoutCheckingPayload(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_bypass_token'] = 'test-bypass';
        $_SERVER['HTTP_X_FORMHAMMER_BYPASS'] = 'test-bypass';

        $result = $this->validator()->validate([
            'hl_website' => 'filled',
            'hl_elapsed' => '1',
        ], self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('PASS', $result->verdict());
        self::assertSame(0, $result->score());
        self::assertSame('bypass', $result->code());
    }

    public function testValidatePassesImmediatelyWhenGloballyDisabled(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_enabled'] = false;

        $result = $this->validator()->validate([
            'hl_website' => 'filled',
            'hl_elapsed' => '1',
        ], self::FORM_ID);

        self::assertTrue($result->is_valid());
        self::assertSame('PASS', $result->verdict());
        self::assertSame(0, $result->score());
        self::assertSame('disabled', $result->code());
    }

    private function validator(int $now = 1_700_000_000, int $maxAge = 3600): Formhammer_Validator
    {
        return new Formhammer_Validator(
            secret_key: self::SECRET,
            max_age: $maxAge,
            clock: static fn (): int => $now,
            random_bytes: static fn (int $length): string => str_repeat('a', $length)
        );
    }

    private function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
