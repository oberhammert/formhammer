<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RestTest extends TestCase
{
    private const SECRET = 'test-secret-with-enough-entropy';

    protected function setUp(): void
    {
        $GLOBALS['formhammer_registered_rest_routes'] = [];
        $GLOBALS['formhammer_test_transients'] = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    }

    public function testRegisterRoutesRegistersTokenEndpoint(): void
    {
        $rest = new Formhammer_REST($this->validator());

        $rest->register_routes();

        self::assertCount(1, $GLOBALS['formhammer_registered_rest_routes']);
        self::assertSame('formhammer/v1', $GLOBALS['formhammer_registered_rest_routes'][0]['namespace']);
        self::assertSame('/token', $GLOBALS['formhammer_registered_rest_routes'][0]['route']);
        self::assertSame('GET', $GLOBALS['formhammer_registered_rest_routes'][0]['args']['methods']);
        self::assertSame([$rest, 'handle_token_request'], $GLOBALS['formhammer_registered_rest_routes'][0]['args']['callback']);
        self::assertSame('__return_true', $GLOBALS['formhammer_registered_rest_routes'][0]['args']['permission_callback']);
    }

    public function testHandleTokenRequestReturnsFreshSignedToken(): void
    {
        $validator = $this->validator(now: 1_700_000_000);
        $rest = new Formhammer_REST($validator);
        $request = new WP_REST_Request(['form_id' => 'contact-form-1']);

        $response = $rest->handle_token_request($request);
        $data = $this->response_data($response);

        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertTrue($validator->verify_token($data['token'], 'contact-form-1')->is_valid());
    }

    public function testHandleTokenRequestAddsNoStoreHeaders(): void
    {
        $rest = new Formhammer_REST($this->validator());
        $request = new WP_REST_Request(['form_id' => 'contact_form-1']);

        $response = $rest->handle_token_request($request);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(
            'no-store, no-cache, must-revalidate, max-age=0',
            $response->get_headers()['Cache-Control']
        );
        self::assertSame('no-cache', $response->get_headers()['Pragma']);
        self::assertSame('0', $response->get_headers()['Expires']);
    }

    public function testHandleTokenRequestGeneratesNewTokenOnEachCall(): void
    {
        $counter = 0;
        $validator = new Formhammer_Validator(
            secret_key: self::SECRET,
            max_age: 3600,
            clock: static fn (): int => 1_700_000_000,
            random_bytes: static function (int $length) use (&$counter): string {
                $counter++;

                return str_repeat(chr(96 + $counter), $length);
            }
        );
        $rest = new Formhammer_REST($validator);
        $request = new WP_REST_Request(['form_id' => 'contact-form-1']);

        $first = $rest->handle_token_request($request);
        $second = $rest->handle_token_request($request);
        $firstData = $this->response_data($first);
        $secondData = $this->response_data($second);

        self::assertIsArray($firstData);
        self::assertIsArray($secondData);
        self::assertNotSame($firstData['token'], $secondData['token']);
    }

    public function testHandleTokenRequestRejectsMissingFormId(): void
    {
        $rest = new Formhammer_REST($this->validator());
        $request = new WP_REST_Request([]);

        $response = $rest->handle_token_request($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('formhammer_invalid_form_id', $response->get_error_code());
        self::assertSame(['status' => 400], $response->get_error_data());
    }

    public function testHandleTokenRequestRejectsBlankFormId(): void
    {
        $rest = new Formhammer_REST($this->validator());
        $request = new WP_REST_Request(['form_id' => '   ']);

        $response = $rest->handle_token_request($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('formhammer_invalid_form_id', $response->get_error_code());
    }

    public function testHandleTokenRequestRejectsInvalidFormIdCharacters(): void
    {
        $rest = new Formhammer_REST($this->validator());
        $request = new WP_REST_Request(['form_id' => 'contact/form']);

        $response = $rest->handle_token_request($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('formhammer_invalid_form_id', $response->get_error_code());
        self::assertSame(['status' => 400], $response->get_error_data());
    }

    public function testHandleTokenRequestRejectsFormIdLongerThanOneHundredCharacters(): void
    {
        $rest = new Formhammer_REST($this->validator());
        $request = new WP_REST_Request(['form_id' => str_repeat('a', 101)]);

        $response = $rest->handle_token_request($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('formhammer_invalid_form_id', $response->get_error_code());
    }

    public function testHandleTokenRequestAllowsAlphanumericDashAndUnderscoreFormIds(): void
    {
        $validator = $this->validator();
        $rest = new Formhammer_REST($validator);
        $request = new WP_REST_Request(['form_id' => 'Contact_123-form']);

        $response = $rest->handle_token_request($request);
        $data = $this->response_data($response);

        self::assertTrue($validator->verify_token($data['token'], 'Contact_123-form')->is_valid());
    }

    public function testHandleTokenRequestRateLimitsByIpAfterTenRequestsPerMinute(): void
    {
        $rest = new Formhammer_REST($this->validator());
        $request = new WP_REST_Request(['form_id' => 'contact-form-1']);

        for ($i = 0; $i < 10; $i++) {
            self::assertNotInstanceOf(WP_Error::class, $rest->handle_token_request($request));
        }

        $response = $rest->handle_token_request($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('formhammer_rate_limit_exceeded', $response->get_error_code());
        self::assertSame(['status' => 429], $response->get_error_data());
    }

    private function validator(int $now = 1_700_000_000): Formhammer_Validator
    {
        return new Formhammer_Validator(
            secret_key: self::SECRET,
            max_age: 3600,
            clock: static fn (): int => $now,
            random_bytes: static fn (int $length): string => str_repeat('a', $length)
        );
    }

    private function response_data(mixed $response): array
    {
        if ($response instanceof WP_REST_Response) {
            return $response->get_data();
        }

        self::assertIsArray($response);

        return $response;
    }
}
