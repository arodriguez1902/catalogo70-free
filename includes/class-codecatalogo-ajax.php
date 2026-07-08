<?php
/**
 * Manejador de AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_AJAX {
    
    private $field_manager;
    
    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
        
        // Admin AJAX
        add_action('wp_ajax_codecatalogo_create_field', array($this, 'create_field'));
        add_action('wp_ajax_codecatalogo_update_field', array($this, 'update_field'));
        add_action('wp_ajax_codecatalogo_delete_field', array($this, 'delete_field'));
        add_action('wp_ajax_codecatalogo_reorder_fields', array($this, 'reorder_fields'));
        
                // Public AJAX
        add_action('wp_ajax_codecatalogo_filter_products', array($this, 'filter_products'));
        add_action('wp_ajax_nopriv_codecatalogo_filter_products', array($this, 'filter_products'));
        add_action('wp_ajax_codecatalogo_search_products', array($this, 'search_products'));
        add_action('wp_ajax_nopriv_codecatalogo_search_products', array($this, 'search_products'));

                // Admin AJAX misc
        add_action('wp_ajax_codecatalogo_dismiss_update_notice', array($this, 'dismiss_update_notice'));
        add_action('wp_ajax_codecatalogo_toggle_field', array($this, 'toggle_field'));
    }

    /**
     * Toggle field checkbox inline
     */
    public function toggle_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        $field_id = intval($_POST['field_id']);
        $field = sanitize_key($_POST['field']);
        $value = intval($_POST['value']);
        
        $allowed_fields = array('show_in_card', 'show_in_filter', 'is_seo_relevant', 'is_required');
        if (!in_array($field, $allowed_fields)) {
            wp_send_json_error();
        }
        
        $result = $this->field_manager->update_field($field_id, array($field => $value));
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Descartar aviso de actualización
     */
    public function dismiss_update_notice() {
        update_option('codecatalogo_update_notice_dismissed', true);
        wp_send_json_success();
    }
    
    /**
     * Crear campo personalizado
     */
    public function create_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes.', 'catalogo70')));
        }

        // Verificar límite de campos en versión FREE
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-license.php';
        $license = new CodeCatalogo_License();

        if (!$license->can_create_field()) {
                        wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %d: Maximum number of fields allowed in the free version */
                    __('Has alcanzado el límite de %d campos en la versión Free. Actualiza a Premium para campos ilimitados.', 'catalogo70'),
                    CodeCatalogo_License::FREE_LIMIT_FIELDS
                )
            ));
        }

                $field_data = array(
            'field_name'       => sanitize_key($_POST['field_name']),
            'field_label'      => sanitize_text_field($_POST['field_label']),
            'field_type'       => sanitize_text_field($_POST['field_type']),
            'field_icon'       => sanitize_text_field($_POST['field_icon'] ?? ''),
            'field_group'      => sanitize_text_field($_POST['field_group'] ?? ''),
            'field_unit'       => sanitize_text_field($_POST['field_unit'] ?? ''),
            'show_in_card'     => isset($_POST['show_in_card']) ? 1 : 0,
            'show_in_filter'   => isset($_POST['show_in_filter']) ? 1 : 0,
            'is_seo_relevant'  => isset($_POST['is_seo_relevant']) ? 1 : 0,
            'is_required'      => isset($_POST['is_required']) ? 1 : 0,
        );

        // Validar
        if (empty($field_data['field_name']) || empty($field_data['field_label'])) {
            wp_send_json_error(array('message' => __('El nombre y etiqueta del campo son requeridos.', 'catalogo70')));
        }

        // En versión FREE, solo permitir tipos básicos
        if (!$license->is_premium()) {
            $allowed_types = array('text', 'number', 'textarea');
            if (!in_array($field_data['field_type'], $allowed_types)) {
                wp_send_json_error(array(
                    'message' => __('Este tipo de campo solo está disponible en la versión Premium.', 'catalogo70')
                ));
            }
        }

        // Opciones de select
        if ($field_data['field_type'] === 'select' && !empty($_POST['field_options'])) {
            $field_data['field_options'] = $_POST['field_options'];
        }

        $field_id = $this->field_manager->create_field($field_data);

        if ($field_id) {
            wp_send_json_success(array(
                'message' => __('Campo creado exitosamente.', 'catalogo70'),
                'field_id' => $field_id,
            ));
        } else {
            wp_send_json_error(array('message' => __('Error al crear el campo.', 'catalogo70')));
        }
    }
    
    /**
     * Actualizar campo
     */
    public function update_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes.', 'catalogo70')));
        }
        
        $field_id = intval($_POST['field_id']);
        
                $field_data = array(
            'field_label'      => sanitize_text_field($_POST['field_label']),
            'field_type'       => sanitize_text_field($_POST['field_type']),
            'field_icon'       => sanitize_text_field($_POST['field_icon'] ?? ''),
            'field_group'      => sanitize_text_field($_POST['field_group'] ?? ''),
            'field_unit'       => sanitize_text_field($_POST['field_unit'] ?? ''),
            'show_in_card'     => isset($_POST['show_in_card']) ? 1 : 0,
            'show_in_filter'   => isset($_POST['show_in_filter']) ? 1 : 0,
            'is_seo_relevant'  => isset($_POST['is_seo_relevant']) ? 1 : 0,
            'is_required'      => isset($_POST['is_required']) ? 1 : 0,
        );
        
        if ($field_data['field_type'] === 'select' && !empty($_POST['field_options'])) {
            $field_data['field_options'] = $_POST['field_options'];
        }
        
        $result = $this->field_manager->update_field($field_id, $field_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Campo actualizado exitosamente.', 'catalogo70')));
        } else {
            wp_send_json_error(array('message' => __('Error al actualizar el campo.', 'catalogo70')));
        }
    }
    
    /**
     * Eliminar campo
     */
    public function delete_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes.', 'catalogo70')));
        }
        
        $field_id = intval($_POST['field_id']);
        
        $result = $this->field_manager->delete_field($field_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Campo eliminado exitosamente.', 'catalogo70')));
        } else {
            wp_send_json_error(array('message' => __('Error al eliminar el campo.', 'catalogo70')));
        }
    }
    
    /**
     * Reordenar campos
     */
    public function reorder_fields() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes.', 'catalogo70')));
        }
        
        $order = $_POST['order'] ?? array();
        
        if (empty($order) || !is_array($order)) {
            wp_send_json_error(array('message' => __('Orden inválido.', 'catalogo70')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'codecatalogo_fields';
        
                foreach ($order as $index => $field_id) {
            $wpdb->update(
                $table_name,
                array('field_order' => $index),
                array('id' => intval($field_id)),
                array('%d'),
                array('%d')
            );
        }
        
        // Limpiar toda la caché relacionada
        wp_cache_delete('codecatalogo_all_fields', 'codecatalogo');
        wp_cache_delete('codecatalogo_filter_fields', 'codecatalogo');
        
        // Limpiar también caché de productos (por si hay resultados cacheados)
        global $wpdb;
        $values_table = $wpdb->prefix . 'codecatalogo_field_values';
        $product_ids = $wpdb->get_col("SELECT DISTINCT product_id FROM {$values_table}");
        foreach ($product_ids as $pid) {
            wp_cache_delete('codecatalogo_product_fields_' . $pid . '_all', 'codecatalogo');
            wp_cache_delete('codecatalogo_product_fields_' . $pid . '_card', 'codecatalogo');
        }
        
        wp_send_json_success(array('message' => __('Orden actualizado exitosamente.', 'catalogo70')));
    }
    
    /**
     * Filtrar productos
     */
    public function filter_products() {
        $filters = $_POST['filters'] ?? array();
        $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = get_option('codecatalogo_products_per_page', 12);
        
        $args = array(
            'post_type' => 'codecatalogo_product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
        );
        
        // Búsqueda
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        // Categoría
        if ($category > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'codecatalogo_cat',
                    'field' => 'term_id',
                    'terms' => $category,
                ),
            );
        }
        
        // Meta query para filtros de campos personalizados
        if (!empty($filters) && is_array($filters)) {
            global $wpdb;
            $values_table = $wpdb->prefix . 'codecatalogo_field_values';
            
            $product_ids = null;
            
            foreach ($filters as $field_id => $field_value) {
                if (empty($field_value)) {
                    continue;
                }
                
                $field_id = intval($field_id);
                $field_value = sanitize_text_field($field_value);
                
                $matching_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT product_id FROM {$values_table} 
                    WHERE field_id = %d AND field_value LIKE %s",
                    $field_id,
                    '%' . $wpdb->esc_like($field_value) . '%'
                ));
                
                if ($product_ids === null) {
                    $product_ids = $matching_ids;
                } else {
                    $product_ids = array_intersect($product_ids, $matching_ids);
                }
            }
            
            if (is_array($product_ids)) {
                if (empty($product_ids)) {
                    $product_ids = array(0);
                }
                $args['post__in'] = $product_ids;
            }
        }
        
        $query = new WP_Query($args);
        
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                $product_id = get_the_ID();
                
                $products[] = array(
                    'id' => $product_id,
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'permalink' => get_permalink(),
                    'thumbnail' => get_the_post_thumbnail_url($product_id, 'medium'),
                    'fields' => $this->get_product_card_fields($product_id),
                );
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(array(
            'products' => $products,
            'total' => $query->found_posts,
            'max_pages' => $query->max_num_pages,
            'current_page' => $page,
        ));
    }
    
    /**
     * Buscar productos
     */
    public function search_products() {
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_error(array('message' => __('Término de búsqueda vacío.', 'catalogo70')));
        }
        
        $args = array(
            'post_type' => 'codecatalogo_product',
            'posts_per_page' => 10,
            's' => $search,
            'post_status' => 'publish',
        );
        
        $query = new WP_Query($args);
        
        $results = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                $results[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'),
                );
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * Obtener campos para la tarjeta del producto
     */
    private function get_product_card_fields($product_id) {
        $fields = $this->field_manager->get_product_fields($product_id, true);
        
        $card_fields = array();
        
        foreach ($fields as $field) {
            if (!empty($field->field_value)) {
                $card_fields[] = array(
                    'label' => $field->field_label,
                    'value' => $field->field_value,
                    'icon' => $field->field_icon,
                );
            }
        }
        
        return $card_fields;
    }
}

// Inicializar
new CodeCatalogo_AJAX();