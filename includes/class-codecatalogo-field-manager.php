<?php
/**
 * Gestor de campos personalizados dinámicos
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Field_Manager {
    const FREE_MAX_FIELDS = 10;
    
    private $table_name;
    private $values_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'codecatalogo_fields';
        $this->values_table = $wpdb->prefix . 'codecatalogo_field_values';
        
                add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_codecatalogo_product', array($this, 'save_fields'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_uploader'));

        // Migración automática de base de datos (agregar nuevas columnas)
        add_action('init', array($this, 'maybe_upgrade_db'));
    }

    /**
     * Migrar base de datos si es necesario
     */
    public function maybe_upgrade_db() {
        $current_db_version = get_option('codecatalogo_field_db_version', '1.0.0');

        if (version_compare($current_db_version, '1.1.0', '<')) {
            global $wpdb;
            
            $row = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'field_group'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN field_group VARCHAR(100) NULL DEFAULT '' AFTER field_icon");
            }

            $row = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'field_unit'");
            if (empty($row)) {
                $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN field_unit VARCHAR(50) NULL DEFAULT '' AFTER field_group");
            }

            update_option('codecatalogo_field_db_version', '1.1.0');
        }
    }
    
        /**
     * Cargar media uploader y scripts necesarios
     */
    public function enqueue_media_uploader($hook) {
        if ($hook === 'post-new.php' || $hook === 'post.php') {
            global $post_type;
            if ($post_type === 'codecatalogo_product') {
                wp_enqueue_media();
                wp_enqueue_script('jquery-ui-sortable');
            }
        }
    }
    
            /**
     * Crear campo personalizado
     */
    public function create_field($data) {
        global $wpdb;
        
        $defaults = array(
            'field_name'       => '',
            'field_label'      => '',
            'field_type'       => 'text',
            'field_options'    => null,
            'field_order'      => 0,
            'field_icon'       => '',
            'field_group'      => '',
            'field_unit'       => '',
            'show_in_card'     => 0,
            'show_in_filter'   => 0,
            'is_seo_relevant'  => 0,
            'is_required'      => 0,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitizar
        $data['field_name'] = sanitize_key($data['field_name']);
        $data['field_label'] = sanitize_text_field($data['field_label']);
        $data['field_type'] = sanitize_text_field($data['field_type']);
        $data['field_icon'] = sanitize_text_field($data['field_icon']);
        $data['field_group'] = sanitize_text_field($data['field_group']);
        $data['field_unit'] = sanitize_text_field($data['field_unit']);
        
        if (!empty($data['field_options']) && is_array($data['field_options'])) {
            $data['field_options'] = wp_json_encode($data['field_options']);
        }
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result) {
            // Limpiar caché
            wp_cache_delete('codecatalogo_all_fields', 'codecatalogo');
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
                /**
     * Actualizar campo
     */
    public function update_field($field_id, $data) {
        global $wpdb;
        
        // Sanitizar datos
        if (isset($data['field_name'])) {
            $data['field_name'] = sanitize_key($data['field_name']);
        }
        if (isset($data['field_label'])) {
            $data['field_label'] = sanitize_text_field($data['field_label']);
        }
        if (isset($data['field_options']) && is_array($data['field_options'])) {
            $data['field_options'] = wp_json_encode($data['field_options']);
        }
        if (isset($data['field_group'])) {
            $data['field_group'] = sanitize_text_field($data['field_group']);
        }
        if (isset($data['field_unit'])) {
            $data['field_unit'] = sanitize_text_field($data['field_unit']);
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $field_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            wp_cache_delete('codecatalogo_all_fields', 'codecatalogo');
            return true;
        }
        
        return false;
    }
    
    /**
     * Eliminar campo
     */
    public function delete_field($field_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $field_id),
            array('%d')
        );
        
        if ($result) {
            wp_cache_delete('codecatalogo_all_fields', 'codecatalogo');
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener todos los campos
     */
    public function get_all_fields($orderby = 'field_order', $order = 'ASC') {
        global $wpdb;
        
        // Intentar obtener del caché
        $cache_key = 'codecatalogo_all_fields_' . $orderby . '_' . $order;
        $fields = wp_cache_get($cache_key, 'codecatalogo');
        
        if (false === $fields) {
            $orderby = sanitize_sql_orderby($orderby . ' ' . $order);
            
            $fields = $wpdb->get_results(
                "SELECT * FROM {$this->table_name} ORDER BY {$orderby}"
            );
            
            // Guardar en caché por 1 hora
            wp_cache_set($cache_key, $fields, 'codecatalogo', 3600);
        }
        
        return $fields;
    }
    
    /**
     * Obtener campo por ID
     */
    public function get_field($field_id) {
        global $wpdb;
        
        $cache_key = 'codecatalogo_field_' . $field_id;
        $field = wp_cache_get($cache_key, 'codecatalogo');
        
        if (false === $field) {
            $field = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $field_id
            ));
            
            if ($field) {
                wp_cache_set($cache_key, $field, 'codecatalogo', 3600);
            }
        }
        
        return $field;
    }
    
    /**
     * Obtener campo por nombre
     */
    public function get_field_by_name($field_name) {
        global $wpdb;
        
        $cache_key = 'codecatalogo_field_name_' . $field_name;
        $field = wp_cache_get($cache_key, 'codecatalogo');
        
        if (false === $field) {
            $field = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE field_name = %s",
                $field_name
            ));
            
            if ($field) {
                wp_cache_set($cache_key, $field, 'codecatalogo', 3600);
            }
        }
        
        return $field;
    }
    
    /**
     * Guardar valor de campo para un producto
     */
    public function save_field_value($product_id, $field_id, $value) {
        global $wpdb;
        
        $value = $this->sanitize_field_value($value, $field_id);
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->values_table} WHERE product_id = %d AND field_id = %d",
            $product_id,
            $field_id
        ));
        
        if ($exists) {
            $result = $wpdb->update(
                $this->values_table,
                array('field_value' => $value),
                array('product_id' => $product_id, 'field_id' => $field_id),
                array('%s'),
                array('%d', '%d')
            );
        } else {
            $result = $wpdb->insert(
                $this->values_table,
                array(
                    'product_id'  => $product_id,
                    'field_id'    => $field_id,
                    'field_value' => $value,
                ),
                array('%d', '%d', '%s')
            );
        }
        
        // Limpiar caché del producto
        wp_cache_delete('codecatalogo_product_fields_' . $product_id, 'codecatalogo');
        
        return $result;
    }
    
    /**
     * Obtener valor de un campo específico
     */
    public function get_field_value($product_id, $field_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT field_value FROM {$this->values_table} WHERE product_id = %d AND field_id = %d",
            $product_id,
            $field_id
        ));
    }
    
    /**
     * Obtener todos los campos de un producto
     */
    public function get_product_fields($product_id, $show_in_card_only = false) {
        global $wpdb;
        
        $cache_key = 'codecatalogo_product_fields_' . $product_id . '_' . ($show_in_card_only ? 'card' : 'all');
        $fields = wp_cache_get($cache_key, 'codecatalogo');
        
        if (false === $fields) {
            $where = $show_in_card_only ? 'AND f.show_in_card = 1' : '';
            
            $fields = $wpdb->get_results($wpdb->prepare(
                "SELECT f.*, v.field_value 
                FROM {$this->table_name} f
                LEFT JOIN {$this->values_table} v ON f.id = v.field_id AND v.product_id = %d
                WHERE 1=1 {$where}
                ORDER BY f.field_order ASC",
                $product_id
            ));
            
            wp_cache_set($cache_key, $fields, 'codecatalogo', 1800);
        }
        
        return $fields;
    }
    
    /**
     * Obtener campos para filtros
     */
    public function get_filter_fields() {
        global $wpdb;
        
        $cache_key = 'codecatalogo_filter_fields';
        $fields = wp_cache_get($cache_key, 'codecatalogo');
        
        if (false === $fields) {
            $fields = $wpdb->get_results(
                "SELECT * FROM {$this->table_name} 
                WHERE show_in_filter = 1 
                ORDER BY field_order ASC"
            );
            
            wp_cache_set($cache_key, $fields, 'codecatalogo', 3600);
        }
        
        return $fields;
    }
    
            /**
     * Agregar meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'codecatalogo_product_fields',
            __('Especificaciones del Producto','catalogo70free'),
            array($this, 'render_fields_meta_box'),
            'codecatalogo_product',
            'normal',
            'high'
        );

        add_meta_box(
            'codecatalogo_product_gallery',
            __('Galería de Imágenes','catalogo70free'),
            array($this, 'render_gallery_meta_box'),
            'codecatalogo_product',
            'side',
            'low'
        );
    }

            /**
     * Renderizar meta box de galería de imágenes
     */
    public function render_gallery_meta_box($post) {
        wp_nonce_field('codecatalogo_save_gallery', 'codecatalogo_gallery_nonce');

        $gallery_ids = get_post_meta($post->ID, '_codecatalogo_gallery_ids', true);
        $gallery_ids = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();
        ?>
        <div class="codecatalogo-gallery-wrapper">
            <p style="font-size:12px;color:#646970;margin-bottom:10px;">
                <?php esc_html_e('Imágenes adicionales del producto. Arrastra para reordenar.','catalogo70free'); ?>
            </p>
            
            <input type="hidden" id="codecatalogo-gallery-ids" name="codecatalogo_gallery_ids" 
                   value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>">
            
            <div class="codecatalogo-gallery-container">
                <ul class="codecatalogo-gallery-images" id="codecatalogo-gallery-images">
                    <?php foreach ($gallery_ids as $attach_id): 
                        $image = wp_get_attachment_image_src($attach_id, 'thumbnail');
                        if (!$image) continue;
                    ?>
                    <li class="codecatalogo-gallery-image" data-attach-id="<?php echo esc_attr($attach_id); ?>">
                        <img src="<?php echo esc_url($image[0]); ?>" alt="">
                        <button type="button" class="codecatalogo-gallery-remove" title="Eliminar">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <p id="codecatalogo-gallery-empty" style="display:<?php echo !empty($gallery_ids) ? 'none' : 'block'; ?>;color:#787c82;font-style:italic;font-size:12px;text-align:center;">
                    <?php esc_html_e('No hay imágenes. Haz clic en "Agregar imágenes".','catalogo70free'); ?>
                </p>
                
                <button type="button" class="button" id="codecatalogo-gallery-add">
                    <span class="dashicons dashicons-format-gallery" style="vertical-align:middle;"></span>
                    <?php esc_html_e('Agregar imágenes','catalogo70free'); ?>
                </button>
            </div>
        </div>
        <style>
            #codecatalogo-gallery-images { 
                display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 8px 0; padding: 6px; 
                min-height: 70px; border: 1px dashed #c3c4c7; border-radius: 4px;
                list-style: none;
            }
            #codecatalogo-gallery-images .codecatalogo-gallery-image { 
                position: relative; list-style: none; margin: 0;
                width: 65px; height: 65px; border-radius: 3px; overflow: hidden;
                border: 1px solid #ddd; cursor: move;
            }
            #codecatalogo-gallery-images .codecatalogo-gallery-image img { 
                width: 100%; height: 100%; object-fit: cover; display: block; 
            }
            #codecatalogo-gallery-images .codecatalogo-gallery-remove {
                position: absolute; top: 2px; right: 2px; 
                width: 18px; height: 18px; border-radius: 50%;
                background: #d63638; color: #fff; border: none;
                cursor: pointer; display: none; align-items: center; justify-content: center;
                padding: 0; font-size: 12px; line-height: 1;
            }
            #codecatalogo-gallery-images .codecatalogo-gallery-image:hover .codecatalogo-gallery-remove { 
                display: flex; 
            }
            #codecatalogo-gallery-images .codecatalogo-gallery-remove .dashicons {
                font-size: 14px; width: 14px; height: 14px;
            }
            #codecatalogo-gallery-add .dashicons { 
                vertical-align: middle; margin-right: 2px; 
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            var frame;
            var $container = $('#codecatalogo-gallery-images');
            var $ids = $('#codecatalogo-gallery-ids');
            var $empty = $('#codecatalogo-gallery-empty');

            function actualizar() {
                var ids = [];
                $container.find('.codecatalogo-gallery-image').each(function() {
                    ids.push($(this).data('attach-id'));
                });
                $ids.val(ids.join(','));
                $empty.toggle(ids.length === 0);
            }

            $('#codecatalogo-gallery-add').on('click', function(e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: 'Seleccionar imágenes',
                    button: { text: 'Agregar' },
                    multiple: true
                });
                frame.on('select', function() {
                    var attachments = frame.state().get('selection').toJSON();
                    $.each(attachments, function(i, attachment) {
                        var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                        var $li = $('<li class="codecatalogo-gallery-image" data-attach-id="' + attachment.id + '">' +
                            '<img src="' + url + '" alt="">' +
                            '<button type="button" class="codecatalogo-gallery-remove"><span class="dashicons dashicons-no-alt"></span></button></li>');
                        $container.append($li);
                    });
                    actualizar();
                });
                frame.open();
            });

            $container.on('click', '.codecatalogo-gallery-remove', function() {
                var $li = $(this).closest('li');
                $li.fadeOut(function() { $li.remove(); actualizar(); });
            });

            $container.sortable({
                items: 'li',
                cursor: 'move',
                update: function() { actualizar(); }
            });

            actualizar();
        });
        </script>
        <?php
    }
    
                /**
     * Obtener grupos de campos disponibles
     */
    private function get_field_groups() {
        global $wpdb;
        $groups = $wpdb->get_results(
            "SELECT DISTINCT field_group FROM {$this->table_name} 
             WHERE field_group != '' 
             ORDER BY field_group ASC"
        );
        
        $group_list = array();
        foreach ($groups as $g) {
            $group_list[] = $g->field_group;
        }
        return $group_list;
    }

    /**
     * Renderizar meta box de campos (mejorado con agrupación)
     */
    public function render_fields_meta_box($post) {
        wp_nonce_field('codecatalogo_save_fields', 'codecatalogo_fields_nonce');
        
        $fields = $this->get_product_fields($post->ID);
        
                if (empty($fields)) {
            echo '<p>' . esc_html__('No hay campos configurados. Por favor, configura los campos en Catálogo Pro > Campos.','catalogo70free') . '</p>';
            return;
        }
        
        // Separar campos con grupo y sin grupo
        $grouped_fields = array();
        $ungrouped_fields = array();
        
        foreach ($fields as $field) {
            if (!empty($field->field_group)) {
                $grouped_fields[$field->field_group][] = $field;
            } else {
                $ungrouped_fields[] = $field;
            }
        }
        
        echo '<div class="codecatalogo-fields-wrapper">';
        
        // Mostrar campos sin grupo primero
        if (!empty($ungrouped_fields)) {
            foreach ($ungrouped_fields as $field) {
                $this->render_single_field($field);
            }
        }
        
        // Mostrar campos agrupados
        foreach ($grouped_fields as $group_name => $group_fields) {
            $group_label = ucfirst(str_replace('_', ' ', $group_name));
            echo '<div class="codecatalogo-field-group-box">';
            echo '<h4 class="codecatalogo-group-title">';
            echo '<span class="dashicons dashicons-arrow-down-alt2"></span> ';
            echo esc_html($group_label);
            echo '</h4>';
            echo '<div class="codecatalogo-group-fields">';
            
            foreach ($group_fields as $field) {
                $this->render_single_field($field);
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Renderizar un campo individual
     */
    private function render_single_field($field) {
        $value = $field->field_value ?? '';
        $required = $field->is_required ? ' <span class="required">*</span>' : '';
        
        echo '<div class="codecatalogo-field-group">';
        
                // Label
                echo '<label for="codecatalogo_field_' . esc_attr($field->id) . '">';
        echo esc_html($field->field_label) . wp_kses_post($required);
        echo '</label>';
        
        // Input con unidad
        echo '<div class="codecatalogo-field-input-wrapper">';
        $this->render_field_input($field, $value);
        if (!empty($field->field_unit)) {
            echo '<span class="codecatalogo-field-unit">' . esc_html($field->field_unit) . '</span>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
                /**
     * Generar placeholder sugerido según el tipo de campo y nombre
     */
    private function get_field_placeholder($field) {
        $label = strtolower($field->field_label);
        $type = $field->field_type;
        
        // Placeholders según palabras clave en la etiqueta
        if (strpos($label, 'capacidad') !== false || strpos($label, 'litros') !== false) {
            return $type === 'number' ? 'Ej: 200' : 'Ej: 200 litros';
        }
        if (strpos($label, 'peso') !== false) {
            return $type === 'number' ? 'Ej: 80' : 'Ej: 80 kg';
        }
        if (strpos($label, 'dimensi') !== false || strpos($label, 'medida') !== false) {
            return 'Ej: 2000 x 1500 x 1200 mm';
        }
        if (strpos($label, 'voltaje') !== false || strpos($label, 'volt') !== false) {
            return 'Ej: 220V';
        }
        if (strpos($label, 'potencia') !== false) {
            return 'Ej: 300W';
        }
        if (strpos($label, 'tubo') !== false) {
            return $type === 'number' ? 'Ej: 20' : 'Ej: 20 tubos';
        }
        if (strpos($label, 'área') !== false || strpos($label, 'captación') !== false) {
            return 'Ej: 2.5 m²';
        }
        if (strpos($label, 'modelo') !== false || strpos($label, 'código') !== false) {
            return 'Ej: CS-200L';
        }
        if (strpos($label, 'marca') !== false) {
            return 'Ej: Nombre de la marca';
        }
        if (strpos($label, 'garantía') !== false || strpos($label, 'garantia') !== false) {
            return 'Ej: 5 años';
        }
        if (strpos($label, 'email') !== false || strpos($label, 'correo') !== false) {
            return 'ejemplo@correo.com';
        }
        if (strpos($label, 'url') !== false || strpos($label, 'web') !== false || strpos($label, 'sitio') !== false) {
            return 'https://ejemplo.com';
        }
        
        // Placeholders genéricos por tipo
        $type_placeholders = array(
            'number' => 'Ingresa un valor numérico',
            'text' => 'Ingresa el valor',
            'textarea' => 'Describe la información aquí...',
            'url' => 'https://',
            'email' => 'correo@ejemplo.com',
            'date' => 'Selecciona una fecha',
        );
        
        return isset($type_placeholders[$type]) ? $type_placeholders[$type] : '';
    }

        private function render_field_input($field, $value) {
        $field_id = 'codecatalogo_field_' . $field->id;
        $field_name = 'codecatalogo_fields[' . $field->id . ']';
        $placeholder = $this->get_field_placeholder($field);
        $is_required = $field->is_required;
        
        switch ($field->field_type) {
            case 'text':
                echo '<input type="text" 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      value="' . esc_attr($value) . '" 
                      class="widefat"' .
                      ($placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '') .
                      ($is_required ? ' required' : '') . ' />';
                break;
                
            case 'number':
                echo '<input type="number" 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      value="' . esc_attr($value) . '" 
                      class="widefat codecatalogo-number-input" 
                      step="any"' .
                      ($placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '') .
                      ($is_required ? ' required' : '') . ' />';
                break;
                
            case 'textarea':
                echo '<textarea 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      class="widefat" 
                      rows="4"' .
                      ($placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '') .
                      ($is_required ? ' required' : '') . '>' . esc_textarea($value) . '</textarea>';
                break;
                
            case 'select':
                $options = !empty($field->field_options) ? json_decode($field->field_options, true) : array();
                
                echo '<select 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      class="widefat"' .
                      ($is_required ? ' required' : '') . '>';
                echo '<option value="">-- ' . esc_html__('Seleccionar','catalogo70free') . ' --</option>';
                
                foreach ($options as $opt_value => $opt_label) {
                    echo '<option value="' . esc_attr($opt_value) . '" ' . selected($value, $opt_value, false) . '>';
                    echo esc_html($opt_label);
                    echo '</option>';
                }
                echo '</select>';
                break;
                
            case 'file':
                echo '<div class="codecatalogo-file-field">';
                echo '<input type="text" 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      value="' . esc_attr($value) . '" 
                      class="widefat codecatalogo-file-url"' .
                      ($placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '') .
                      ($is_required ? ' required' : '') . ' />';
                
                echo '<button type="button" 
                      class="button codecatalogo-upload-file codecatalogo-upload-btn" 
                      data-target="' . esc_attr($field_id) . '">
                      <span class="dashicons dashicons-upload"></span> ' . esc_html__('Subir','catalogo70free') . '
                      </button>';
                
                if ($value) {
                    echo '<div class="codecatalogo-file-preview">';
                    echo '<a href="' . esc_url($value) . '" target="_blank" class="button">
                          <span class="dashicons dashicons-media-document"></span> ' . esc_html__('Ver Archivo','catalogo70free') . '
                          </a>';
                    echo '</div>';
                }
                
                echo '</div>';
                break;
                
            case 'url':
                echo '<input type="url" 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      value="' . esc_attr($value) . '" 
                      class="widefat" 
                      placeholder="https://"' .
                      ($is_required ? ' required' : '') . ' />';
                break;
                
            case 'email':
                echo '<input type="email" 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      value="' . esc_attr($value) . '" 
                      class="widefat"' .
                      ($placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '') .
                      ($is_required ? ' required' : '') . ' />';
                break;
                
            case 'date':
                echo '<input type="date" 
                      id="' . esc_attr($field_id) . '" 
                      name="' . esc_attr($field_name) . '" 
                      value="' . esc_attr($value) . '" 
                      class="widefat"' .
                      ($is_required ? ' required' : '') . ' />';
                break;
        }
    }
    
    /**
     * Guardar campos al guardar el post
     */
    public function save_fields($post_id, $post) {
        // Verificaciones de seguridad
        if (!isset($_POST['codecatalogo_fields_nonce']) || 
            !wp_verify_nonce($_POST['codecatalogo_fields_nonce'], 'codecatalogo_save_fields')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
                if (isset($_POST['codecatalogo_fields']) && is_array($_POST['codecatalogo_fields'])) {
            foreach ($_POST['codecatalogo_fields'] as $field_id => $field_value) {
                $this->save_field_value($post_id, intval($field_id), $field_value);
            }
            
            // Limpiar caché del producto
            wp_cache_delete('codecatalogo_product_fields_' . $post_id, 'codecatalogo');
            wp_cache_delete('codecatalogo_product_fields_' . $post_id . '_card', 'codecatalogo');
            wp_cache_delete('codecatalogo_product_fields_' . $post_id . '_all', 'codecatalogo');
        }

        // Guardar galería de imágenes
        if (isset($_POST['codecatalogo_gallery_nonce']) && 
            wp_verify_nonce($_POST['codecatalogo_gallery_nonce'], 'codecatalogo_save_gallery')) {
            
            if (isset($_POST['codecatalogo_gallery_ids'])) {
                $gallery_ids = sanitize_text_field($_POST['codecatalogo_gallery_ids']);
                $gallery_ids = trim($gallery_ids, ',');
                
                // Validar que sean IDs numéricos
                $valid_ids = array();
                foreach (explode(',', $gallery_ids) as $id) {
                    $id = intval(trim($id));
                    if ($id > 0) {
                        $valid_ids[] = $id;
                    }
                }
                
                if (!empty($valid_ids)) {
                    update_post_meta($post_id, '_codecatalogo_gallery_ids', implode(',', $valid_ids));
                } else {
                    delete_post_meta($post_id, '_codecatalogo_gallery_ids');
                }
            }
        }
    }
    
    /**
     * Sanitizar valor según tipo de campo
     */
    private function sanitize_field_value($value, $field_id) {
        $field = $this->get_field($field_id);
        
        if (!$field) {
            return sanitize_text_field($value);
        }
        
        switch ($field->field_type) {
            case 'number':
                return floatval($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'file':
            case 'url':
                return esc_url_raw($value);
                
            case 'email':
                return sanitize_email($value);
                
            default:
                return sanitize_text_field($value);
        }
    }
}

// Inicializar
new CodeCatalogo_Field_Manager();
