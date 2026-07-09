<?php
/**
 * Configuración - Versión FREE
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_Settings {

    private $field_manager;

    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
    }

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
    }

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

    public function render_fields_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $total_fields = count($this->field_manager->get_all_fields());
        $can_create = $total_fields < CodeCatalogo_Field_Manager::FREE_MAX_FIELDS;
        
        if (isset($_POST['codecatalogo_save_field_nonce'])) {
            if (wp_verify_nonce($_POST['codecatalogo_save_field_nonce'], 'codecatalogo_save_field')) {
                if (!$can_create) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(
                        esc_html__('Límite alcanzado: máximo %d campos en la versión gratuita.', 'catalogo70'),
                        CodeCatalogo_Field_Manager::FREE_MAX_FIELDS
                    ) . '</p></div>';
                } else {
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
        }
        if (isset($_GET['action']) && $_GET['action'] === 'new') {
            $this->render_field_form($can_create);
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>
                <?php esc_html_e('Gestiona los campos personalizados de tus productos.', 'catalogo70'); ?>
                <strong><?php printf(esc_html__('(%d/%d campos utilizados)', 'catalogo70'), $total_fields, CodeCatalogo_Field_Manager::FREE_MAX_FIELDS); ?></strong>
            </p>
            <p>
                <?php if ($can_create): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields&action=new')); ?>" class="button button-primary"><?php esc_html_e('Agregar Campo', 'catalogo70'); ?></a>
                <?php else: ?>
                    <button class="button" disabled><?php esc_html_e('Límite alcanzado (máx. 10 campos)', 'catalogo70'); ?></button>
                <?php endif; ?>
            </p>
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
                    <?php foreach ($this->field_manager->get_all_fields() as $field): ?>
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

    private function render_field_form($can_create) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agregar Campo', 'catalogo70'); ?></h1>
            <?php if (!$can_create): ?>
                <div class="notice notice-warning"><p><?php printf(esc_html__('Has alcanzado el límite de %d campos de la versión gratuita.', 'catalogo70'), CodeCatalogo_Field_Manager::FREE_MAX_FIELDS); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields')); ?>">
                <?php wp_nonce_field('codecatalogo_save_field', 'codecatalogo_save_field_nonce'); ?>
                <table class="form-table">
                    <tr><th><label for="field_label"><?php esc_html_e('Etiqueta', 'catalogo70'); ?></label></th><td><input type="text" id="field_label" name="field_label" class="regular-text" required placeholder="<?php esc_attr_e('Ej: Capacidad, Potencia', 'catalogo70'); ?>"></td></tr>
                    <tr><th><label for="field_name"><?php esc_html_e('Nombre interno', 'catalogo70'); ?></label></th><td><input type="text" id="field_name" name="field_name" class="regular-text" required placeholder="<?php esc_attr_e('Ej: capacidad, potencia', 'catalogo70'); ?>"><p class="description"><?php esc_html_e('Solo minúsculas, sin espacios.', 'catalogo70'); ?></p></td></tr>
                    <tr><th><label for="field_type"><?php esc_html_e('Tipo', 'catalogo70'); ?></label></th><td><select id="field_type" name="field_type"><option value="text"><?php esc_html_e('Texto', 'catalogo70'); ?></option><option value="number"><?php esc_html_e('Número', 'catalogo70'); ?></option><option value="textarea"><?php esc_html_e('Texto largo', 'catalogo70'); ?></option><option value="file"><?php esc_html_e('Archivo', 'catalogo70'); ?></option><option value="url"><?php esc_html_e('URL', 'catalogo70'); ?></option></select></td></tr>
                    <tr><th><?php esc_html_e('Opciones', 'catalogo70'); ?></th><td><label><input type="checkbox" name="show_in_card" value="1" checked> <?php esc_html_e('Mostrar en tarjeta', 'catalogo70'); ?></label><br><label><input type="checkbox" name="show_in_filter" value="1"> <?php esc_html_e('Usar como filtro', 'catalogo70'); ?></label><br><label><input type="checkbox" name="is_seo_relevant" value="1"> <?php esc_html_e('Relevante para SEO', 'catalogo70'); ?></label><br><label><input type="checkbox" name="is_required" value="1"> <?php esc_html_e('Campo obligatorio', 'catalogo70'); ?></label></td></tr>
                </table>
                <p>
                    <?php if ($can_create): ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Guardar Campo', 'catalogo70'); ?></button>
                    <?php else: ?>
                        <button type="submit" class="button" disabled><?php esc_html_e('Límite alcanzado', 'catalogo70'); ?></button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-fields')); ?>" class="button"><?php esc_html_e('Cancelar', 'catalogo70'); ?></a>
                </p>
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
}
