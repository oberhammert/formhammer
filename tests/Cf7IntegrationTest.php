<?php

declare(strict_types=1);

if (!class_exists('WPCF7')) {
    class WPCF7
    {
    }
}

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../includes/integrations/cf7.php';

use PHPUnit\Framework\TestCase;

final class Cf7IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['formhammer_registered_filters'] = [];
        $GLOBALS['formhammer_registered_actions'] = [];
        $GLOBALS['formhammer_validate_calls'] = [];
        $GLOBALS['formhammer_fields_calls'] = [];
        $GLOBALS['formhammer_fields_markup'] = '<input name="hl_token" value="token">';
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::pass('pass');
        $_POST = [];
    }

    public function testRegisterHooksWhenCf7IsActive(): void
    {
        $integration = new Formhammer_CF7_Integration();

        $integration->register();

        self::assertCount(1, $GLOBALS['formhammer_registered_actions']);
        self::assertCount(3, $GLOBALS['formhammer_registered_filters']);
        self::assertSame('wpcf7_contact_form', $GLOBALS['formhammer_registered_actions'][0]['hook']);
        self::assertSame('wpcf7_validate', $GLOBALS['formhammer_registered_filters'][0]['hook']);
        self::assertSame(2, $GLOBALS['formhammer_registered_filters'][0]['accepted_args']);
        self::assertSame('wpcf7_form_elements', $GLOBALS['formhammer_registered_filters'][1]['hook']);
        self::assertSame('wpcf7_form_additional_atts', $GLOBALS['formhammer_registered_filters'][2]['hook']);
        self::assertSame(1, $GLOBALS['formhammer_registered_filters'][2]['accepted_args']);
    }

    public function testValidateCallsFormhammerValidateWithCf7PrefixedFormId(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321));
        $result = new Formhammer_Test_Cf7ValidationResult();
        $_POST = ['field' => 'value'];

        $returned = $integration->validate($result, [['name' => 'your-name']]);

        self::assertSame($result, $returned);
        self::assertCount(1, $GLOBALS['formhammer_validate_calls']);
        self::assertSame('cf7-321', $GLOBALS['formhammer_validate_calls'][0]['form_id']);
        self::assertSame($_POST, $GLOBALS['formhammer_validate_calls'][0]['post_data']);
        self::assertSame([], $result->invalidations);
    }

    public function testValidateBlocksSubmissionOnRejectVerdict(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321));
        $result = new Formhammer_Test_Cf7ValidationResult();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');

        $integration->validate($result, [['name' => 'your-name']]);

        self::assertCount(1, $result->invalidations);
        self::assertSame(['name' => 'your-name'], $result->invalidations[0]['tag']);
        self::assertSame('Submission blocked.', $result->invalidations[0]['message']);
    }

    public function testValidateBlocksSubmissionOnBlockVerdict(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321));
        $result = new Formhammer_Test_Cf7ValidationResult();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::block('score_threshold_block', 40);

        $integration->validate($result, [['name' => 'your-name']]);

        self::assertCount(1, $result->invalidations);
        self::assertSame('Submission blocked.', $result->invalidations[0]['message']);
    }

    public function testValidateDoesNotBlockPassOrFlagVerdict(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321));
        $result = new Formhammer_Test_Cf7ValidationResult();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::flag('score_threshold_flag', 40);

        $integration->validate($result, [['name' => 'your-name']]);

        self::assertSame([], $result->invalidations);
    }

    public function testValidateSkipsWhenFormShortcodeOptOutIsOff(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321, 'off'));
        $result = new Formhammer_Test_Cf7ValidationResult();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');

        $returned = $integration->validate($result, [['name' => 'your-name']]);

        self::assertSame($result, $returned);
        self::assertSame([], $GLOBALS['formhammer_validate_calls']);
        self::assertSame([], $result->invalidations);
    }

    public function testInjectFieldsAppendsFormhammerMarkupToFormElements(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321));

        $html = $integration->inject_fields('<p>Form</p>');

        self::assertSame('<p>Form</p><input name="hl_token" value="token">', $html);
        self::assertSame(['cf7-321'], $GLOBALS['formhammer_fields_calls']);
    }

    public function testAddFormAttributeMarksCf7FormForJavascript(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321));

        $atts = $integration->add_form_attribute(['class' => 'wpcf7-form']);

        self::assertSame('wpcf7-form', $atts['class']);
        self::assertSame('cf7-321', $atts['data-formhammer']);
    }

    public function testInjectFieldsSkipsWhenFormShortcodeOptOutIsOff(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321, 'off'));

        $html = $integration->inject_fields('<p>Form</p>');

        self::assertSame('<p>Form</p>', $html);
        self::assertSame([], $GLOBALS['formhammer_fields_calls']);
    }

    public function testAddFormAttributeSkipsWhenFormShortcodeOptOutIsOff(): void
    {
        $integration = new Formhammer_CF7_Integration();
        $integration->capture_contact_form(new Formhammer_Test_Cf7ContactForm(321, 'off'));

        $atts = $integration->add_form_attribute(['class' => 'wpcf7-form']);

        self::assertSame(['class' => 'wpcf7-form'], $atts);
    }
}

final class Formhammer_Test_Cf7ContactForm
{
    public function __construct(
        private int $id,
        private string $formhammer = ''
    )
    {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function shortcode_attr(string $name): string
    {
        return $name === 'formhammer' ? $this->formhammer : '';
    }
}

final class Formhammer_Test_Cf7ValidationResult
{
    public array $invalidations = [];

    public function invalidate(mixed $tag, string $message): void
    {
        $this->invalidations[] = [
            'tag' => $tag,
            'message' => $message,
        ];
    }
}
