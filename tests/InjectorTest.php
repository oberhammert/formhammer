<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InjectorTest extends TestCase
{
    public function testRenderFieldsReturnsHoneypotAndTimingFieldBundle(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_fields('contact-form-1');

        self::assertStringContainsString('<div class="formhammer-hp" aria-hidden="true">', $markup);
        self::assertStringContainsString(
            '<input type="hidden" name="hl_elapsed" id="hl_elapsed_contact-form-1" value="">',
            $markup
        );
        self::assertStringContainsString(
            '<input type="hidden" name="hl_token" id="hl_token_contact-form-1" value="">',
            $markup
        );
    }

    public function testRenderHoneypotReturnsExpectedMarkup(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_honeypot('contact-form-1');

        self::assertStringContainsString('<div class="formhammer-hp" aria-hidden="true">', $markup);
        self::assertStringContainsString('<label for="hl_website_contact-form-1">Website (leave empty)</label>', $markup);
        self::assertStringContainsString(
            '<input type="text" name="hl_website" id="hl_website_contact-form-1" autocomplete="off" tabindex="-1">',
            $markup
        );
    }

    public function testRenderTimingFieldReturnsExpectedMarkup(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_timing_field('contact-form-1');

        self::assertSame(
            '<input type="hidden" name="hl_elapsed" id="hl_elapsed_contact-form-1" value="">',
            $markup
        );
    }

    public function testRenderTokenFieldReturnsExpectedMarkup(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_token_field('contact-form-1');

        self::assertSame(
            '<input type="hidden" name="hl_token" id="hl_token_contact-form-1" value="">',
            $markup
        );
    }

    public function testRenderHoneypotEscapesUntrustedFormId(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_honeypot('"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $markup);
        self::assertStringContainsString('id="hl_website_script_alert_1_script"', $markup);
    }

    public function testRenderTimingFieldEscapesUntrustedFormId(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_timing_field('"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $markup);
        self::assertStringContainsString('id="hl_elapsed_script_alert_1_script"', $markup);
    }

    public function testRenderTokenFieldEscapesUntrustedFormId(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_token_field('"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $markup);
        self::assertStringContainsString('id="hl_token_script_alert_1_script"', $markup);
    }

    public function testRenderHoneypotNormalizesDuplicateSeparators(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_honeypot(' contact  form ');

        self::assertStringContainsString('id="hl_website_contact_form"', $markup);
    }

    public function testRenderHoneypotUsesFallbackSuffixWhenSanitizedFormIdIsEmpty(): void
    {
        $injector = new Formhammer_Injector();

        $markup = $injector->render_honeypot('!!!');

        self::assertStringContainsString('id="hl_website_form"', $markup);
    }

    public function testConstructorRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Formhammer_Injector('');
    }
}
