<?php
/**
 * Renderizado y visualización del catálogo
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Display {
    
    private $field_manager;
    
    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
    }
    
    /**
 * Renderizar catálogo completo
 */
public function render_catalog($atts = array()) {
    $defaults = array(
        'category' => '',
        'per_page' => 12,
        'layout' => 'grid',
        'columns' => '3',
        'show_filters' => 'yes',
        'show_search' => 'yes',
    );
    
    $args = wp_parse_args($atts, $defaults);
    
    ob_start();
    ?>
    <div class="codecatalogo-wrapper">
        
        <!-- Buscador arriba -->
        <?php if ($args['show_search'] === 'yes'): ?>
        <div class="codecatalogo-toolbar">
            <div class="codecatalogo-search-box">
                <input type="search" 
                       class="codecatalogo-search" 
                       placeholder="<?php esc_html_e('Buscar productos...','catalogo70free'); ?>">
                <button type="button" class="codecatalogo-search-btn">
                    <span class="dashicons dashicons-search"></span>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Layout 2 columnas -->
        <div class="codecatalogo-main-content">
            
            <!-- Filtros (columna izquierda) -->
            <?php if ($args['show_filters'] === 'yes'): ?>
            <aside class="codecatalogo-sidebar">
                <?php $this->render_filters(); ?>
            </aside>
            <?php endif; ?>
            
            <!-- Productos (columna derecha) -->
            <div class="codecatalogo-content">
                <div class="codecatalogo-products-grid codecatalogo-columns-<?php echo esc_attr($args['columns']); ?>">
                    <?php $this->render_products($args); ?>
                </div>
                
                <div class="codecatalogo-pagination">
                    <?php $this->render_pagination($args); ?>
                </div>
            </div>
            
        </div>
        
        <!-- Loading overlay -->
        <div class="codecatalogo-loading" style="display: none;">
            <div class="codecatalogo-spinner"></div>
        </div>
        
    </div>
    
    <!-- Modal de contacto -->

    <?php
    return ob_get_clean();
}
    
    /**
     * Renderizar filtros
     */
    private function render_filters() {
        $filter_fields = $this->field_manager->get_filter_fields();
        
        if (empty($filter_fields)) {
            return;
        }
        
        echo '<div class="codecatalogo-filters-wrapper">';
        echo '<h4>' . esc_html__('Filtrar por:','catalogo70free') . '</h4>';
        
        // Filtro de categorías
        $categories = get_terms(array(
            'taxonomy' => 'codecatalogo_cat',
            'hide_empty' => true,
        ));
        
        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<div class="codecatalogo-filter-group">';
                        echo '<label>' . esc_html__('Categoría','catalogo70free') . '</label>';
            echo '<select class="codecatalogo-filter" data-filter-type="category">';
            echo '<option value="">' . esc_html__('Todas las categorías','catalogo70free') . '</option>';
            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        }
        
                // Filtros de campos personalizados
                foreach ($filter_fields as $field) {
                    echo '<div class="codecatalogo-filter-group">';
                    echo '<label>' . esc_html($field->field_label) . '</label>';
            
                    if ($field->field_type === 'select') {
                        $options = json_decode($field->field_options, true);
                        echo '<select class="codecatalogo-filter" data-filter-field="' . esc_attr($field->id) . '">';
                        echo '<option value="">' . esc_html__('Todos','catalogo70free') . '</option>';
                        foreach ($options as $value => $label) {
                            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
                        }
                        echo '</select>';
                    } else {
                        $unique_values = $this->get_unique_field_values($field->id);
                        if (!empty($unique_values)) {
                            echo '<select class="codecatalogo-filter" data-filter-field="' . esc_attr($field->id) . '">';
                            echo '<option value="">' . esc_html__('Todos','catalogo70free') . '</option>';
                            foreach ($unique_values as $value) {
                                echo '<option value="' . esc_attr($value) . '">' . esc_html($value) . '</option>';
                            }
                            echo '</select>';
                        }
                    }
            
                    echo '</div>';
                }
        
        echo '<button type="button" class="codecatalogo-clear-filters">' . esc_html__('Limpiar filtros','catalogo70free') . '</button>';
        echo '</div>';
    }
    
    /**
     * Obtener valores únicos de un campo
     */
    private function get_unique_field_values($field_id) {
        global $wpdb;
        $values_table = $wpdb->prefix . 'codecatalogo_field_values';
        
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT field_value FROM {$values_table} 
            WHERE field_id = %d AND field_value != '' 
            ORDER BY field_value ASC",
            $field_id
        ));
        
        return $values;
    }
    
    /**
     * Renderizar productos
     */
    private function render_products($args) {
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        
        $query_args = array(
            'post_type' => 'codecatalogo_product',
            'posts_per_page' => intval($args['per_page']),
            'paged' => $paged,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Filtro de categoría
        if (!empty($args['category'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'codecatalogo_cat',
                    'field' => 'term_id',
                    'terms' => intval($args['category']),
                ),
            );
        }
        
        $query = new WP_Query($query_args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_product_card(get_the_ID());
            }
            wp_reset_postdata();
        } else {
            echo '<div class="codecatalogo-no-results">';
            echo '<p>' . esc_html__('No se encontraron productos.','catalogo70free') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Renderizar tarjeta de producto
     */
    public function render_product_card($product_id) {
        $fields = $this->field_manager->get_product_fields($product_id, true);
        
        
        include CODECATALOGO_PATH . 'public/templates/product-card.php';
    }
    
    /**
     * Renderizar paginación
     */
    private function render_pagination($args) {
        global $wp_query;
        
        if ($wp_query->max_num_pages <= 1) {
            return;
        }
        
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        
                echo '<nav class="codecatalogo-pagination-nav">';
        
        $pagination_links = paginate_links(array(
            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
            'format' => '?paged=%#%',
            'current' => max(1, $paged),
            'total' => $wp_query->max_num_pages,
            'prev_text' => '&laquo; ' . esc_html__('Anterior','catalogo70free'),
            'next_text' => esc_html__('Siguiente','catalogo70free') . ' &raquo;',
            'mid_size' => 2,
        ));
                echo wp_kses_post($pagination_links);
    }
    
    /**
     * Renderizar solo el modal de contacto (para usar fuera del catálogo)
     */
    
        /**
     * Renderizar modal de contacto - CON CAMPO HIDDEN product_name
     */
}
