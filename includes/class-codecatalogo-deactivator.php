<?php
/**
 * Desactivador del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Deactivator {
    
    /**
     * Ejecutar al desactivar el plugin
     */
    public static function deactivate() {
        // Limpiar rewrite rules
        flush_rewrite_rules();
        
        // Limpiar caché
        self::clear_cache();
        
        // Limpiar transients
        self::clear_transients();
    }
    
    /**
     * Limpiar caché
     */
    private static function clear_cache() {
        wp_cache_flush();
        
        // Limpiar grupos específicos
        wp_cache_delete('codecatalogo_all_fields', 'codecatalogo');
        wp_cache_delete('codecatalogo_settings', 'codecatalogo');
    }
    
    /**
     * Limpiar transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_codecatalogo_%' 
            OR option_name LIKE '_transient_timeout_codecatalogo_%'"
        );
    }
}