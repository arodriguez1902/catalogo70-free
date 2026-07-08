<?php
/**
 * Importador de productos desde CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Importer {
    
    private $field_manager;
    private $results = array(
        'imported' => 0,
        'updated' => 0,
        'errors' => array(),
        'warnings' => array(),
    );
    
    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
    }
    
    /**
     * Procesar importación desde CSV
     */
    public function import_from_csv($file_path, $settings = array()) {
        $defaults = array(
            'delimiter' => ',',
            'encoding' => 'UTF-8',
            'update_existing' => true,
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Validar archivo
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => esc_html__('Archivo no encontrado', 'catalogo70'),
            );
        }
        
        // Leer CSV
        $csv_data = $this->read_csv($file_path, $settings['delimiter'], $settings['encoding']);
        
        if (empty($csv_data)) {
            return array(
                'success' => false,
                'message' => esc_html__('El archivo CSV está vacío o tiene formato incorrecto', 'catalogo70'),
            );
        }
        
        // Procesar cada fila
        $headers = array_shift($csv_data); // Primera fila = headers
        $headers = array_map('trim', $headers);
        
        foreach ($csv_data as $line_number => $row) {
            $this->process_row($headers, $row, $line_number + 2, $settings);
        }
        
        return array(
            'success' => true,
            'results' => $this->results,
        );
    }
    
    /**
     * Leer archivo CSV
     */
        private function read_csv($file_path, $delimiter, $encoding) {
            $data = array();
        
            // Usar WP_Filesystem
            global $wp_filesystem;
            if (!function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
        
            $content = $wp_filesystem->get_contents($file_path);
        
            if ($content === false) {
                return $data;
            }
        
            if ($encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }
        
                        // Guardar en archivo temporal
            $temp_file = tempnam(sys_get_temp_dir(), 'csv');
            $wp_filesystem->put_contents($temp_file, $content);

            // Leer CSV línea por línea usando str_getcsv
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                // Eliminar BOM UTF-8 si existe
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                $row = str_getcsv($line, $delimiter);
                if (!empty($row)) {
                    $data[] = $row;
                }
            }
        
            wp_delete_file($temp_file);
        
            return $data;
        }
    
    /**
     * Procesar una fila del CSV
     */
    private function process_row($headers, $row, $line_number, $settings) {
        // Crear array asociativo
        $data = array();
        foreach ($headers as $index => $header) {
            $data[$header] = isset($row[$index]) ? trim($row[$index]) : '';
        }
        
                // Validar campos requeridos
        if (empty($data['titulo'])) {
                        $this->results['errors'][] = sprintf(
                /* translators: %d: Line number in the CSV file */
                esc_html__('Línea %d: El campo "titulo" es obligatorio', 'catalogo70'),
                $line_number
            );
            return;
        }
        
        // Verificar si el producto ya existe (por título)
        $existing_product = get_page_by_title($data['titulo'], OBJECT, 'codecatalogo_product');
        
                if ($existing_product && !$settings['update_existing']) {
                        $this->results['warnings'][] = sprintf(
                /* translators: %1$d: Line number in the CSV file, %2$s: Product title */
                esc_html__('Línea %1$d: El producto "%2$s" ya existe y no se actualizó', 'catalogo70'),
                $line_number,
                $data['titulo']
            );
            return;
        }
        
        // Crear o actualizar producto
        $product_id = $this->save_product($data, $existing_product);
        
                if (is_wp_error($product_id)) {
                        $this->results['errors'][] = sprintf(
                /* translators: %1$d: Line number in the CSV file, %2$s: Error message */
                esc_html__('Línea %1$d: %2$s', 'catalogo70'),
                $line_number,
                $product_id->get_error_message()
            );
            return;
        }
        
        // Procesar imagen destacada
        if (!empty($data['imagen']) || !empty($data['imagen_destacada'])) {
            $image_src = !empty($data['imagen_destacada']) ? $data['imagen_destacada'] : $data['imagen'];
            $this->process_image($product_id, $image_src, $line_number);
        }
        
        // Procesar galería de imágenes
        if (!empty($data['galeria_imagenes'])) {
            $this->process_gallery($product_id, $data['galeria_imagenes'], $line_number);
        }
        
        // Procesar categorías
        if (!empty($data['categoria'])) {
            $this->process_categories($product_id, $data['categoria']);
        }
        
        // Procesar etiquetas
        if (!empty($data['etiquetas'])) {
            $this->process_tags($product_id, $data['etiquetas']);
        }
        
        // Procesar campos personalizados
        $this->process_custom_fields($product_id, $data, $headers, $line_number);
        
        // Incrementar contador
        if ($existing_product) {
            $this->results['updated']++;
        } else {
            $this->results['imported']++;
        }
    }
    
                /**
         * Guardar producto
         */
        private function save_product($data, $existing_product = null) {
            $content = !empty($data['contenido']) ? $data['contenido'] : '';
            
            // NO filtrar con wp_kses_post porque elimina <style>
            // El contenido viene del CSV generado por el mismo plugin
            $post_data = array(
            'post_type' => 'codecatalogo_product',
            'post_title' => sanitize_text_field($data['titulo']),
            'post_excerpt' => !empty($data['extracto']) ? sanitize_textarea_field($data['extracto']) : '',
            'post_content' => !empty($content) ? $content : '',
            'post_status' => !empty($data['estado']) ? sanitize_text_field($data['estado']) : 'publish',
        );
        
        if ($existing_product) {
            $post_data['ID'] = $existing_product->ID;
            return wp_update_post($post_data, true);
        } else {
            return wp_insert_post($post_data, true);
        }
    }
    
        /**
     * Procesar imagen (soporta URL completa o ruta local)
     */
    private function process_image($product_id, $image_src, $line_number) {
        // Limpiar
        $image_src = trim($image_src);
        if (empty($image_src)) return;
        
        // Determinar si es URL remota o ruta local
        $is_url = preg_match('/^https?:\/\//', $image_src);
        
        if ($is_url) {
            // Es una URL → descargar la imagen
            $attach_id = $this->download_and_attach_image($product_id, $image_src);
            if ($attach_id) {
                set_post_thumbnail($product_id, $attach_id);
            } else {
                $this->results['warnings'][] = sprintf(
                    esc_html__('Línea %1$d: No se pudo descargar la imagen: %2$s', 'catalogo70'),
                    $line_number,
                    $image_src
                );
            }
        } else {
            // Es ruta local
            // Si es ruta relativa, agregar ABSPATH
            if (strpos($image_src, '/wp-content/') === 0) {
                $full_path = ABSPATH . ltrim($image_src, '/');
            } else {
                $full_path = $image_src;
            }
            
            if (!file_exists($full_path)) {
                $this->results['warnings'][] = sprintf(
                    esc_html__('Línea %1$d: Imagen no encontrada: %2$s', 'catalogo70'),
                    $line_number,
                    $image_src
                );
                return;
            }
            
            $attach_id = $this->insert_attachment_from_file($product_id, $full_path);
            if ($attach_id) {
                set_post_thumbnail($product_id, $attach_id);
            }
        }
    }
    
    /**
     * Procesar galería de imágenes
     */
    private function process_gallery($product_id, $gallery_string, $line_number) {
        $image_urls = array_map('trim', explode(';', $gallery_string));
        $gallery_ids = array();
        
        foreach ($image_urls as $image_src) {
            if (empty($image_src)) continue;
            
            $is_url = preg_match('/^https?:\/\//', $image_src);
            
            if ($is_url) {
                $attach_id = $this->download_and_attach_image($product_id, $image_src);
            } else {
                if (strpos($image_src, '/wp-content/') === 0) {
                    $full_path = ABSPATH . ltrim($image_src, '/');
                } else {
                    $full_path = $image_src;
                }
                if (!file_exists($full_path)) continue;
                $attach_id = $this->insert_attachment_from_file($product_id, $full_path);
            }
            
            if ($attach_id && !is_wp_error($attach_id)) {
                $gallery_ids[] = $attach_id;
            }
        }
        
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_codecatalogo_gallery_ids', implode(',', $gallery_ids));
        }
    }
    
    /**
     * Descargar imagen desde URL externa y agregarla a la biblioteca de medios
     */
    private function download_and_attach_image($product_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Descargar la imagen a la carpeta temporal
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            return false;
        }
        
        // Obtener nombre del archivo desde la URL
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        if (empty($filename)) {
            $filename = 'product-image-' . $product_id . '.jpg';
        }
        
        // Subir a la biblioteca de medios
        $file_args = array(
            'name' => $filename,
            'tmp_name' => $temp_file,
        );
        
        $attach_id = media_handle_sideload($file_args, $product_id);
        
        // Eliminar archivo temporal
        @unlink($temp_file);
        
        return $attach_id;
    }
    
    /**
     * Insertar adjunto desde archivo local
     */
    private function insert_attachment_from_file($product_id, $full_path) {
        $filetype = wp_check_filetype(basename($full_path));
        
        $attachment_data = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($full_path)),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        
        $attach_id = wp_insert_attachment($attachment_data, $full_path, $product_id);
        
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $full_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            return $attach_id;
        }
        
        return false;
    }
    
    /**
     * Procesar categorías
     */
    private function process_categories($product_id, $categories_string) {
        $categories = array_map('trim', explode(';', $categories_string));
        $term_ids = array();
        
        foreach ($categories as $category_name) {
            if (empty($category_name)) continue;
            
            // Buscar o crear categoría
            $term = get_term_by('name', $category_name, 'codecatalogo_cat');
            
            if (!$term) {
                $term_data = wp_insert_term($category_name, 'codecatalogo_cat');
                if (!is_wp_error($term_data)) {
                    $term_ids[] = $term_data['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        
        if (!empty($term_ids)) {
            wp_set_object_terms($product_id, $term_ids, 'codecatalogo_cat');
        }
    }
    
    /**
     * Procesar etiquetas
     */
    private function process_tags($product_id, $tags_string) {
        $tags = array_map('trim', explode(';', $tags_string));
        $term_ids = array();
        
        foreach ($tags as $tag_name) {
            if (empty($tag_name)) continue;
            
            // Buscar o crear etiqueta
            $term = get_term_by('name', $tag_name, 'codecatalogo_tag');
            
            if (!$term) {
                $term_data = wp_insert_term($tag_name, 'codecatalogo_tag');
                if (!is_wp_error($term_data)) {
                    $term_ids[] = $term_data['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        
        if (!empty($term_ids)) {
            wp_set_object_terms($product_id, $term_ids, 'codecatalogo_tag');
        }
    }
    
    /**
     * Procesar campos personalizados
     */
    private function process_custom_fields($product_id, $data, $headers, $line_number) {
        // Obtener campos personalizados configurados
        $custom_fields = $this->field_manager->get_all_fields();

                // Campos del sistema que no son personalizados
        $system_fields = array('titulo', 'extracto', 'contenido', 'categoria', 'etiquetas', 'imagen', 'imagen_destacada', 'galeria_imagenes', 'estado');
        
        global $wpdb;
        $fields_table = $wpdb->prefix . 'codecatalogo_fields';
        $values_table = $wpdb->prefix . 'codecatalogo_field_values';
        
                foreach ($headers as $header) {
            // Saltar campos del sistema
            if (in_array($header, $system_fields)) {
                continue;
            }
            
            // Buscar el campo personalizado por field_label (el header del CSV es el label)
            $field = null;
            foreach ($custom_fields as $custom_field) {
                if ($custom_field->field_label === $header || $custom_field->field_name === $header) {
                    $field = $custom_field;
                    break;
                }
            }
            
            if (!$field) {
                continue; // Campo no existe en la configuración
            }
            
            $value = isset($data[$header]) ? $data[$header] : '';
            
            // Validar campos requeridos
                        if ($field->is_required && empty($value)) {
                                $this->results['errors'][] = sprintf(
                    /* translators: %1$d: Line number in the CSV file, %2$s: Field label */
                    esc_html__('Línea %1$d: El campo "%2$s" es obligatorio', 'catalogo70'),
                    $line_number,
                    $field->field_label
                );
                // No importar este producto si falta un campo requerido
                wp_delete_post($product_id, true);
                return;
            }
            
            // Procesar archivos PDF (fichas técnicas)
            if ($field->field_type === 'file' && !empty($value)) {
                $value = $this->process_file($product_id, $value, $line_number);
            }
            
            // Guardar valor del campo
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$values_table} WHERE product_id = %d AND field_id = %d",
                $product_id,
                $field->id
            ));
            
            if ($existing) {
                $wpdb->update(
                    $values_table,
                    array('field_value' => $value),
                    array('id' => $existing),
                    array('%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $values_table,
                    array(
                        'product_id' => $product_id,
                        'field_id' => $field->id,
                        'field_value' => $value,
                    ),
                    array('%d', '%d', '%s')
                );
            }
        }
        
        // Limpiar caché
        wp_cache_delete('codecatalogo_product_fields_' . $product_id, 'codecatalogo');
    }
    
        /**
     * Procesar archivo PDF (soporta URL remota o ruta local)
     */
    private function process_file($product_id, $file_path, $line_number) {
        // Limpiar
        $file_path = trim($file_path);
        if (empty($file_path)) return '';
        
        $is_url = preg_match('/^https?:\/\//', $file_path);
        
        if ($is_url) {
            // Descargar archivo desde URL
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            
            $temp_file = download_url($file_path);
            if (is_wp_error($temp_file)) {
                $this->results['warnings'][] = sprintf(
                    esc_html__('Línea %1$d: No se pudo descargar el archivo: %2$s', 'catalogo70'),
                    $line_number,
                    $file_path
                );
                return '';
            }
            
            $filename = basename(parse_url($file_path, PHP_URL_PATH));
            if (empty($filename)) {
                $filename = 'document-' . $product_id . '.pdf';
            }
            
            $file_args = array(
                'name' => $filename,
                'tmp_name' => $temp_file,
            );
            
            $attach_id = media_handle_sideload($file_args, $product_id);
            @unlink($temp_file);
            
            if (!is_wp_error($attach_id)) {
                return wp_get_attachment_url($attach_id);
            }
            return '';
        }
        
        // Si es ruta relativa, agregar ABSPATH
        if (strpos($file_path, '/wp-content/') === 0) {
            $full_path = ABSPATH . ltrim($file_path, '/');
        } else {
            $full_path = $file_path;
        }
        
        if (!file_exists($full_path)) {
            $this->results['warnings'][] = sprintf(
                esc_html__('Línea %1$d: Archivo no encontrado: %2$s', 'catalogo70'),
                $line_number,
                $file_path
            );
            return '';
        }
        
        $filetype = wp_check_filetype(basename($full_path));
        
        $attachment_data = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($full_path)),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        
        $attach_id = wp_insert_attachment($attachment_data, $full_path, $product_id);
        
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $full_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            return wp_get_attachment_url($attach_id);
        }
        
        return '';
    }
    
    /**
     * Obtener resultados de la importación
     */
    public function get_results() {
        return $this->results;
    }
}