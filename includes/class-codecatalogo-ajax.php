<?php
/**
 * AJAX - Versión FREE
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_AJAX {
    
    private $field_manager;
    
    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
        
        add_action('wp_ajax_codecatalogo_create_field', array($this, 'create_field'));
        add_action('wp_ajax_codecatalogo_delete_field', array($this, 'delete_field'));
        add_action('wp_ajax_codecatalogo_reorder_fields', array($this, 'reorder_fields'));
        add_action('wp_ajax_codecatalogo_filter_products', array($this, 'filter_products'));
        add_action('wp_ajax_nopriv_codecatalogo_filter_products', array($this, 'filter_products'));
        add_action('wp_ajax_codecatalogo_toggle_field', array($this, 'toggle_field'));
    }

    public function toggle_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error(); }
        $field_id = intval($_POST['field_id']);
        $field = sanitize_key($_POST['field']);
        $value = intval($_POST['value']);
        $allowed = array('show_in_card', 'show_in_filter', 'is_seo_relevant', 'is_required');
        if (!in_array($field, $allowed)) { wp_send_json_error(); }
        $result = $this->field_manager->update_field($field_id, array($field => $value));
        $result ? wp_send_json_success() : wp_send_json_error();
    }
    
    public function create_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes.','catalogo70free')));
        }
        if (!$this->field_manager->can_create_more_fields()) {
            $max_fields = intval(CodeCatalogo_Field_Manager::FREE_MAX_FIELDS);
            /* translators: %d: maximum number of free fields */
            wp_send_json_error(array('message' => sprintf(__('Límite de %1$d campos alcanzado.','catalogo70free'), $max_fields)));
        }
        $allowed_types = array('text', 'number', 'textarea', 'file', 'url');
        if (!in_array($_POST['field_type'], $allowed_types)) {
            wp_send_json_error(array('message' => __('Tipo de campo no disponible.','catalogo70free')));
        }
        $field_data = array(
            'field_name' => sanitize_key($_POST['field_name']),
            'field_label' => sanitize_text_field($_POST['field_label']),
            'field_type' => sanitize_text_field($_POST['field_type']),
            'field_icon' => sanitize_text_field($_POST['field_icon'] ?? ''),
            'field_group' => sanitize_text_field($_POST['field_group'] ?? ''),
            'field_unit' => sanitize_text_field($_POST['field_unit'] ?? ''),
            'show_in_card' => isset($_POST['show_in_card']) ? 1 : 0,
            'show_in_filter' => isset($_POST['show_in_filter']) ? 1 : 0,
            'is_seo_relevant' => isset($_POST['is_seo_relevant']) ? 1 : 0,
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
        );
        if (empty($field_data['field_name']) || empty($field_data['field_label'])) {
            wp_send_json_error(array('message' => __('Todos los campos son requeridos.','catalogo70free')));
        }
        $field_id = $this->field_manager->create_field($field_data);
        if ($field_id) {
            wp_send_json_success(array('message' => __('Campo creado.','catalogo70free'), 'field_id' => $field_id));
        } else {
            wp_send_json_error(array('message' => __('Error al crear.','catalogo70free')));
        }
    }
    
    public function delete_field() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => __('No tienes permisos.','catalogo70free'))); }
        $field_id = intval($_POST['field_id']);
        $result = $this->field_manager->delete_field($field_id);
        $result ? wp_send_json_success(array('message' => __('Campo eliminado.','catalogo70free'))) : wp_send_json_error(array('message' => __('Error al eliminar.','catalogo70free')));
    }
    
    public function reorder_fields() {
        check_ajax_referer('codecatalogo_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => __('No tienes permisos.','catalogo70free'))); }
        $order = $_POST['order'] ?? array();
        if (empty($order) || !is_array($order)) { wp_send_json_error(array('message' => __('Orden inválido.','catalogo70free'))); }
        global $wpdb;
        $table_name = $wpdb->prefix . 'codecatalogo_fields';
        foreach ($order as $index => $field_id) {
            $wpdb->update($table_name, array('field_order' => $index), array('id' => intval($field_id)), array('%d'), array('%d'));
        }
        wp_cache_delete('codecatalogo_all_fields', 'codecatalogo');
        wp_cache_delete('codecatalogo_filter_fields', 'codecatalogo');
        wp_send_json_success(array('message' => __('Orden actualizado.','catalogo70free')));
    }
    
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
        if (!empty($search)) { $args['s'] = $search; }
        if ($category > 0) {
            $args['tax_query'] = array(array('taxonomy' => 'codecatalogo_cat', 'field' => 'term_id', 'terms' => $category));
        }
        if (!empty($filters) && is_array($filters)) {
            global $wpdb;
            $values_table = $wpdb->prefix . 'codecatalogo_field_values';
            $product_ids = null;
            foreach ($filters as $field_id => $field_value) {
                if (empty($field_value)) continue;
                $field_id = intval($field_id);
                $field_value = sanitize_text_field($field_value);
                $matching_ids = $wpdb->get_col($wpdb->prepare("SELECT product_id FROM {$values_table} WHERE field_id = %d AND field_value LIKE %s", $field_id, '%' . $wpdb->esc_like($field_value) . '%'));
                $product_ids = ($product_ids === null) ? $matching_ids : array_intersect($product_ids, $matching_ids);
            }
            if (is_array($product_ids)) {
                if (empty($product_ids)) { $product_ids = array(0); }
                $args['post__in'] = $product_ids;
            }
        }
        $query = new WP_Query($args);
        $products = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) { $query->the_post();
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
        wp_send_json_success(array('products' => $products, 'total' => $query->found_posts, 'max_pages' => $query->max_num_pages, 'current_page' => $page));
    }
    
    private function get_product_card_fields($product_id) {
        $fields = $this->field_manager->get_product_fields($product_id, true);
        $card_fields = array();
        foreach ($fields as $field) {
            if (!empty($field->field_value)) {
                $card_fields[] = array('label' => $field->field_label, 'value' => $field->field_value, 'icon' => $field->field_icon);
            }
        }
        return $card_fields;
    }
}

new CodeCatalogo_AJAX();
