<?php
/**
 * Página de configuración
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Settings {

    private $field_manager;

    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();

        // Hook temprano para procesar descargas ANTES de cualquier output
        add_action('admin_init', array($this, 'process_downloads'));
    }

    /**
     * Procesar descargas (plantilla y exportación)
     * Se ejecuta en admin_init, ANTES de cualquier output
     */
    public function process_downloads() {
        $is_our_page = (isset($_GET['page']) && $_GET['page'] === 'codecatalogo-import-export');
        $is_export = isset($_POST['codecatalogo_export_nonce']);

        if (!$is_our_page && !$is_export) {
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'download_template' && isset($_GET['nonce'])) {
            if (wp_verify_nonce($_GET['nonce'], 'codecatalogo_download_template')) {
                require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-exporter.php';
                $exporter = new CodeCatalogo_Exporter();
                $result = $exporter->generate_template();
                if ($result['success']) {
                    $this->force_download($result['file_path'], $result['filename']);
                } else {
                    wp_die('<h1>' . esc_html__('Error al generar la plantilla CSV', 'catalogo70') . '</h1><p><strong>' . esc_html__('Mensaje:', 'catalogo70') . '</strong> ' . esc_html($result['message']) . '</p><p><a href="' . esc_url(admin_url('admin.php?page=codecatalogo-import-export')) . '">' . esc_html__('Volver', 'catalogo70') . '</a></p>', esc_html__('Error - CodeCatalogo Pro', 'catalogo70'));
                }
            }
        }

        if (isset($_POST['codecatalogo_export_nonce'])) {
            if (wp_verify_nonce($_POST['codecatalogo_export_nonce'], 'codecatalogo_export_csv')) {
                require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-exporter.php';
                $exporter = new CodeCatalogo_Exporter();
                $settings = array(
                    'delimiter' => isset($_POST['export_delimiter']) ? sanitize_text_field($_POST['export_delimiter']) : ',',
                    'encoding' => isset($_POST['export_encoding']) ? sanitize_text_field($_POST['export_encoding']) : 'UTF-8',
                );
                $result = $exporter->export_to_csv($settings);
                if ($result['success']) {
                    $this->force_download($result['file_path'], $result['filename']);
                } else {
                    wp_die('<h1>' . esc_html__('Error al exportar productos', 'catalogo70') . '</h1><p><strong>' . esc_html__('Mensaje:', 'catalogo70') . '</strong> ' . esc_html($result['message']) . '</p><p><a href="' . esc_url(admin_url('admin.php?page=codecatalogo-import-export')) . '">' . esc_html__('Volver', 'catalogo70') . '</a></p>', esc_html__('Error - CodeCatalogo Pro', 'catalogo70'));
                }
            }
        }
    }

        private function force_download($file_path, $filename) {
        if (!file_exists($file_path)) {
            wp_die(esc_html__('El archivo no existe.', 'catalogo70'));
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        // Leer y enviar archivo
                global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($file_path);
        
        wp_delete_file($file_path);
        exit;
    }

    /**
     * Agregar páginas de menú
     */
    public function add_menu_pages() {
        add_submenu_page(
            'edit.php?post_type=codecatalogo_product',
            __('Campos Personalizados', 'catalogo70'),
            __('Campos', 'catalogo70'),
            'manage_options',
            'codecatalogo-fields',
            array($this, 'render_fields_page')
        );
        add_submenu_page(
            'edit.php?post_type=codecatalogo_product',
            __('Configuración', 'catalogo70'),
            __('Configuración', 'catalogo70'),
            'manage_options',
            'codecatalogo-settings',
            array($this, 'render_settings_page')
        );
        add_submenu_page(
            'edit.php?post_type=codecatalogo_product',
            __('Importar / Exportar', 'catalogo70'),
            __('Importar / Exportar', 'catalogo70'),
            'manage_options',
            'codecatalogo-import-export',
            array($this, 'render_import_export_page')
        );
        add_submenu_page(
            'edit.php?post_type=codecatalogo_product',
            __('Licencia', 'catalogo70'),
            __('Licencia', 'catalogo70'),
            'manage_options',
            'codecatalogo-license',
            array($this, 'render_license_page')
        );
        add_submenu_page(
            null,
            __('Activar Licencia (Simple)', 'catalogo70'),
            __('Activar Licencia (Simple)', 'catalogo70'),
            'manage_options',
            'codecatalogo-license-simple',
            array($this, 'render_license_simple_page')
        );
        // Mensajes de contacto
        add_submenu_page(
            'edit.php?post_type=codecatalogo_product',
            __('Mensajes de Contacto', 'catalogo70'),
            __('Mensajes', 'catalogo70'),
            'manage_options',
            'codecatalogo-messages',
            array($this, 'render_messages_page')
        );
        // Diagnóstico de correo
        add_submenu_page(
            'edit.php?post_type=codecatalogo_product',
            __('Diagnóstico de Correo', 'catalogo70'),
            __('Diagnóstico Correo', 'catalogo70'),
            'manage_options',
            'codecatalogo-mail-test',
            array($this, 'render_mail_test_page')
        );
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['codecatalogo_settings_submit'])) {
            check_admin_referer('codecatalogo_settings_action', 'codecatalogo_settings_nonce');
            $this->save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuración guardada.', 'catalogo70') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('codecatalogo_settings_action', 'codecatalogo_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="codecatalogo_catalog_slug"><?php esc_html_e('Slug del catálogo', 'catalogo70'); ?></label></th>
                        <td><input type="text" id="codecatalogo_catalog_slug" name="codecatalogo_catalog_slug" value="<?php echo esc_attr(get_option('codecatalogo_catalog_slug', 'catalogo')); ?>" class="regular-text"><p class="description"><?php esc_html_e('URL base para el archivo del catálogo', 'catalogo70'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="codecatalogo_product_slug"><?php esc_html_e('Slug del producto', 'catalogo70'); ?></label></th>
                        <td><input type="text" id="codecatalogo_product_slug" name="codecatalogo_product_slug" value="<?php echo esc_attr(get_option('codecatalogo_product_slug', 'producto')); ?>" class="regular-text"><p class="description"><?php esc_html_e('URL base para productos individuales', 'catalogo70'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="codecatalogo_products_per_page"><?php esc_html_e('Productos por página', 'catalogo70'); ?></label></th>
                        <td><input type="number" id="codecatalogo_products_per_page" name="codecatalogo_products_per_page" value="<?php echo esc_attr(get_option('codecatalogo_products_per_page', 12)); ?>" min="1" max="100" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('SEO', 'catalogo70'); ?></th>
                        <td>
                            <label><input type="checkbox" name="codecatalogo_enable_seo" value="1" <?php checked(get_option('codecatalogo_enable_seo', 1), 1); ?>> <?php esc_html_e('Habilitar SEO', 'catalogo70'); ?></label><br>
                            <label><input type="checkbox" name="codecatalogo_enable_schema" value="1" <?php checked(get_option('codecatalogo_enable_schema', 1), 1); ?>> <?php esc_html_e('Habilitar Schema.org markup', 'catalogo70'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="codecatalogo_default_whatsapp"><?php esc_html_e('WhatsApp por defecto', 'catalogo70'); ?></label></th>
                        <td><input type="text" id="codecatalogo_default_whatsapp" name="codecatalogo_default_whatsapp" value="<?php echo esc_attr(get_option('codecatalogo_default_whatsapp', '')); ?>" class="regular-text" placeholder="+51987654321"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="codecatalogo_default_whatsapp_message"><?php esc_html_e('Mensaje adicional WhatsApp', 'catalogo70'); ?></label></th>
                        <td><textarea id="codecatalogo_default_whatsapp_message" name="codecatalogo_default_whatsapp_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('codecatalogo_default_whatsapp_message', '')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="codecatalogo_default_form_email"><?php esc_html_e('Email por defecto', 'catalogo70'); ?></label></th>
                        <td><input type="email" id="codecatalogo_default_form_email" name="codecatalogo_default_form_email" value="<?php echo esc_attr(get_option('codecatalogo_default_form_email', get_option('admin_email'))); ?>" class="regular-text"><p class="description"><?php esc_html_e('Email para formularios de contacto', 'catalogo70'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="codecatalogo_primary_color"><?php esc_html_e('Color primario', 'catalogo70'); ?></label></th>
                        <td><input type="color" id="codecatalogo_primary_color" name="codecatalogo_primary_color" value="<?php echo esc_attr(get_option('codecatalogo_primary_color', '#0073aa')); ?>"></td>
                    </tr>
                </table>
                <?php submit_button(__('Guardar Configuración', 'catalogo70'), 'primary', 'codecatalogo_settings_submit'); ?>
            </form>
        </div>
        <?php
    }

    private function save_settings() {
        $options = array(
            'codecatalogo_catalog_slug',
            'codecatalogo_product_slug',
            'codecatalogo_products_per_page',
            'codecatalogo_enable_seo',
            'codecatalogo_enable_schema',
            'codecatalogo_default_whatsapp',
            'codecatalogo_default_whatsapp_message',
            'codecatalogo_default_form_email',
            'codecatalogo_primary_color',
        );
        foreach ($options as $option) {
            if (isset($_POST[$option])) {
                update_option($option, sanitize_text_field($_POST[$option]));
            } else {
                if (in_array($option, array('codecatalogo_enable_seo', 'codecatalogo_enable_schema'))) {
                    update_option($option, 0);
                }
            }
        }
        flush_rewrite_rules();
    }

    /**
     * Renderizar página de campos
     */
    public function render_fields_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['codecatalogo_save_field_nonce'])) {
            if (wp_verify_nonce($_POST['codecatalogo_save_field_nonce'], 'codecatalogo_save_field')) {
                $field_data = array(
                    'field_name'       => sanitize_key($_POST['field_name']),
                    'field_label'      => sanitize_text_field($_POST['field_label']),
                    'field_type'       => sanitize_text_field($_POST['field_type']),
                    'field_group'      => sanitize_text_field($_POST['field_group'] ?? ''),
                    'field_unit'       => sanitize_text_field($_POST['field_unit'] ?? ''),
                    'show_in_card'     => isset($_POST['show_in_card']) ? 1 : 0,
                    'show_in_filter'   => isset($_POST['show_in_filter']) ? 1 : 0,
                    'is_seo_relevant'  => isset($_POST['is_seo_relevant']) ? 1 : 0,
                    'is_required'      => isset($_POST['is_required']) ? 1 : 0,
                );
                $result = $this->field_manager->create_field($field_data);
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Campo creado exitosamente.', 'catalogo70') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error al crear el campo.', 'catalogo70') . '</p></div>';
                }
            }
        }
        if (isset($_GET['action']) && $_GET['action'] === 'new') {
            $this->render_field_form();
            return;
        }
        $fields = $this->field_manager->get_all_fields();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('Gestiona los campos personalizados de tus productos.', 'catalogo70'); ?></p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields&action=new')); ?>" class="button button-primary"><?php esc_html_e('Agregar Campo', 'catalogo70'); ?></a></p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th width="30"></th>
                    <th><?php esc_html_e('Nombre', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Etiqueta', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Tipo', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Tarjeta', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Filtro', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('SEO', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Requerido', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Acciones', 'catalogo70'); ?></th>
                </tr></thead>
                <tbody id="codecatalogo-fields-sortable">
                    <?php foreach ($fields as $field): ?>
                    <tr data-field-id="<?php echo esc_attr($field->id); ?>">
                        <td class="handle" style="cursor:move"><span class="dashicons dashicons-menu"></span></td>
                        <td><code><?php echo esc_html($field->field_name); ?></code></td>
                        <td><?php echo esc_html($field->field_label); ?></td>
                        <td><?php echo esc_html($field->field_type); ?></td>
                        <td><input type="checkbox" class="codecatalogo-toggle-field" data-field-id="<?php echo esc_attr($field->id); ?>" data-field="show_in_card" <?php checked($field->show_in_card, 1); ?>></td>
                        <td><input type="checkbox" class="codecatalogo-toggle-field" data-field-id="<?php echo esc_attr($field->id); ?>" data-field="show_in_filter" <?php checked($field->show_in_filter, 1); ?>></td>
                        <td><input type="checkbox" class="codecatalogo-toggle-field" data-field-id="<?php echo esc_attr($field->id); ?>" data-field="is_seo_relevant" <?php checked($field->is_seo_relevant, 1); ?>></td>
                        <td><input type="checkbox" class="codecatalogo-toggle-field" data-field-id="<?php echo esc_attr($field->id); ?>" data-field="is_required" <?php checked($field->is_required, 1); ?>></td>
                        <td><button type="button" class="button button-small button-link-delete codecatalogo-delete-field" data-field-id="<?php echo esc_attr($field->id); ?>"><?php esc_html_e('Eliminar', 'catalogo70'); ?></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        jQuery(function($) {
            $('.codecatalogo-toggle-field').on('change', function() {
                var $cb = $(this), fid = $cb.data('field-id'), f = $cb.data('field'), v = $cb.is(':checked')?1:0;
                $.post(ajaxurl, {action:'codecatalogo_toggle_field',nonce:codecatalogoAdmin.nonce,field_id:fid,field:f,value:v});
            });
        });
        </script>
        <?php
    }

    private function render_field_form() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agregar Campo', 'catalogo70'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields')); ?>">
                <?php wp_nonce_field('codecatalogo_save_field', 'codecatalogo_save_field_nonce'); ?>
                <table class="form-table">
                    <tr><th><label for="field_label"><?php esc_html_e('Etiqueta', 'catalogo70'); ?></label></th><td><input type="text" id="field_label" name="field_label" class="regular-text" required placeholder="<?php esc_attr_e('Ej: Capacidad, Potencia', 'catalogo70'); ?>"></td></tr>
                    <tr><th><label for="field_name"><?php esc_html_e('Nombre interno', 'catalogo70'); ?></label></th><td><input type="text" id="field_name" name="field_name" class="regular-text" required placeholder="<?php esc_attr_e('Ej: capacidad, potencia', 'catalogo70'); ?>"><p class="description"><?php esc_html_e('Solo minúsculas, sin espacios.', 'catalogo70'); ?></p></td></tr>
                    <tr><th><label for="field_type"><?php esc_html_e('Tipo', 'catalogo70'); ?></label></th><td><select id="field_type" name="field_type"><option value="text"><?php esc_html_e('Texto', 'catalogo70'); ?></option><option value="number"><?php esc_html_e('Número', 'catalogo70'); ?></option><option value="textarea"><?php esc_html_e('Texto largo', 'catalogo70'); ?></option><option value="file"><?php esc_html_e('Archivo', 'catalogo70'); ?></option><option value="url"><?php esc_html_e('URL', 'catalogo70'); ?></option></select></td></tr>
                    <tr><th><?php esc_html_e('Opciones', 'catalogo70'); ?></th><td><label><input type="checkbox" name="show_in_card" value="1" checked> <?php esc_html_e('Mostrar en tarjeta', 'catalogo70'); ?></label><br><label><input type="checkbox" name="show_in_filter" value="1"> <?php esc_html_e('Usar como filtro', 'catalogo70'); ?></label><br><label><input type="checkbox" name="is_seo_relevant" value="1"> <?php esc_html_e('Relevante para SEO', 'catalogo70'); ?></label><br><label><input type="checkbox" name="is_required" value="1"> <?php esc_html_e('Campo obligatorio', 'catalogo70'); ?></label></td></tr>
                </table>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Guardar Campo', 'catalogo70'); ?></button> <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields')); ?>" class="button"><?php esc_html_e('Cancelar', 'catalogo70'); ?></a></p>
            </form>
        </div>
        <script>
        jQuery(function($) {
            $('#field_label').on('blur', function() {
                if (!$('#field_name').val()) {
                    $('#field_name').val($(this).val().toLowerCase().replace(/[^a-z0-9]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,''));
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Renderizar página de Mensajes de Contacto
     */
    public function render_messages_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'codecatalogo_contacts';
        
        if (isset($_GET['mark_read']) && intval($_GET['mark_read']) > 0) {
            $wpdb->update($table, array('is_read' => 1), array('id' => intval($_GET['mark_read'])));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Mensaje marcado como leído.', 'catalogo70') . '</p></div>';
        }
        
        $messages = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100");
        $unread = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_read = 0");
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Mensajes de Contacto', 'catalogo70'); ?> <?php if ($unread > 0): ?><span class="update-plugins count-<?php echo esc_attr($unread); ?>"><span class="update-count"><?php echo esc_html($unread); ?></span></span><?php endif; ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e('Fecha', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Producto', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Nombre', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Email', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Teléfono', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Mensaje', 'catalogo70'); ?></th>
                    <th><?php esc_html_e('Estado', 'catalogo70'); ?></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                    <tr><td colspan="7"><?php esc_html_e('No hay mensajes aún.', 'catalogo70'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr style="<?php $s = !$msg->is_read ? 'font-weight:bold;background:#f0f6fc;' : ''; echo esc_attr($s); ?>">
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($msg->created_at))); ?></td>
                            <td><?php echo esc_html($msg->product_name ?: '—'); ?></td>
                            <td><?php echo esc_html($msg->contact_name); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($msg->contact_email); ?>"><?php echo esc_html($msg->contact_email); ?></a></td>
                            <td><?php echo esc_html($msg->contact_phone ?: '—'); ?></td>
                            <td><?php echo nl2br(esc_html($msg->contact_message)); ?></td>
                            <td>
                                <?php if ($msg->is_read): ?>
                                    <?php esc_html_e('Leído', 'catalogo70'); ?>
                                <?php else: ?>
                                    <a href="?page=codecatalogo-messages&mark_read=<?php echo intval($msg->id); ?>" class="button button-small"><?php esc_html_e('Marcar leído', 'catalogo70'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Renderizar página de Diagnóstico de Correo
     */
    public function render_mail_test_page() {
        global $wpdb;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Diagnóstico de Correo', 'catalogo70'); ?></h1>
            
            <?php
            if (isset($_POST['codecatalogo_test_mail'])) {
                $test_to = sanitize_email($_POST['test_email']);
                $test_subject = 'CodeCatalogo Pro - Prueba de envío';
                $test_body = "Hola,\n\nEste es un mensaje de prueba desde CodeCatalogo Pro.\n\nFecha: " . current_time('mysql') . "\n\nSaludos.";
                $test_headers = array('Content-Type: text/plain; charset=UTF-8');
                
                echo '<div style="background:#fff;padding:15px;border:1px solid #ccc;margin:10px 0;">';
                echo '<h3>' . esc_html__('Resultado:', 'catalogo70') . '</h3>';
                echo '<p><strong>' . esc_html__('Email destino:', 'catalogo70') . '</strong> ' . esc_html($test_to) . '</p>';
                
                $start = microtime(true);
                $sent = wp_mail($test_to, $test_subject, $test_body, $test_headers);
                $elapsed = round((microtime(true) - $start) * 1000);
                
                                if ($sent) {
                                    echo '<div class="notice notice-success"><p>✅ ' . sprintf(
                                        /* translators: %d: Time in milliseconds */
                                        esc_html__('CORREO ENVIADO (%d ms) - Revisa tu bandeja de entrada y SPAM', 'catalogo70'),
                                        intval($elapsed)
                                    ) . '</p></div>';
                                } else {
                                    echo '<div class="notice notice-error"><p>❌ ' . sprintf(
                                        /* translators: %d: Time in milliseconds */
                                        esc_html__('FALLO wp_mail() (%d ms)', 'catalogo70'),
                                        intval($elapsed)
                                    ) . '</p></div>';
                    
                    echo '<h4>' . esc_html__('Probando mail() de PHP...', 'catalogo70') . '</h4>';
                    $mail_headers = "From: " . get_bloginfo('name') . " <" . get_option('admin_email') . ">\r\n";
                    $mail_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    
                    $start2 = microtime(true);
                    $sent2 = @mail($test_to, '=?UTF-8?B?' . base64_encode($test_subject) . '?=', $test_body, $mail_headers);
                    $elapsed2 = round((microtime(true) - $start2) * 1000);
                    
                                        if ($sent2) {
                                            echo '<div class="notice notice-success"><p>✅ ' . sprintf(
                                                /* translators: %d: Time in milliseconds */
                                                esc_html__('CORREO ENVIADO con mail() de PHP (%d ms)', 'catalogo70'),
                                                intval($elapsed2)
                                            ) . '</p></div>';
                                        } else {
                                            echo '<div class="notice notice-error"><p>❌ ' . sprintf(
                                                /* translators: %d: Time in milliseconds */
                                                esc_html__('TAMBIÉN FALLÓ mail() de PHP (%d ms)', 'catalogo70'),
                                                intval($elapsed2)
                                            ) . '</p></div>';
                        echo '<p>' . esc_html__('Posibles causas:', 'catalogo70') . '</p>';
                        echo '<ul style="list-style:disc;padding-left:20px;">';
                        echo '<li>' . esc_html__('En local (XAMPP): Mercury Mail no está configurado. Actívalo desde el panel de XAMPP.', 'catalogo70') . '</li>';
                        echo '<li>' . esc_html__('En hosting: Instala el plugin WP Mail SMTP y configura SendGrid, Brevo o Mailgun (gratuitos).', 'catalogo70') . '</li>';
                        echo '</ul>';
                    }
                }
                echo '</div>';
            }
            
            $table_contacts = $wpdb->prefix . 'codecatalogo_contacts';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_contacts}'");
            ?>
            
            <h3><?php esc_html_e('Información del sistema', 'catalogo70'); ?></h3>
            <table class="widefat striped" style="max-width:600px;">
                <tr><td><strong><?php esc_html_e('Plugin versión', 'catalogo70'); ?></strong></td><td><?php echo esc_html(CODECATALOGO_VERSION); ?></td></tr>
                <tr><td><strong>WordPress</strong></td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                <tr><td><strong>PHP</strong></td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                <tr><td><strong><?php esc_html_e('Email admin', 'catalogo70'); ?></strong></td><td><?php echo esc_html(get_option('admin_email')); ?></td></tr>
                <tr><td><strong><?php esc_html_e('Email plugin', 'catalogo70'); ?></strong></td><td><?php echo esc_html(get_option('codecatalogo_default_form_email', get_option('admin_email'))); ?></td></tr>
                <tr><td><strong><?php esc_html_e('Tabla contactos', 'catalogo70'); ?></strong></td><td><?php echo $table_exists ? '✅ ' . esc_html__('Existe', 'catalogo70') : '❌ ' . esc_html__('No existe', 'catalogo70'); ?></td></tr>
            </table>
            
            <h3><?php esc_html_e('Probar envío', 'catalogo70'); ?></h3>
            <form method="post" style="max-width:400px;">
                <table class="form-table">
                    <tr>
                        <th><label for="test_email"><?php esc_html_e('Email de prueba', 'catalogo70'); ?></label></th>
                        <td><input type="email" id="test_email" name="test_email" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>" required></td>
                    </tr>
                </table>
                <p><button type="submit" name="codecatalogo_test_mail" class="button button-primary"><?php esc_html_e('Enviar correo de prueba', 'catalogo70'); ?></button></p>
            </form>
        </div>
        <?php
    }

    /**
     * Renderizar página de Importar/Exportar
     */
    public function render_import_export_page() {
        include CODECATALOGO_PATH . 'admin/pages/import-export.php';
    }

    /**
     * Renderizar página de Licencia
     */
    public function render_license_page() {
        include CODECATALOGO_PATH . 'admin/pages/license.php';
    }

    /**
     * Renderizar página de Licencia Simple
     */
    public function render_license_simple_page() {
        include CODECATALOGO_PATH . 'admin/pages/license-simple.php';
    }
}
