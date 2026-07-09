<?php
/**
 * Plugin Name: Catalogo70
 * Plugin URI: https://codigo70.com/codecatalogo
 * Description: Crea catálogos de productos optimizados para SEO con campos personalizables
 * Version: 1.0.0
 * Author: Codigo70
 * Author URI: https://codigo70.com
 * Text Domain: catalogo70
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CODECATALOGO_VERSION', '1.0.0');
define('CODECATALOGO_PATH', plugin_dir_path(__FILE__));
define('CODECATALOGO_URL', plugin_dir_url(__FILE__));
define('CODECATALOGO_BASENAME', plugin_basename(__FILE__));
define('CODECATALOGO_DB_VERSION', '1.0.0');

final class CodeCatalogo_Pro {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    private function load_dependencies() {
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-activator.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-deactivator.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-i18n.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-cpt.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-field-manager.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-seo.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-ajax.php';
        require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-admin.php';
        require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-settings.php';
        require_once CODECATALOGO_PATH . 'public/class-codecatalogo-display.php';
        require_once CODECATALOGO_PATH . 'public/class-codecatalogo-public.php';
    }
    
    private function set_locale() {
        $plugin_i18n = new CodeCatalogo_i18n();
        add_action('plugins_loaded', array($plugin_i18n, 'load_plugin_textdomain'));
    }
    
    private function define_admin_hooks() {
        $admin = new CodeCatalogo_Admin();
        $settings = new CodeCatalogo_Settings();

        add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
        add_action('admin_menu', array($settings, 'add_menu_pages'));
    }
    
    private function define_public_hooks() {
        $public = new CodeCatalogo_Public();
        
        add_action('wp_enqueue_scripts', array($public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($public, 'enqueue_scripts'));
    }
}

function activate_codecatalogo_pro() {
    require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-activator.php';
    CodeCatalogo_Activator::activate();
}
register_activation_hook(__FILE__, 'activate_codecatalogo_pro');

function deactivate_codecatalogo_pro() {
    require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-deactivator.php';
    CodeCatalogo_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_codecatalogo_pro');

function codecatalogo_pro() {
    return CodeCatalogo_Pro::instance();
}

codecatalogo_pro();
