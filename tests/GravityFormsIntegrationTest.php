<?php

declare(strict_types=1);

if (!class_exists('GFForms')) {
    class GFForms
    {
    }
}

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../includes/integrations/gravity-forms.php';

use PHPUnit\Framework\TestCase;

final class GravityFormsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['formhammer_registered_filters'] = [];
        $GLOBALS['formhammer_registered_actions'] = [];
        $GLOBALS['formhammer_validate_calls'] = [];
        $GLOBALS['formhammer_fields_calls'] = [];
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::pass('pass');
        $_POST = [];
    }

    public function testRegisterHooksWhenGravityFormsIsActive(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();

        $integration->register();

        self::assertCount(3, $GLOBALS['formhammer_registered_filters']);
        self::assertSame('gform_validation', $GLOBALS['formhammer_registered_filters'][0]['hook']);
        self::assertSame(2, $GLOBALS['formhammer_registered_filters'][0]['accepted_args']);
        self::assertSame('gform_get_form_filter', $GLOBALS['formhammer_registered_filters'][1]['hook']);
        self::assertSame(2, $GLOBALS['formhammer_registered_filters'][1]['accepted_args']);
        self::assertSame('gform_form_tag', $GLOBALS['formhammer_registered_filters'][2]['hook']);
        self::assertSame(2, $GLOBALS['formhammer_registered_filters'][2]['accepted_args']);
    }

    public function testValidateCallsFormhammerValidateWithGravityPrefixedFormId(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $_POST = ['field' => 'value'];
        $validation_result = [
            'is_valid' => true,
            'form' => [
                'id' => 55,
                'fields' => [],
            ],
        ];

        $returned = $integration->validate($validation_result, 'form-submit');

        self::assertSame($validation_result, $returned);
        self::assertCount(1, $GLOBALS['formhammer_validate_calls']);
        self::assertSame('gf-55', $GLOBALS['formhammer_validate_calls'][0]['form_id']);
        self::assertSame($_POST, $GLOBALS['formhammer_validate_calls'][0]['post_data']);
    }

    public function testInjectFieldsInsertsFieldBundleIntoFormHtml(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $GLOBALS['formhammer_fields_markup'] = implode('', [
            '<div class="formhammer-hp" aria-hidden="true">',
            '<label for="hl_website_55">Website (leave empty)</label>',
            '<input type="text" name="hl_website" id="hl_website_55" autocomplete="off" tabindex="-1">',
            '</div>',
            '<input type="hidden" name="hl_elapsed" id="hl_elapsed_55" value="">',
            '<input type="hidden" name="hl_token" id="hl_token_55" value="">',
        ]);
        $form_html = '<form id="gform_55"><div>content</div></form>';

        $returned = $integration->inject_fields($form_html, ['id' => 55]);

        self::assertStringContainsString($GLOBALS['formhammer_fields_markup'], $returned);
        self::assertStringContainsString('hl_website_55', $returned);
        self::assertStringContainsString('hl_elapsed_55', $returned);
        self::assertStringContainsString('hl_token_55', $returned);
        self::assertStringEndsWith('</form>', $returned);
    }

    public function testAddFormAttributeMarksGravityFormForJavascript(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $form_tag = '<form id="gform_55" method="post">';

        $returned = $integration->add_form_attribute($form_tag, ['id' => 55]);

        self::assertSame('<form id="gform_55" method="post" data-formhammer="gf-55">', $returned);
    }

    public function testValidateSkipsWhenFormMetaTurnsFormhammerOff(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');
        $field = new Formhammer_Test_GravityField();
        $validation_result = [
            'is_valid' => true,
            'form' => [
                'id' => 55,
                'meta' => ['formhammer' => 'off'],
                'fields' => [$field],
            ],
        ];

        $returned = $integration->validate($validation_result, 'form-submit');

        self::assertSame($validation_result, $returned);
        self::assertSame([], $GLOBALS['formhammer_validate_calls']);
        self::assertFalse($field->failed_validation);
    }

    public function testInjectFieldsSkipsWhenFormMetaTurnsFormhammerOff(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $form_html = '<form id="gform_55"><div>content</div></form>';

        $returned = $integration->inject_fields($form_html, ['id' => 55, 'meta' => ['formhammer' => 'off']]);

        self::assertSame($form_html, $returned);
        self::assertSame([], $GLOBALS['formhammer_fields_calls']);
    }

    public function testAddFormAttributeSkipsWhenFormMetaTurnsFormhammerOff(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $form_tag = '<form id="gform_55" method="post">';

        $returned = $integration->add_form_attribute($form_tag, ['id' => 55, 'meta' => ['formhammer' => 'off']]);

        self::assertSame($form_tag, $returned);
    }

    public function testValidateBlocksSubmissionOnRejectVerdict(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');
        $field = new Formhammer_Test_GravityField();
        $validation_result = [
            'is_valid' => true,
            'form' => [
                'id' => 55,
                'fields' => [$field],
            ],
        ];

        $returned = $integration->validate($validation_result, 'form-submit');

        self::assertFalse($returned['is_valid']);
        self::assertTrue($field->failed_validation);
        self::assertSame('Submission blocked.', $field->validation_message);
    }

    public function testValidateBlocksSubmissionOnBlockVerdict(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::block('score_threshold_block', 40);
        $field = new Formhammer_Test_GravityField();
        $validation_result = [
            'is_valid' => true,
            'form' => [
                'id' => 55,
                'fields' => [$field],
            ],
        ];

        $returned = $integration->validate($validation_result, 'form-submit');

        self::assertFalse($returned['is_valid']);
        self::assertSame('Submission blocked.', $field->validation_message);
    }

    public function testValidateDoesNotBlockPassOrFlagVerdict(): void
    {
        $integration = new Formhammer_Gravity_Forms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::flag('score_threshold_flag', 40);
        $field = new Formhammer_Test_GravityField();
        $validation_result = [
            'is_valid' => true,
            'form' => [
                'id' => 55,
                'fields' => [$field],
            ],
        ];

        $returned = $integration->validate($validation_result, 'form-submit');

        self::assertTrue($returned['is_valid']);
        self::assertFalse($field->failed_validation);
        self::assertSame('', $field->validation_message);
    }
}

final class Formhammer_Test_GravityField
{
    public bool $failed_validation = false;
    public string $validation_message = '';
}
