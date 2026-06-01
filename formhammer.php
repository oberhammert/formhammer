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
/**
 * Plugin Name: Formhammer
 * Plugin URI: https://github.com/oberhammert/formhammer
 * Description: Block spam. Not users. Form spam protection without CAPTCHA, external APIs, or stored submissions.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Tobias Oberhammer
 * Author URI: https://iamhammer.at
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: formhammer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FORMHAMMER_VERSION', '1.0.0' );
define( 'FORMHAMMER_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORMHAMMER_URL', plugin_dir_url( __FILE__ ) );

require_once FORMHAMMER_DIR . 'includes/class-validator.php';
require_once FORMHAMMER_DIR . 'includes/class-injector.php';
require_once FORMHAMMER_DIR . 'includes/class-settings.php';
require_once FORMHAMMER_DIR . 'includes/class-logger.php';
require_once FORMHAMMER_DIR . 'includes/class-rest.php';

function formhammer_secret_key(): string {
    $secret_key = get_option( 'formhammer_secret_key', '' );

    if ( ! is_string( $secret_key ) || $secret_key === '' ) {
        $secret_key = bin2hex( random_bytes( 32 ) );
        update_option( 'formhammer_secret_key', $secret_key );
    }

    return $secret_key;
}

function formhammer_validator(): Formhammer_Validator {
    static $validator = null;

    if ( ! $validator instanceof Formhammer_Validator ) {
        $validator = new Formhammer_Validator( formhammer_secret_key() );
    }

    return $validator;
}

function formhammer_validate( array $post_data, string $form_id ): Formhammer_Validation_Result {
    return formhammer_validator()->validate( $post_data, $form_id );
}

function formhammer_get_fields( string $form_id ): string {
    return ( new Formhammer_Injector() )->render_fields( $form_id );
}

function formhammer_fields( string $form_id ): void {
    echo formhammer_get_fields( $form_id );
}

function formhammer_register_services(): void {
    ( new Formhammer_Settings() )->register();
    ( new Formhammer_Logger() )->register();

    add_action( 'rest_api_init', static function (): void {
        ( new Formhammer_REST( formhammer_validator() ) )->register_routes();
    } );
}
add_action( 'plugins_loaded', 'formhammer_register_services' );

function formhammer_enqueue_assets(): void {
    wp_enqueue_script(
        'formhammer',
        FORMHAMMER_URL . 'assets/formhammer.js',
        [],
        FORMHAMMER_VERSION,
        true
    );

    wp_add_inline_script(
        'formhammer',
        'window.formhammerRestUrl = ' . wp_json_encode( rest_url() ) . ';',
        'before'
    );
}
add_action( 'wp_enqueue_scripts', 'formhammer_enqueue_assets' );

function formhammer_load_integrations() {
    if ( class_exists( 'WPCF7' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/cf7.php';
        ( new Formhammer_CF7_Integration() )->register();
    }
    if ( class_exists( 'ElementorPro\Plugin' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/elementor.php';
        ( new Formhammer_Elementor_Integration() )->register();
    }
    if ( class_exists( 'WPForms' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/wpforms.php';
        ( new Formhammer_WPForms_Integration() )->register();
    }
    if ( class_exists( 'GFForms' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/gravity-forms.php';
        ( new Formhammer_Gravity_Forms_Integration() )->register();
    }
}
add_action( 'plugins_loaded', 'formhammer_load_integrations' );

function formhammer_activate() {
    if ( ! get_option( 'formhammer_secret_key' ) ) {
        update_option( 'formhammer_secret_key', bin2hex( random_bytes( 32 ) ) );
    }
}
register_activation_hook( __FILE__, 'formhammer_activate' );

function formhammer_deactivate() {
    wp_clear_scheduled_hook( 'formhammer_log_cleanup' );
}
register_deactivation_hook( __FILE__, 'formhammer_deactivate' );
