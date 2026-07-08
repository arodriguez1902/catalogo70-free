<?php
/**
 * Plugin Name: Catalogo70
 * Plugin URI: https://codigo70.com/codecatalogo-pro
 * Description: Plugin ligero para crear catálogos de productos optimizados para SEO con campos personalizables y CTAs inteligentes
 * Plugin URI: https://codigo70.com/codecatalogo
 * Plugin URI: https://codigo70.com/codecatalogo-pro
 * Author URI: https://codigo70.com
 * Text Domain: catalogo70
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Mostrar errores PHP en pantalla (solo para depuración - quitar después)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Constantes del plugin
define('CODECATALOGO_VERSION', '1.4.0');
define('CODECATALOGO_PATH', plugin_dir_path(__FILE__));
define('CODECATALOGO_URL', plugin_dir_url(__FILE__));
define('CODECATALOGO_BASENAME', plugin_basename(__FILE__));
define('CODECATALOGO_DB_VERSION', '1.0.0');

/**
 * Clase principal del plugin
 */
final class CodeCatalogo_Pro {
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
        /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        // Core
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-activator.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-deactivator.php';
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-i18n.php';

        // CPT y Taxonomías
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-cpt.php';

        // Sistema de licencias
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-license.php';

        // Sistema de campos
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-field-manager.php';
        // CTAs
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-cta-handler.php';

        // SEO
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-seo.php';

        // AJAX
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-ajax.php';

        // Admin
        require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-admin.php';
        require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-settings.php';

        // Exportador
        require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-exporter.php';

                // Public
        require_once CODECATALOGO_PATH . 'public/class-codecatalogo-display.php';
        require_once CODECATALOGO_PATH . 'public/class-codecatalogo-public.php';
    }
    
    /**
     */
    private function set_locale() {
        $plugin_i18n = new CodeCatalogo_i18n();
        add_action('plugins_loaded', array($plugin_i18n, 'load_plugin_textdomain'));
    }
    
    /**
     * Hooks del admin
     */
    private function define_admin_hooks() {
        $admin = new CodeCatalogo_Admin();
        $settings = new CodeCatalogo_Settings();
        $license = new CodeCatalogo_License();

                add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
        add_action('admin_menu', array($settings, 'add_menu_pages'));
        
        // Forzar creación de tablas DB si no existen
        add_action('admin_init', array('CodeCatalogo_Activator', 'create_contacts_table'));
    }
    
    /**
     * Hooks del frontend
     */
    private function define_public_hooks() {
        $public = new CodeCatalogo_Public();
        
        add_action('wp_enqueue_scripts', array($public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($public, 'enqueue_scripts'));
    }
}

/**
 * Activación del plugin con captura de errores
 */
function activate_codecatalogo_pro() {
    try {
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-activator.php';
        
        // Verificar que la clase existe antes de usarla
        if (!class_exists('CodeCatalogo_Activator')) {
            throw new Exception('La clase CodeCatalogo_Activator no se encuentra en: ' . CODECATALOGO_PATH . 'includes/class-codecatalogo-activator.php');
        }
        
        CodeCatalogo_Activator::activate();
        
        // Verificar que las tablas se crearon
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'codecatalogo_fields',
            $wpdb->prefix . 'codecatalogo_field_values',
            $wpdb->prefix . 'codecatalogo_ctas',
        );
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                throw new Exception("La tabla {$table} no se creó correctamente. Error DB: " . $wpdb->last_error);
            }
        }
        
    } catch (Exception $e) {
        // Mostrar error en pantalla y en el log
        $error_msg = 'Error al activar Catalogo70: ' . $e->getMessage();
        error_log($error_msg);
        wp_die(
            '<div style="padding:20px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;margin:20px 0;">' .
            '<h2 style="color:#721c24;margin-top:0;">❌ Error al activar Catalogo70</h2>' .
            '<p style="color:#721c24;">' . esc_html($error_msg) . '</p>' .
            '<p style="color:#856404;background:#fff3cd;padding:10px;border-radius:4px;">' .
            '<strong>💡 Sugerencia:</strong> Verifica que la versión de PHP sea 7.4 o superior y que los permisos de la base de datos sean correctos.</p>' .
            '</div>',
            'Error al activar el plugin',
            array('back_link' => true)
        );
    }
}
register_activation_hook(__FILE__, 'activate_codecatalogo_pro');

/**
 * Desactivación del plugin
 */
function deactivate_codecatalogo_pro() {
    require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-deactivator.php';
    CodeCatalogo_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_codecatalogo_pro');

/**
 * Iniciar el plugin
 */
function codecatalogo_pro() {
    return CodeCatalogo_Pro::instance();
}

// Ejecutar plugin con captura de errores
try {
    codecatalogo_pro();
} catch (Throwable $e) {
    // Si hay error al cargar, mostrar en pantalla
    $error_msg = 'ERROR FATAL en Catalogo70: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine();
    error_log($error_msg);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div style="background:#f8d7da;padding:15px;border:1px solid #f5c6cb;margin:10px 0;border-radius:4px;color:#721c24;">';
        echo '<strong>❌ ' . esc_html($error_msg) . '</strong>';
        echo '</div>';
    }
}