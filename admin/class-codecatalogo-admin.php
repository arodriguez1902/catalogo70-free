<?php
/**
 * Funcionalidad del área de administración
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Admin {
    
    public function __construct() {
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_importer_assets'));
    }
    
    /**
     * Encolar estilos del admin
     */
    public function enqueue_styles($hook) {
        global $post_type;
        
        if ($post_type === 'codecatalogo_product' || strpos($hook, 'codecatalogo') !== false) {
            wp_enqueue_style(
                'codecatalogo-admin',
                CODECATALOGO_URL . 'admin/css/admin-styles.css',
                array(),
                CODECATALOGO_VERSION
            );
        }
    }
    
    /**
     * Encolar scripts del admin
     */
    public function enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'codecatalogo_product' || strpos($hook, 'codecatalogo') !== false) {
            wp_enqueue_script(
                'codecatalogo-admin',
                CODECATALOGO_URL . 'admin/js/admin-scripts.js',
                array('jquery', 'jquery-ui-sortable'),
                CODECATALOGO_VERSION,
                true
            );
            
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
    
        /**
     * Mostrar notificaciones del admin
     */
    public function show_admin_notices() {
        // Aviso de actualización
        $dismissed = get_option('codecatalogo_update_notice_dismissed', false);
        if (!$dismissed) {
            ?>
            <div class="notice notice-info is-dismissible codecatalogo-update-notice">
                <p>
                    <strong><?php esc_html_e('⚡ CodeCatalogo Pro', 'catalogo70'); ?></strong>
                    <?php esc_html_e('Para actualizar el plugin sin perder datos: desactívalo, reemplaza los archivos y reactívalo. NO es necesario eliminarlo.', 'catalogo70'); ?>
                    <button type="button" class="button button-small codecatalogo-dismiss-update-notice" style="margin-left:10px;">
                        <?php esc_html_e('No mostrar más', 'catalogo70'); ?>
                    </button>
                </p>
            </div>
            <script>
            jQuery(document).on('click', '.codecatalogo-dismiss-update-notice', function() {
                jQuery.ajax({
                    url: ajaxurl,
                    data: { action: 'codecatalogo_dismiss_update_notice' }
                });
                jQuery(this).closest('.notice').fadeOut();
            });
            </script>
            <?php
        }
        
                // Verificar si hay campos configurados
        global $wpdb;
        
        $fields_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}codecatalogo_fields");
        
        if ($fields_count == 0 && isset($_GET['post_type']) && $_GET['post_type'] === 'codecatalogo_product') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('CodeCatalogo Pro:', 'catalogo70'); ?></strong>
                    <?php esc_html_e('No tienes campos personalizados configurados.', 'catalogo70'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields')); ?>">
                        <?php esc_html_e('Configurar campos ahora', 'catalogo70'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Encolar assets del importador
     */
    public function enqueue_importer_assets($hook) {
        // Solo en la página de importar/exportar
        if ($hook !== 'codecatalogo_product_page_codecatalogo-import-export') {
            return;
        }

        wp_enqueue_style(
            'codecatalogo-importer',
            CODECATALOGO_URL . 'admin/css/importer-styles.css',
            array(),
            CODECATALOGO_VERSION
        );

        wp_enqueue_script(
            'codecatalogo-importer',
            CODECATALOGO_URL . 'admin/js/importer-scripts.js',
            array('jquery'),
            CODECATALOGO_VERSION,
            true
        );
    }
}