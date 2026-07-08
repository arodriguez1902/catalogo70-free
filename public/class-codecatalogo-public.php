<?php
/**
 * Funcionalidad del área pública
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Public {
    
    public function __construct() {
        add_shortcode('codecatalogo', array($this, 'catalog_shortcode'));
        add_shortcode('codecatalogo_search', array($this, 'search_shortcode'));
    }
    
    /**
     * Encolar estilos del frontend
     */
    public function enqueue_styles() {
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'codecatalogo-public',
            CODECATALOGO_URL . 'public/css/catalog-styles.css',
            array('dashicons'),
            CODECATALOGO_VERSION
        );
        
        // Agregar variables CSS personalizadas
        $primary_color = get_option('codecatalogo_primary_color', '#0073aa');
        $secondary_color = get_option('codecatalogo_secondary_color', '#25D366');
        
        $custom_css = "
            :root {
                --codecatalogo-primary: {$primary_color};
                --codecatalogo-secondary: {$secondary_color};
            }
        ";
        
        wp_add_inline_style('codecatalogo-public', $custom_css);
    }
    
    /**
     * Encolar scripts del frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'codecatalogo-public',
            CODECATALOGO_URL . 'public/js/catalog-scripts.js',
            array('jquery'),
            CODECATALOGO_VERSION,
            true
        );
        
        wp_localize_script('codecatalogo-public', 'codecatalogoPublic', array(
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('codecatalogo_contact_form'),
            'strings' => array(
                'loading' => __('Cargando...', 'catalogo70'),
                'no_results' => __('No se encontraron productos.', 'catalogo70'),
                'error' => __('Error al cargar los productos.', 'catalogo70'),
                'sending' => __('Enviando...', 'catalogo70'),
                'required_fields' => __('Por favor completa todos los campos requeridos.', 'catalogo70'),
            ),
        ));
    }
    
    /**
     * Shortcode para mostrar el catálogo
     * Uso: [codecatalogo category="10" per_page="12" layout="grid"]
     */
    public function catalog_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'per_page' => get_option('codecatalogo_products_per_page', 12),
            'layout' => 'grid',
            'columns' => '3',
            'show_filters' => 'yes',
            'show_search' => 'yes',
        ), $atts);
        
        $display = new CodeCatalogo_Display();
        return $display->render_catalog($atts);
    }
    
    /**
     * Shortcode para búsqueda
     * Uso: [codecatalogo_search]
     */
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => esc_html__('Buscar productos...', 'catalogo70'),
        ), $atts);
        
        ob_start();
        ?>
        <div class="codecatalogo-search-widget">
            <form class="codecatalogo-search-form" role="search">
                <input type="search" 
                       class="codecatalogo-search-input" 
                       placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                       name="codecatalogo_search">
                <button type="submit" class="codecatalogo-search-submit">
                    <span class="dashicons dashicons-search"></span>
                </button>
            </form>
            <div class="codecatalogo-search-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}