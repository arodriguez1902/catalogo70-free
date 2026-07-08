<?php
/**
 * Exportador de productos a CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Exporter {
    
    private $field_manager;
    
    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
    }
    
    /**
     * Exportar productos a CSV
     */
    public function export_to_csv($settings = array()) {
        $defaults = array(
            'delimiter' => ',',
            'encoding' => 'UTF-8',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Obtener todos los productos
        $products = get_posts(array(
            'post_type' => 'codecatalogo_product',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        if (empty($products)) {
            return array(
                'success' => false,
                'message' => esc_html__('No hay productos para exportar', 'catalogo70'),
            );
        }
        
        // Preparar CSV
        $csv_data = array();
        
        // Headers
        $headers = $this->get_csv_headers();
        $csv_data[] = $headers;
        
        // Datos de productos
        foreach ($products as $product) {
            $csv_data[] = $this->get_product_data($product, $headers);
        }

        // Generar archivo CSV
        $filename = 'codecatalogo-productos-' . gmdate('Y-m-d-H-i-s') . '.csv';
        $file_path = $this->generate_csv_file($csv_data, $filename, $settings);

        if (!$file_path || is_wp_error($file_path)) {
            return array(
                'success' => false,
                'message' => is_wp_error($file_path) ? $file_path->get_error_message() : esc_html__('No se pudo crear el archivo CSV. Verifica los permisos de escritura en la carpeta wp-content/uploads/', 'catalogo70'),
            );
        }

        return array(
            'success' => true,
            'file_path' => $file_path,
            'filename' => $filename,
        );
    }
    
    /**
     * Generar CSV modelo (solo headers)
     */
    public function generate_template() {
        $headers = $this->get_csv_headers();

        $csv_data = array($headers);

        $filename = 'codecatalogo-plantilla.csv';
        $result = $this->generate_csv_file($csv_data, $filename, array('delimiter' => ',', 'encoding' => 'UTF-8'));

        if (!$result || is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => is_wp_error($result) ? $result->get_error_message() : esc_html__('No se pudo crear el archivo CSV. Verifica los permisos de escritura en la carpeta wp-content/uploads/', 'catalogo70'),
            );
        }

        return array(
            'success' => true,
            'file_path' => $result,
            'filename' => $filename,
        );
    }
    
    /**
     * Obtener headers del CSV
     */
    private function get_csv_headers() {
                $headers = array(
            'titulo',
            'extracto',
            'contenido',
            'categoria',
            'etiquetas',
            'imagen_destacada',
            'galeria_imagenes',
            'estado',
        );
        
                // Agregar campos personalizados usando field_label (más legible)
        $custom_fields = $this->field_manager->get_all_fields();

        foreach ($custom_fields as $field) {
            $headers[] = $field->field_label;
        }
        
        return $headers;
    }
    
    /**
     * Obtener datos de un producto
     */
    private function get_product_data($product, $headers) {
        $data = array();
        
        foreach ($headers as $header) {
            switch ($header) {
                case 'titulo':
                    $data[] = $product->post_title;
                    break;
                    
                case 'extracto':
                    $data[] = wp_strip_all_tags($product->post_excerpt);
                    break;

                                case 'contenido':
                                    // Exportar el contenido manteniendo HTML y comentarios de Gutenberg
                                    $content = $product->post_content;
                                    // Limpiar espacios múltiples pero mantener HTML y bloques Gutenberg
                                    $content = preg_replace('/\s+/', ' ', $content);
                                    $data[] = trim($content);
                                    break;
                    
                case 'categoria':
                    $categories = wp_get_post_terms($product->ID, 'codecatalogo_cat', array('fields' => 'names'));
                    $data[] = !empty($categories) ? implode(';', $categories) : '';
                    break;
                    
                case 'etiquetas':
                    $tags = wp_get_post_terms($product->ID, 'codecatalogo_tag', array('fields' => 'names'));
                    $data[] = !empty($tags) ? implode(';', $tags) : '';
                    break;
                    
                                case 'imagen_destacada':
                    $thumbnail_id = get_post_thumbnail_id($product->ID);
                    if ($thumbnail_id) {
                        // URL completa de la imagen (funciona al importar desde otro sitio)
                        $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                        $data[] = $image_url ? $image_url : '';
                    } else {
                        $data[] = '';
                    }
                    break;

                                case 'galeria_imagenes':
                    $gallery_ids = get_post_meta($product->ID, '_codecatalogo_gallery_ids', true);
                    $gallery_urls = array();
                    if (!empty($gallery_ids)) {
                        $ids = explode(',', $gallery_ids);
                        foreach ($ids as $attach_id) {
                            $attach_id = intval(trim($attach_id));
                            if ($attach_id) {
                                $gallery_url = wp_get_attachment_image_url($attach_id, 'full');
                                if ($gallery_url) {
                                    $gallery_urls[] = $gallery_url;
                                }
                            }
                        }
                    }
                    $data[] = !empty($gallery_urls) ? implode(';', $gallery_urls) : '';
                    break;
                    
                case 'estado':
                    $data[] = $product->post_status;
                    break;
                    
                                default:
                    // Campo personalizado - buscar por field_label
                    $field_value = $this->get_custom_field_value_by_label($product->ID, $header);
                    $data[] = $field_value;
                    break;
            }
        }
        
        return $data;
    }
    
        /**
     * Obtener valor de campo personalizado por field_label
     */
    private function get_custom_field_value_by_label($product_id, $field_label) {
        global $wpdb;
        
        $fields_table = $wpdb->prefix . 'codecatalogo_fields';
        $values_table = $wpdb->prefix . 'codecatalogo_field_values';
        
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT fv.field_value 
            FROM {$values_table} fv
            INNER JOIN {$fields_table} f ON fv.field_id = f.id
            WHERE fv.product_id = %d AND f.field_label = %s",
            $product_id,
            $field_label
        ));
        
        return $value ? $value : '';
    }

    /**
     * Obtener valor de campo personalizado por field_name (compatibilidad)
     */
    private function get_custom_field_value($product_id, $field_name) {
        global $wpdb;
        
        $fields_table = $wpdb->prefix . 'codecatalogo_fields';
        $values_table = $wpdb->prefix . 'codecatalogo_field_values';
        
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT fv.field_value 
            FROM {$values_table} fv
            INNER JOIN {$fields_table} f ON fv.field_id = f.id
            WHERE fv.product_id = %d AND f.field_name = %s",
            $product_id,
            $field_name
        ));
        
        return $value ? $value : '';
    }
    
    /**
     * Generar archivo CSV
     */
        private function generate_csv_file($csv_data, $filename, $settings) {
        $upload_dir = wp_upload_dir();

        // Verificar si hay error en el directorio de uploads
        if (!empty($upload_dir['error'])) {
            return new WP_Error('upload_dir_error', $upload_dir['error']);
        }

        $file_path = $upload_dir['path'] . '/' . $filename;

        // Usar WP_Filesystem para escribir el archivo
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        // Construir contenido CSV
        $csv_content = '';

        // Escribir BOM para UTF-8 si es necesario
        if ($settings['encoding'] === 'UTF-8') {
            $csv_content .= chr(0xEF).chr(0xBB).chr(0xBF);
        }

        // Abrir archivo temporal para usar fputcsv
        $temp_handle = tmpfile();

        foreach ($csv_data as $row) {
            // Convertir encoding si es necesario
            if ($settings['encoding'] !== 'UTF-8') {
                $row = array_map(function($value) use ($settings) {
                    return mb_convert_encoding($value, $settings['encoding'], 'UTF-8');
                }, $row);
            }

            fputcsv($temp_handle, $row, $settings['delimiter']);
        }

        // Leer el contenido temporal
        rewind($temp_handle);
        $csv_content .= stream_get_contents($temp_handle);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($temp_handle);

        // Escribir el archivo con WP_Filesystem
        if (!$wp_filesystem->put_contents($file_path, $csv_content, FS_CHMOD_FILE)) {
            return new WP_Error(
                'file_creation_error',
                sprintf(
                    /* translators: %s: Name of file */
                    esc_html__('No se puede crear el archivo en: %s. Verifica los permisos de escritura.', 'catalogo70'),
                    $upload_dir['path']
                )
            );
        }

        return $file_path;
    }
}