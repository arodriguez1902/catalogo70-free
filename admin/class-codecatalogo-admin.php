<?php
/**
 * Admin - Versión FREE
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Admin {
    
    public function __construct() {
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    public function enqueue_styles($hook) {
        global $post_type;
        if ($post_type === 'codecatalogo_product' || strpos($hook, 'codecatalogo') !== false) {
            wp_enqueue_style('codecatalogo-admin', CODECATALOGO_URL . 'admin/css/admin-styles.css', array(), CODECATALOGO_VERSION);
        }
    }
    
    public function enqueue_scripts($hook) {
        global $post_type;
        if ($post_type === 'codecatalogo_product' || strpos($hook, 'codecatalogo') !== false) {
            wp_enqueue_script('codecatalogo-admin', CODECATALOGO_URL . 'admin/js/admin-scripts.js', array('jquery', 'jquery-ui-sortable'), CODECATALOGO_VERSION, true);
            wp_localize_script('codecatalogo-admin', 'codecatalogoAdmin', array(
                'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('codecatalogo_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => esc_html__('¿Estás seguro de eliminar este campo? Esta acción no se puede deshacer.', 'catalogo70'),
                    'error' => esc_html__('Error', 'catalogo70'),
                    'success' => esc_html__('Éxito', 'catalogo70'),
                ),
            ));
        }
    }
    
    public function show_admin_notices() {
        global $wpdb;
        $fields_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}codecatalogo_fields");
        if ($fields_count == 0 && isset($_GET['post_type']) && $_GET['post_type'] === 'codecatalogo_product') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Catalogo70:', 'catalogo70'); ?></strong>
                    <?php esc_html_e('No tienes campos personalizados configurados.', 'catalogo70'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields')); ?>"><?php esc_html_e('Configurar campos ahora', 'catalogo70'); ?></a>
                </p>
            </div>
            <?php
        }
    }
}
