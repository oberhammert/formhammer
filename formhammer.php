<?php
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

function formhammer_load_integrations() {
    if ( class_exists( 'WPCF7' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/cf7.php';
    }
    if ( class_exists( 'ElementorPro\Plugin' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/elementor.php';
    }
    if ( class_exists( 'WPForms' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/wpforms.php';
    }
    if ( class_exists( 'GFForms' ) ) {
        require_once FORMHAMMER_DIR . 'includes/integrations/gravity-forms.php';
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
