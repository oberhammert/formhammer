<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../includes/class-settings.php';

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['formhammer_registered_actions'] = [];
        $GLOBALS['formhammer_registered_filters'] = [];
        $GLOBALS['formhammer_registered_settings'] = [];
        $GLOBALS['formhammer_registered_admin_pages'] = [];
        $GLOBALS['formhammer_settings_fields'] = [];
        $GLOBALS['formhammer_do_settings_sections'] = [];
        $GLOBALS['formhammer_test_options'] = [];
        $GLOBALS['formhammer_test_transients'] = [];
        $GLOBALS['formhammer_current_user_can'] = [];
    }

    public function testRegisterAddsPluginActionLinkAndAdminHooks(): void
    {
        $settings = new Formhammer_Settings();

        $settings->register();

        self::assertCount(2, $GLOBALS['formhammer_registered_actions']);
        self::assertCount(1, $GLOBALS['formhammer_registered_filters']);
        self::assertSame('admin_menu', $GLOBALS['formhammer_registered_actions'][0]['hook']);
        self::assertSame('admin_init', $GLOBALS['formhammer_registered_actions'][1]['hook']);
        self::assertSame('plugin_action_links_formhammer/formhammer.php', $GLOBALS['formhammer_registered_filters'][0]['hook']);
    }

    public function testPluginActionLinksPrependsSettingsLink(): void
    {
        $settings = new Formhammer_Settings();

        $links = $settings->plugin_action_links(['Deactivate']);

        self::assertCount(2, $links);
        self::assertStringContainsString('options-general.php?page=formhammer', $links[0]);
        self::assertStringContainsString('Settings', $links[0]);
        self::assertSame('Deactivate', $links[1]);
    }

    public function testRenderPageUsesNativeAdminTablesAndSections(): void
    {
        $GLOBALS['formhammer_test_options'] = [
            'formhammer_enabled' => true,
            'formhammer_min_time' => 3000,
            'formhammer_max_age' => 3600,
            'formhammer_block_threshold' => 60,
            'formhammer_flag_threshold' => 30,
            'formhammer_log_enabled' => false,
            'formhammer_log_retention' => 7,
            'formhammer_bypass_token' => 'abc123',
        ];

        $settings = new Formhammer_Settings();

        ob_start();
        $settings->render_page();
        $output = ob_get_clean();

        self::assertStringContainsString('<table class="form-table" role="presentation">', $output);
        self::assertStringContainsString('<th scope="row">', $output);
        self::assertStringContainsString('Protection', $output);
        self::assertStringContainsString('Thresholds', $output);
        self::assertStringContainsString('Logging', $output);
        self::assertStringContainsString('Security', $output);
        self::assertStringContainsString('type="password"', $output);
        self::assertStringContainsString('Regenerate', $output);
        self::assertStringContainsString('Logging is disabled. Enable it temporarily to debug blocking behavior.', $output);
        self::assertStringContainsString('name="formhammer_bypass_token"', $output);
    }

    public function testRenderPageShowsFlashTokenOnceWithCopyButton(): void
    {
        $GLOBALS['formhammer_test_transients']['formhammer_bypass_token_flash'] = [
            'value' => 'new-token-123',
            'expiration' => 60,
        ];

        $settings = new Formhammer_Settings();

        ob_start();
        $settings->render_page();
        $output = ob_get_clean();

        self::assertStringContainsString('This token will only be shown once. Save it now.', $output);
        self::assertStringContainsString('new-token-123', $output);
        self::assertStringContainsString('Copy', $output);
        self::assertArrayNotHasKey('formhammer_bypass_token_flash', $GLOBALS['formhammer_test_transients']);
    }
}
