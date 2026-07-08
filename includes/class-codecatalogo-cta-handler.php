<?php
/**
 * Gestor de CTAs (Call To Action)
 * Versión simplificada con configuración global de WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_CTA_Handler {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'codecatalogo_ctas';
        
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_codecatalogo_product', array($this, 'save_cta'), 10, 2);
        add_action('wp_ajax_codecatalogo_send_contact_form', array($this, 'handle_contact_form'));
        add_action('wp_ajax_nopriv_codecatalogo_send_contact_form', array($this, 'handle_contact_form'));
    }
    
    /**
     * Agregar meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'codecatalogo_product_cta',
            __('Call To Action (CTA)', 'catalogo70'),
            array($this, 'render_cta_meta_box'),
            'codecatalogo_product',
            'side',
            'default'
        );
    }
    
    /**
     * Renderizar meta box de CTA - SIMPLIFICADO
     */
    public function render_cta_meta_box($post) {
        wp_nonce_field('codecatalogo_save_cta', 'codecatalogo_cta_nonce');

        // Verificar licencia
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-license.php';
        $license_manager = new CodeCatalogo_License();
        $is_premium = $license_manager->is_premium();

        $cta = $this->get_product_cta($post->ID);

        // Valores
                $enable_whatsapp = $cta ? ($cta->cta_type === 'whatsapp' || $cta->cta_type === 'both') : true; // WhatsApp habilitado por defecto
        $enable_form = $cta ? ($cta->cta_type === 'form' || $cta->cta_type === 'both') : true; // Formulario siempre disponible
        $form_email = $cta ? $cta->form_email : get_option('codecatalogo_default_form_email', get_option('admin_email'));
        $form_subject = $cta ? $cta->form_subject : '';
        $form_cc = $cta ? $cta->form_cc : '';
        $cta_text_whatsapp = $cta && !empty($cta->cta_text) ? $cta->cta_text : esc_html__('Consultar WhatsApp', 'catalogo70');
        $cta_position = $cta ? $cta->cta_position : 'bottom';
        $show_on_card = $cta ? $cta->show_on_card : 1;
        $is_active = $cta ? $cta->is_active : 1;

        // Valores globales
        $global_whatsapp = get_option('codecatalogo_default_whatsapp', '');

        ?>
        <style>
            .codecatalogo-cta-field { margin-bottom: 15px; }
            .codecatalogo-cta-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .codecatalogo-cta-field input[type="text"],
            .codecatalogo-cta-field input[type="email"],
            .codecatalogo-cta-field textarea,
            .codecatalogo-cta-field select {
                width: 100%;
            }
            .codecatalogo-cta-section {
                padding: 12px;
                background: #f6f7f7;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .codecatalogo-cta-section h4 {
                margin: 0 0 10px 0;
                font-size: 13px;
                color: #1d2327;
            }
            .codecatalogo-global-notice {
                padding: 8px;
                background: #e7f5fe;
                border-left: 3px solid #0073aa;
                font-size: 12px;
                margin-top: 8px;
            }
            .codecatalogo-premium-feature {
                position: relative;
                opacity: 0.6;
            }
            .codecatalogo-premium-notice {
                padding: 10px;
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                margin-top: 10px;
                font-size: 12px;
            }
            .codecatalogo-premium-notice p {
                margin: 5px 0;
            }
            .codecatalogo-premium-notice .button {
                margin-top: 8px;
            }
        </style>
        
        <div class="codecatalogo-cta-wrapper">
            
            <!-- Estado General -->
            <div class="codecatalogo-cta-field">
                <label>
                    <input type="checkbox" name="codecatalogo_cta[is_active]" value="1" <?php checked($is_active, 1); ?>>
                    <strong><?php esc_html_e('Activar CTAs', 'catalogo70'); ?></strong>
                </label>
            </div>
            
            <hr>
            
            <!-- WhatsApp -->
            <div class="codecatalogo-cta-section">
                <div class="codecatalogo-cta-field">
                    <label>
                        <input type="checkbox" 
                               name="codecatalogo_cta[enable_whatsapp]" 
                               id="enable_whatsapp"
                               value="1" 
                               <?php checked($enable_whatsapp, true); ?>>
                        <strong><?php esc_html_e('Habilitar WhatsApp', 'catalogo70'); ?></strong>
                    </label>
                </div>
                
                <?php if (!empty($global_whatsapp)): ?>
                    <div class="codecatalogo-global-notice">
                        📱 <strong><?php esc_html_e('Número configurado:', 'catalogo70'); ?></strong><br>
                        <?php echo esc_html($global_whatsapp); ?>
                    </div>
                <?php else: ?>
                    <div class="codecatalogo-global-notice" style="background: #fcf0f1; border-color: #d63638;">
                        ⚠️ <?php esc_html_e('No hay número de WhatsApp configurado.', 'catalogo70'); ?><br>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-settings')); ?>">
                            <?php esc_html_e('Configurar ahora', 'catalogo70'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="codecatalogo-cta-field" style="margin-top: 10px;">
                    <label><?php esc_html_e('Texto del botón WhatsApp', 'catalogo70'); ?></label>
                    <input type="text" 
                           name="codecatalogo_cta[cta_text]" 
                           value="<?php echo esc_attr($cta_text_whatsapp); ?>" 
                           placeholder="<?php esc_html_e('Consulta por WhatsApp', 'catalogo70'); ?>">
                </div>
            </div>
            
                        <!-- Formulario -->
            <div class="codecatalogo-cta-section">
                <div class="codecatalogo-cta-field">
                    <label>
                        <input type="checkbox"
                               name="codecatalogo_cta[enable_form]"
                               id="enable_form"
                               value="1"
                               <?php checked($enable_form, true); ?>>
                        <strong><?php esc_html_e('Habilitar Formulario', 'catalogo70'); ?></strong>
                    </label>
                </div>

                <div class="codecatalogo-cta-field">
                    <label><?php esc_html_e('Email de destino', 'catalogo70'); ?></label>
                    <input type="email"
                           name="codecatalogo_cta[form_email]"
                           value="<?php echo esc_attr($form_email); ?>"
                           placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <p class="description"><?php esc_html_e('Dejar vacío para usar el email por defecto', 'catalogo70'); ?></p>
                </div>

                <div class="codecatalogo-cta-field">
                    <label><?php esc_html_e('Asunto del email', 'catalogo70'); ?></label>
                    <input type="text"
                           name="codecatalogo_cta[form_subject]"
                           value="<?php echo esc_attr($form_subject); ?>"
                           placeholder="<?php esc_html_e('Consulta sobre producto', 'catalogo70'); ?>">
                </div>

                <div class="codecatalogo-cta-field">
                    <label><?php esc_html_e('CC (opcional)', 'catalogo70'); ?></label>
                    <input type="text"
                           name="codecatalogo_cta[form_cc]"
                           value="<?php echo esc_attr($form_cc); ?>"
                           placeholder="email1@ejemplo.com, email2@ejemplo.com">
                </div>
            </div>
            
            <!-- Configuración Visual -->
            <div class="codecatalogo-cta-field">
                <label><?php esc_html_e('Posición de los botones', 'catalogo70'); ?></label>
                <select name="codecatalogo_cta[cta_position]">
                    <option value="top" <?php selected($cta_position, 'top'); ?>><?php esc_html_e('Arriba', 'catalogo70'); ?></option>
                    <option value="bottom" <?php selected($cta_position, 'bottom'); ?>><?php esc_html_e('Abajo (recomendado)', 'catalogo70'); ?></option>
                    <option value="both" <?php selected($cta_position, 'both'); ?>><?php esc_html_e('Arriba y Abajo', 'catalogo70'); ?></option>
                    <option value="floating" <?php selected($cta_position, 'floating'); ?>><?php esc_html_e('Flotante (fijo)', 'catalogo70'); ?></option>
                </select>
            </div>
            
            <div class="codecatalogo-cta-field">
                <label>
                    <input type="checkbox" name="codecatalogo_cta[show_on_card]" value="1" <?php checked($show_on_card, 1); ?>>
                    <?php esc_html_e('Mostrar en tarjeta del catálogo', 'catalogo70'); ?>
                </label>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Guardar CTA
     */
    public function save_cta($post_id, $post) {
        if (!isset($_POST['codecatalogo_cta_nonce']) ||
            !wp_verify_nonce($_POST['codecatalogo_cta_nonce'], 'codecatalogo_save_cta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['codecatalogo_cta'])) {
            return;
        }

        // Verificar licencia
        require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-license.php';
        $license_manager = new CodeCatalogo_License();
        $is_premium = $license_manager->is_premium();

        $cta_data = $_POST['codecatalogo_cta'];

                // Determinar tipo de CTA
        $enable_whatsapp = isset($cta_data['enable_whatsapp']) && $cta_data['enable_whatsapp'] == 1;
        $enable_form = isset($cta_data['enable_form']) && $cta_data['enable_form'] == 1;

        $cta_type = 'none';
        if ($enable_whatsapp && $enable_form) {
            $cta_type = 'both';
        } elseif ($enable_whatsapp) {
            $cta_type = 'whatsapp';
        } elseif ($enable_form) {
            $cta_type = 'form';
        }
        
        // Sanitizar datos
        $data = array(
            'product_id'        => $post_id,
            'cta_type'          => $cta_type,
            'whatsapp_number'   => '', // Usar global
            'whatsapp_message'  => '', // Usar global
            'form_email'        => sanitize_email($cta_data['form_email'] ?? ''),
            'form_subject'      => sanitize_text_field($cta_data['form_subject'] ?? ''),
            'form_cc'           => sanitize_text_field($cta_data['form_cc'] ?? ''),
            'cta_text'          => sanitize_text_field($cta_data['cta_text'] ?? ''),
            'cta_button_color'  => '#25D366',
            'cta_position'      => sanitize_text_field($cta_data['cta_position'] ?? 'bottom'),
            'show_on_card'      => isset($cta_data['show_on_card']) ? 1 : 0,
            'is_active'         => isset($cta_data['is_active']) ? 1 : 0,
        );
        
        global $wpdb;
        
        // Verificar si existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE product_id = %d",
            $post_id
        ));
        
        if ($exists) {
            $wpdb->update(
                $this->table_name,
                $data,
                array('product_id' => $post_id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'),
                array('%d')
            );
        } else {
            $wpdb->insert($this->table_name, $data);
        }
        
        // Limpiar caché
        wp_cache_delete('codecatalogo_cta_' . $post_id, 'codecatalogo');
    }
    
    /**
     * Obtener CTA de un producto
     */
    public function get_product_cta($product_id) {
        global $wpdb;
        
        $cache_key = 'codecatalogo_cta_' . $product_id;
        $cta = wp_cache_get($cache_key, 'codecatalogo');
        
        if (false === $cta) {
            $cta = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE product_id = %d AND is_active = 1",
                $product_id
            ));
            
            wp_cache_set($cache_key, $cta, 'codecatalogo', 1800);
        }
        
        return $cta;
    }
    
        /**
     * Renderizar botón CTA - CON data-product-name
     */
    public function render_cta_button($product_id, $context = 'single') {
        $cta = $this->get_product_cta($product_id);
        $product_title = get_the_title($product_id);
        $product_url = get_permalink($product_id);
        
        // Obtener configuración global de WhatsApp
        $global_whatsapp = get_option('codecatalogo_default_whatsapp', '');
        $global_message = get_option('codecatalogo_default_whatsapp_message', '');
        
        $output = '<div class="codecatalogo-cta-wrapper codecatalogo-cta-' . esc_attr($context) . '">';
        
        // WhatsApp (siempre que haya CTA activa con WhatsApp o both, o por defecto si hay número global)
        $show_whatsapp = false;
        $show_form = true; // Formulario siempre visible
        
        if ($cta && $cta->is_active && $cta->cta_type !== 'none') {
            if (in_array($cta->cta_type, array('whatsapp', 'both'))) {
                $show_whatsapp = true;
            }
            if (in_array($cta->cta_type, array('form', 'both'))) {
                $show_form = true;
            }
        }
        
        // WhatsApp
                if ($show_whatsapp && !empty($global_whatsapp)) {
            if (empty($global_message)) {
                $message = sprintf(
                    /* translators: %s: Product title */
                    esc_html__('Hola, me interesa el producto: %s', 'catalogo70'),
                    $product_title
                );
            } else {
                $message = sprintf(
                    /* translators: %s: Product title */
                    __('Hola, me interesa el producto: %s', 'catalogo70'),
                    $product_title
                ) . "\n\n" . $global_message;
            }
            
            $whatsapp_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $global_whatsapp) . '?text=' . urlencode($message);
            
            $button_text = ($cta && !empty($cta->cta_text)) ? $cta->cta_text : esc_html__('Consultar WhatsApp', 'catalogo70');
            
            $output .= '<a href="' . esc_url($whatsapp_url) . '" 
                           class="codecatalogo-cta-btn codecatalogo-whatsapp-btn" 
                           target="_blank"
                           rel="noopener noreferrer">';
            $output .= '<span class="dashicons dashicons-whatsapp"></span> ';
            $output .= esc_html($button_text);
            $output .= '</a>';
        }
        
        // Formulario - SIEMPRE visible
        if ($show_form) {
            $output .= '<button type="button" 
                               class="codecatalogo-cta-btn codecatalogo-form-btn" 
                               data-product-id="' . esc_attr($product_id) . '"
                               data-product-name="' . esc_attr($product_title) . '">';
            $output .= '<span class="dashicons dashicons-email"></span> ';
            $output .= __('Solicitar información', 'catalogo70');
            $output .= '</button>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
        /**
     * Manejar envío de formulario de contacto
     */
    public function handle_contact_form() {
        check_ajax_referer('codecatalogo_contact_form', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $product_name = sanitize_text_field($_POST['product_name']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        
        // Validar datos
        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error(array('message' => __('Por favor completa todos los campos requeridos.', 'catalogo70')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Por favor ingresa un email válido.', 'catalogo70')));
        }
        
        $product_url = get_permalink($product_id);
        
        // Obtener configuración (CTA del producto o valores por defecto)
        $cta = $this->get_product_cta($product_id);
        
        $to = '';
        $form_subject = '';
        $form_cc = '';
        
        if ($cta) {
            $to = !empty($cta->form_email) ? $cta->form_email : get_option('codecatalogo_default_form_email', get_option('admin_email'));
            $form_subject = $cta->form_subject;
            $form_cc = $cta->form_cc;
        } else {
            $to = get_option('codecatalogo_default_form_email', get_option('admin_email'));
        }
        
                // Asunto simple - sin caracteres especiales
        if (!empty($form_subject)) {
            $subject = $form_subject . ' - ' . $product_name;
        } else {
            $subject = 'Consulta sobre: ' . $product_name;
        }
        
                // Cuerpo del email - sin funciones de traducción que puedan causar problemas
        $body = "Nueva consulta desde el catálogo\n\n";
        $body .= "=== INFORMACIÓN DEL PRODUCTO ===\n";
        $body .= "Producto: " . $product_name . "\n";
        $body .= "URL: " . $product_url . "\n\n";
        $body .= "=== DATOS DEL CONTACTO ===\n";
        $body .= "Nombre: " . $name . "\n";
        $body .= "Email: " . $email . "\n";
        $body .= "Teléfono: " . (!empty($phone) ? $phone : 'No especificado') . "\n\n";
        $body .= "=== MENSAJE ===\n";
        $body .= $message . "\n\n";
        $body .= "---\n";
        $body .= "Este mensaje fue enviado desde: " . get_bloginfo('name') . "\n";
        $body .= "Fecha: " . current_time('mysql');
        
                // HEADERS IGUAL QUE EL TEST - SIN From personalizado
                $headers = array(
                    'Content-Type: text/plain; charset=UTF-8',
                );
        
                // Agregar CC si existe
                if (!empty($form_cc)) {
                    $cc_emails = array_map('trim', explode(',', $form_cc));
                    foreach ($cc_emails as $cc_email) {
                        if (is_email($cc_email)) {
                            $headers[] = 'Cc: ' . $cc_email;
                        }
                    }
                }
        
                // Intentar enviar con wp_mail() (SIN Reply-To para evitar bloqueos)
                $sent = wp_mail($to, $subject, $body, $headers);
        
                // GUARDAR SIEMPRE en base de datos como respaldo
                global $wpdb;
                $contacts_table = $wpdb->prefix . 'codecatalogo_contacts';
                $wpdb->insert($contacts_table, array(
                    'product_id'     => $product_id,
                    'product_name'   => $product_name,
                    'contact_name'   => $name,
                    'contact_email'  => $email,
                    'contact_phone'  => $phone,
                    'contact_message'=> $message,
                    'is_read'        => 0,
                    'created_at'     => current_time('mysql'),
                ));
        
        // Debug: Log del intento de envío
        error_log('CodeCatalogo - Intento de envío de formulario:');
        error_log('  To: ' . $to);
        error_log('  Subject: ' . $subject);
        error_log('  Sent: ' . ($sent ? 'SI' : 'NO (guardado en BD)'));
        
        if ($sent) {
            wp_send_json_success(array('message' => __('¡Mensaje enviado correctamente! Nos pondremos en contacto pronto.', 'catalogo70')));
        } else {
            wp_send_json_error(array('message' => __('Error al enviar el mensaje. Por favor intenta nuevamente.', 'catalogo70')));
        }
    }
}

// Inicializar
new CodeCatalogo_CTA_Handler();
