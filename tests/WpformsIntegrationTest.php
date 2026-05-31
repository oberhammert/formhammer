<?php

declare(strict_types=1);

if (!class_exists('WPForms')) {
    class WPForms
    {
    }
}

require_once __DIR__ . '/../includes/integrations/wpforms.php';

use PHPUnit\Framework\TestCase;

final class WpformsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['formhammer_registered_filters'] = [];
        $GLOBALS['formhammer_registered_actions'] = [];
        $GLOBALS['formhammer_validate_calls'] = [];
        $GLOBALS['formhammer_fields_calls'] = [];
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::pass('pass');
        $GLOBALS['formhammer_wpforms_instance'] = (object) [
            'process' => (object) [
                'errors' => [],
            ],
        ];
        $_POST = [];
    }

    public function testRegisterHooksWhenWpformsIsActive(): void
    {
        $integration = new Formhammer_WPForms_Integration();

        $integration->register();

        self::assertCount(2, $GLOBALS['formhammer_registered_actions']);
        self::assertSame('wpforms_process', $GLOBALS['formhammer_registered_actions'][0]['hook']);
        self::assertSame(3, $GLOBALS['formhammer_registered_actions'][0]['accepted_args']);
        self::assertSame('wpforms_display_submit_before', $GLOBALS['formhammer_registered_actions'][1]['hook']);
        self::assertSame(1, $GLOBALS['formhammer_registered_actions'][1]['accepted_args']);
    }

    public function testValidateCallsFormhammerValidateWithWpformsPrefixedFormId(): void
    {
        $integration = new Formhammer_WPForms_Integration();
        $_POST = ['field' => 'value'];
        $fields = [1 => ['value' => 'John']];
        $entry = ['field' => 'value'];
        $form_data = ['id' => 123];

        $integration->validate($fields, $entry, $form_data);

        self::assertCount(1, $GLOBALS['formhammer_validate_calls']);
        self::assertSame('wpforms-123', $GLOBALS['formhammer_validate_calls'][0]['form_id']);
        self::assertSame($_POST, $GLOBALS['formhammer_validate_calls'][0]['post_data']);
        self::assertSame([], $GLOBALS['formhammer_wpforms_instance']->process->errors);
    }

    public function testInjectFieldsOutputsFieldBundleBeforeSubmitButton(): void
    {
        $integration = new Formhammer_WPForms_Integration();
        $GLOBALS['formhammer_fields_markup'] = implode('', [
            '<div class="formhammer-hp" aria-hidden="true">',
            '<label for="hl_website_123">Website (leave empty)</label>',
            '<input type="text" name="hl_website" id="hl_website_123" autocomplete="off" tabindex="-1">',
            '</div>',
            '<input type="hidden" name="hl_elapsed" id="hl_elapsed_123" value="">',
            '<input type="hidden" name="hl_token" id="hl_token_123" value="">',
        ]);

        ob_start();
        $integration->inject_fields(['id' => 123]);
        $output = ob_get_clean();

        self::assertStringContainsString('hl_website_123', $output);
        self::assertStringContainsString('hl_elapsed_123', $output);
        self::assertStringContainsString('hl_token_123', $output);
        self::assertStringContainsString($GLOBALS['formhammer_fields_markup'], $output);
    }

    public function testValidateSkipsWhenFormMetaTurnsFormhammerOff(): void
    {
        $integration = new Formhammer_WPForms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');

        $integration->validate([], [], ['id' => 123, 'meta' => ['formhammer' => 'off']]);

        self::assertSame([], $GLOBALS['formhammer_validate_calls']);
        self::assertSame([], $GLOBALS['formhammer_wpforms_instance']->process->errors);
    }

    public function testInjectFieldsSkipsWhenFormMetaTurnsFormhammerOff(): void
    {
        $integration = new Formhammer_WPForms_Integration();

        ob_start();
        $integration->inject_fields(['id' => 123, 'meta' => ['formhammer' => 'off']]);
        $output = ob_get_clean();

        self::assertSame('', $output);
        self::assertSame([], $GLOBALS['formhammer_fields_calls']);
    }

    public function testValidateBlocksSubmissionOnRejectVerdict(): void
    {
        $integration = new Formhammer_WPForms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');

        $integration->validate([], [], ['id' => 123]);

        self::assertSame(
            'Submission blocked.',
            $GLOBALS['formhammer_wpforms_instance']->process->errors[123]['header']
        );
    }

    public function testValidateBlocksSubmissionOnBlockVerdict(): void
    {
        $integration = new Formhammer_WPForms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::block('score_threshold_block', 40);

        $integration->validate([], [], ['id' => 123]);

        self::assertSame(
            'Submission blocked.',
            $GLOBALS['formhammer_wpforms_instance']->process->errors[123]['header']
        );
    }

    public function testValidateDoesNotBlockPassOrFlagVerdict(): void
    {
        $integration = new Formhammer_WPForms_Integration();
        $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::flag('score_threshold_flag', 40);

        $integration->validate([], [], ['id' => 123]);

        self::assertSame([], $GLOBALS['formhammer_wpforms_instance']->process->errors);
    }
}
