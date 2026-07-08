<?php
/**
 * Activador del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Activator {
    
    /**
     * Ejecutar al activar el plugin
     */
    public static function activate() {
        self::create_tables();
        self::create_default_fields();
        self::set_default_options();
        
        // Registrar CPT para flush rewrite rules
        $cpt = new CodeCatalogo_CPT();
        $cpt->register_post_type();
        $cpt->register_taxonomies();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Guardar versión de la base de datos
        update_option('codecatalogo_db_version', CODECATALOGO_DB_VERSION);
        update_option('codecatalogo_activation_date', current_time('mysql'));
    }
    
        /**
     * Crear tablas personalizadas
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_fields = $wpdb->prefix . 'codecatalogo_fields';
        $table_values = $wpdb->prefix . 'codecatalogo_field_values';
        $table_ctas = $wpdb->prefix . 'codecatalogo_ctas';
        
        // Tabla de campos personalizados
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
        
        // Tabla de valores de campos
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
        
        // Tabla de CTAs
        $sql_ctas = "CREATE TABLE IF NOT EXISTS $table_ctas (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            cta_type VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
            whatsapp_number VARCHAR(20) NULL,
            whatsapp_message TEXT NULL,
            form_email VARCHAR(100) NULL,
            form_subject VARCHAR(200) NULL,
            form_cc VARCHAR(255) NULL,
            cta_text VARCHAR(100) NULL,
            cta_button_color VARCHAR(20) DEFAULT '#25D366',
            cta_position VARCHAR(20) DEFAULT 'bottom',
            show_on_card TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_product (product_id),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_fields);
        dbDelta($sql_values);
        dbDelta($sql_ctas);
    }
    
    /**
     * Crear campos por defecto
     */
    private static function create_default_fields() {
        global $wpdb;
        $table_fields = $wpdb->prefix . 'codecatalogo_fields';
        
        // Verificar si ya existen campos
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields");
        
        if ($count > 0) {
            return; // Ya existen campos
        }
        
                // Campos predeterminados
        $default_fields = array(
            array(
                'field_name' => 'potencia',
                'field_label' => 'Potencia',
                'field_type' => 'text',
                'field_order' => 1,
                'field_icon' => '',
                'show_in_card' => 1,
                'show_in_filter' => 1,
                'is_seo_relevant' => 1,
            ),
            array(
                'field_name' => 'voltaje',
                'field_label' => 'Voltaje',
                'field_type' => 'text',
                'field_order' => 2,
                'field_icon' => '',
                'show_in_card' => 1,
                'show_in_filter' => 1,
                'is_seo_relevant' => 1,
            ),
            array(
                'field_name' => 'marca',
                'field_label' => 'Marca',
                'field_type' => 'text',
                'field_order' => 3,
                'field_icon' => '',
                'show_in_card' => 1,
                'show_in_filter' => 1,
                'is_seo_relevant' => 1,
            ),
            array(
                'field_name' => 'ficha_tecnica',
                'field_label' => 'Ficha Técnica (PDF)',
                'field_type' => 'file',
                'field_order' => 4,
                'field_icon' => '',
                'show_in_card' => 1,
                'show_in_filter' => 0,
                'is_seo_relevant' => 0,
            ),
            array(
                'field_name' => 'modelo',
                'field_label' => 'Modelo',
                'field_type' => 'text',
                'field_order' => 5,
                'field_icon' => '',
                'show_in_card' => 1,
                'show_in_filter' => 0,
                'is_seo_relevant' => 1,
            ),
        );
        
        foreach ($default_fields as $field) {
            $wpdb->insert($table_fields, $field);
        }
    }
    
    /**
     * Crear tabla de contactos (se puede llamar en admin_init)
     */
    public static function create_contacts_table() {
        global $wpdb;
        $table_contacts = $wpdb->prefix . 'codecatalogo_contacts';
        
        // Solo crear si no existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_contacts}'") !== $table_contacts) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_contacts (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT(20) UNSIGNED DEFAULT 0,
                product_name VARCHAR(255) DEFAULT '',
                contact_name VARCHAR(100) NOT NULL,
                contact_email VARCHAR(100) NOT NULL,
                contact_phone VARCHAR(50) DEFAULT '',
                contact_message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product (product_id),
                INDEX idx_read (is_read),
                INDEX idx_date (created_at)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Establecer opciones por defecto
     */
    private static function set_default_options() {
        $default_options = array(
            'codecatalogo_catalog_slug' => 'catalogo',
            'codecatalogo_product_slug' => 'producto',
            'codecatalogo_products_per_page' => 12,
            'codecatalogo_catalog_layout' => 'grid',
            'codecatalogo_enable_seo' => 1,
            'codecatalogo_enable_schema' => 1,
            'codecatalogo_default_whatsapp' => '',
            'codecatalogo_default_form_email' => get_option('admin_email'),
            'codecatalogo_card_style' => 'modern',
            'codecatalogo_primary_color' => '#0073aa',
            'codecatalogo_secondary_color' => '#25D366',
        );
        
        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }
}