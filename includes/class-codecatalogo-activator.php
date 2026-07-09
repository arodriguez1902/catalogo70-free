<?php
/**
 * Activador - Versión FREE
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Activator {
    
    public static function activate() {
        self::create_tables();
        self::create_default_fields();
        self::set_default_options();
        
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-cpt.php';
        $cpt = new CodeCatalogo_CPT();
        $cpt->register_post_type();
        $cpt->register_taxonomies();
        
        flush_rewrite_rules();
        update_option('codecatalogo_db_version', CODECATALOGO_DB_VERSION);
    }
    
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_fields = $wpdb->prefix . 'codecatalogo_fields';
        $table_values = $wpdb->prefix . 'codecatalogo_field_values';
        
        $sql_fields = "CREATE TABLE IF NOT EXISTS $table_fields (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            field_name VARCHAR(100) NOT NULL,
            field_label VARCHAR(200) NOT NULL,
            field_type VARCHAR(50) NOT NULL DEFAULT 'text',
            field_options TEXT NULL,
            field_order INT(11) DEFAULT 0,
            field_icon VARCHAR(100) NULL,
            field_group VARCHAR(100) NULL DEFAULT '',
            field_unit VARCHAR(50) NULL DEFAULT '',
            show_in_card TINYINT(1) DEFAULT 0,
            show_in_filter TINYINT(1) DEFAULT 0,
            is_seo_relevant TINYINT(1) DEFAULT 0,
            is_required TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_field_name (field_name),
            INDEX idx_field_order (field_order),
            INDEX idx_show_in_card (show_in_card),
            INDEX idx_show_in_filter (show_in_filter)
        ) $charset_collate;";
        
        $sql_values = "CREATE TABLE IF NOT EXISTS $table_values (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            field_id BIGINT(20) UNSIGNED NOT NULL,
            field_value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product_field (product_id, field_id),
            INDEX idx_product (product_id),
            INDEX idx_field (field_id),
            FOREIGN KEY (field_id) REFERENCES $table_fields(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_fields);
        dbDelta($sql_values);
    }
    
    private static function create_default_fields() {
        global $wpdb;
        $table_fields = $wpdb->prefix . 'codecatalogo_fields';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields");
        if ($count > 0) return;
        
        $defaults = array(
            array('field_name' => 'potencia', 'field_label' => 'Potencia', 'field_type' => 'text', 'field_order' => 1, 'show_in_card' => 1, 'show_in_filter' => 1, 'is_seo_relevant' => 1),
            array('field_name' => 'voltaje', 'field_label' => 'Voltaje', 'field_type' => 'text', 'field_order' => 2, 'show_in_card' => 1, 'show_in_filter' => 1, 'is_seo_relevant' => 1),
            array('field_name' => 'marca', 'field_label' => 'Marca', 'field_type' => 'text', 'field_order' => 3, 'show_in_card' => 1, 'show_in_filter' => 1, 'is_seo_relevant' => 1),
            array('field_name' => 'modelo', 'field_label' => 'Modelo', 'field_type' => 'text', 'field_order' => 4, 'show_in_card' => 1, 'show_in_filter' => 0, 'is_seo_relevant' => 1),
        );
        foreach ($defaults as $field) { $wpdb->insert($table_fields, $field); }
    }

    private static function set_default_options() {
        $defaults = array(
            'codecatalogo_catalog_slug' => 'catalogo',
            'codecatalogo_product_slug' => 'producto',
            'codecatalogo_products_per_page' => 12,
            'codecatalogo_enable_seo' => 1,
            'codecatalogo_enable_schema' => 1,
            'codecatalogo_primary_color' => '#0073aa',
        );
        foreach ($defaults as $name => $value) {
            if (get_option($name) === false) { add_option($name, $value); }
        }
    }
}
