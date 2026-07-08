<?php
/**
 * Script de desinstalación
 * Se ejecuta cuando el plugin se desinstala (no desactiva)
 */

// Si uninstall no es llamado desde WordPress, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verificar permisos
if (!current_user_can('activate_plugins')) {
    return;
}

global $wpdb;

// ===================================
// Eliminar Custom Post Type y Posts
// ===================================

$posts = get_posts(array(
    'post_type' => 'codecatalogo_product',
    'numberposts' => -1,
    'post_status' => 'any',
));

foreach ($posts as $post) {
    // Eliminar permanentemente el post y sus meta
    wp_delete_post($post->ID, true);
}

// ===================================
// Eliminar Taxonomías y Términos
// ===================================

$taxonomies = array('codecatalogo_cat', 'codecatalogo_tag');

foreach ($taxonomies as $taxonomy) {
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ));
    
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
    }
}

// ===================================
// Eliminar Tablas Personalizadas
// ===================================

$table_fields = $wpdb->prefix . 'codecatalogo_fields';
$table_values = $wpdb->prefix . 'codecatalogo_field_values';
$table_ctas = $wpdb->prefix . 'codecatalogo_ctas';

$wpdb->query("DROP TABLE IF EXISTS $table_values");
$wpdb->query("DROP TABLE IF EXISTS $table_ctas");
$wpdb->query("DROP TABLE IF EXISTS $table_fields");

// ===================================
// Eliminar Opciones
// ===================================

$options = array(
    'codecatalogo_db_version',
    'codecatalogo_activation_date',
    'codecatalogo_catalog_slug',
    'codecatalogo_product_slug',
    'codecatalogo_products_per_page',
    'codecatalogo_catalog_layout',
    'codecatalogo_enable_seo',
    'codecatalogo_enable_schema',
    'codecatalogo_default_whatsapp',
    'codecatalogo_default_form_email',
    'codecatalogo_card_style',
    'codecatalogo_primary_color',
    'codecatalogo_secondary_color',
);

foreach ($options as $option) {
    delete_option($option);
}

// ===================================
// Eliminar Transients
// ===================================

$wpdb->query(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_codecatalogo_%' 
    OR option_name LIKE '_transient_timeout_codecatalogo_%'"
);

// ===================================
// Limpiar Caché
// ===================================

wp_cache_flush();

// ===================================
// Limpiar Rewrite Rules
// ===================================

flush_rewrite_rules();

// ===================================
// Log de desinstalación (opcional)
// ===================================

$upload_dir = wp_upload_dir();
$log_file = $upload_dir['basedir'] . '/codecatalogo-uninstall.log';
$log_message = sprintf(
    "[%s] CodeCatalogo Pro desinstalado. Usuario: %s\n",
    current_time('mysql'),
    wp_get_current_user()->user_login
);

// Usar WP_Filesystem para escribir
if (!function_exists('WP_Filesystem')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();
global $wp_filesystem;
$wp_filesystem->put_contents($log_file, $log_message, FILE_APPEND);

// ===================================
// Mensaje final
// ===================================

// Nota: Los mensajes no se pueden mostrar en uninstall.php
// pero puedes registrar la acción para debugging

error_log('CodeCatalogo Pro: Plugin desinstalado completamente');