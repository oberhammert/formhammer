<?php

declare(strict_types=1);

namespace ElementorPro {
    class Plugin
    {
    }
}

namespace {
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once __DIR__ . '/../includes/integrations/elementor.php';

    use PHPUnit\Framework\TestCase;

    final class ElementorIntegrationTest extends TestCase
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

        public function testRegisterHooksWhenElementorProIsActive(): void
        {
            $integration = new Formhammer_Elementor_Integration();

            $integration->register();

            self::assertCount(2, $GLOBALS['formhammer_registered_actions']);
            self::assertCount(1, $GLOBALS['formhammer_registered_filters']);
            self::assertSame('elementor_pro/forms/validation', $GLOBALS['formhammer_registered_actions'][0]['hook']);
            self::assertSame(2, $GLOBALS['formhammer_registered_actions'][0]['accepted_args']);
            self::assertSame('elementor_pro/forms/render_field/after', $GLOBALS['formhammer_registered_actions'][1]['hook']);
            self::assertSame(4, $GLOBALS['formhammer_registered_actions'][1]['accepted_args']);
            self::assertSame('elementor/widget/render_content', $GLOBALS['formhammer_registered_filters'][0]['hook']);
            self::assertSame(2, $GLOBALS['formhammer_registered_filters'][0]['accepted_args']);
        }

        public function testValidateCallsFormhammerValidateWithElementorPrefixedFormId(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $record = new Formhammer_Test_ElementorRecord('form-123');
            $ajax_handler = new Formhammer_Test_ElementorAjaxHandler();
            $_POST = ['field' => 'value'];

            $integration->validate($record, $ajax_handler);

            self::assertCount(1, $GLOBALS['formhammer_validate_calls']);
            self::assertSame('elementor-form-123', $GLOBALS['formhammer_validate_calls'][0]['form_id']);
            self::assertSame($_POST, $GLOBALS['formhammer_validate_calls'][0]['post_data']);
            self::assertSame([], $ajax_handler->messages);
        }

        public function testValidateBlocksSubmissionOnRejectVerdict(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $record = new Formhammer_Test_ElementorRecord('form-123');
            $ajax_handler = new Formhammer_Test_ElementorAjaxHandler();
            $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');

            $integration->validate($record, $ajax_handler);

            self::assertSame(['Submission blocked.'], $ajax_handler->messages);
        }

        public function testValidateBlocksSubmissionOnBlockVerdict(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $record = new Formhammer_Test_ElementorRecord('form-123');
            $ajax_handler = new Formhammer_Test_ElementorAjaxHandler();
            $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::block('score_threshold_block', 40);

            $integration->validate($record, $ajax_handler);

            self::assertSame(['Submission blocked.'], $ajax_handler->messages);
        }

        public function testValidateDoesNotBlockPassOrFlagVerdict(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $record = new Formhammer_Test_ElementorRecord('form-123');
            $ajax_handler = new Formhammer_Test_ElementorAjaxHandler();
            $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::flag('score_threshold_flag', 40);

            $integration->validate($record, $ajax_handler);

            self::assertSame([], $ajax_handler->messages);
        }

        public function testValidateSkipsWhenCustomFormSettingTurnsFormhammerOff(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $record = new Formhammer_Test_ElementorRecord('form-123', ['formhammer' => 'off']);
            $ajax_handler = new Formhammer_Test_ElementorAjaxHandler();
            $GLOBALS['formhammer_validate_result'] = Formhammer_Validation_Result::reject('missing_token');

            $integration->validate($record, $ajax_handler);

            self::assertSame([], $GLOBALS['formhammer_validate_calls']);
            self::assertSame([], $ajax_handler->messages);
        }

        public function testInjectFieldsOutputsFormhammerMarkupForFirstRenderedItemOnly(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $form = new Formhammer_Test_ElementorForm('form-123');

            ob_start();
            $integration->inject_fields('text', ['field_type' => 'text'], 0, $form);
            $output = ob_get_clean();

            self::assertSame('<input name="hl_token" value="token">', $output);
            self::assertSame(['elementor-form-123'], $GLOBALS['formhammer_fields_calls']);
        }

        public function testAddFormAttributeMarksElementorFormForJavascript(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $widget = new Formhammer_Test_ElementorForm('form-123', [], 'form');
            $content = '<div><form class="elementor-form" method="post"><input></form></div>';

            $returned = $integration->add_form_attribute($content, $widget);

            self::assertSame(
                '<div><form class="elementor-form" method="post" data-formhammer="elementor-form-123"><input></form></div>',
                $returned
            );
        }

        public function testInjectFieldsSkipsNonFirstRenderedItems(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $form = new Formhammer_Test_ElementorForm('form-123');

            ob_start();
            $integration->inject_fields('email', ['field_type' => 'email'], 1, $form);
            $output = ob_get_clean();

            self::assertSame('', $output);
            self::assertSame([], $GLOBALS['formhammer_fields_calls']);
        }

        public function testInjectFieldsSkipsWhenCustomFormSettingTurnsFormhammerOff(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $form = new Formhammer_Test_ElementorForm('form-123', ['formhammer' => 'off']);

            ob_start();
            $integration->inject_fields('text', ['field_type' => 'text'], 0, $form);
            $output = ob_get_clean();

            self::assertSame('', $output);
            self::assertSame([], $GLOBALS['formhammer_fields_calls']);
        }

        public function testAddFormAttributeSkipsWhenCustomFormSettingTurnsFormhammerOff(): void
        {
            $integration = new Formhammer_Elementor_Integration();
            $widget = new Formhammer_Test_ElementorForm('form-123', ['formhammer' => 'off'], 'form');
            $content = '<form class="elementor-form" method="post"></form>';

            $returned = $integration->add_form_attribute($content, $widget);

            self::assertSame($content, $returned);
        }
    }

    final class Formhammer_Test_ElementorRecord
    {
        public function __construct(
            private string $id,
            private array $settings = [],
            private string $name = ''
        )
        {
        }

        public function get_name(): string
        {
            return $this->name;
        }

        public function get_form_settings(string $key): string
        {
            if ($key === 'id') {
                return $this->id;
            }

            return isset($this->settings[$key]) ? (string) $this->settings[$key] : '';
        }
    }

    final class Formhammer_Test_ElementorAjaxHandler
    {
        public array $messages = [];

        public function add_error_message(string $message): void
        {
            $this->messages[] = $message;
        }
    }

    final class Formhammer_Test_ElementorForm
    {
        public function __construct(
            private string $id,
            private array $settings = []
        )
        {
        }

        public function get_settings(string $key): string
        {
            if ($key === 'id') {
                return $this->id;
            }

            return isset($this->settings[$key]) ? (string) $this->settings[$key] : '';
        }
    }
}
