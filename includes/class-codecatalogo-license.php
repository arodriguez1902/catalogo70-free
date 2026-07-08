<?php
/**
 * Sistema de gestión de licencias
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_License {

    /**
     * Versión del sistema de licencias (para verificar actualizaciones)
     */
    const LICENSE_SYSTEM_VERSION = '4.0-fixed-final';

    /**
     * URL base de la API de licencias
     *
          * PRODUCCIÓN: https://coders.codigo70.com/api
     */
    private $api_url = 'https://coders.codigo70.com/api';

    /**
     * Opciones del plugin
     */
    private $option_license_key = 'codecatalogo_license_key';
    private $option_license_status = 'codecatalogo_license_status';
    private $option_license_data = 'codecatalogo_license_data';
    private $option_last_check = 'codecatalogo_license_last_check';

    /**
     * Límites para versión FREE
     */
    const FREE_LIMIT_PRODUCTS = 10;
    const FREE_LIMIT_CATEGORIES = 10;
    const FREE_LIMIT_FIELDS = 5;
    const FREE_LIMIT_IMAGES = 3;

    /**
     * Intervalo de validación (7 días en segundos)
     */
    const VALIDATION_INTERVAL = 604800; // 7 días

    public function __construct() {
        // Hook para validación automática semanal
        add_action('admin_init', array($this, 'maybe_validate_license'));

        // Hook para mostrar avisos
        add_action('admin_notices', array($this, 'show_license_notices'));

        // Hooks para validar límites en versión FREE
        add_action('wp_insert_post', array($this, 'check_product_limit'), 10, 3);
        add_action('create_term', array($this, 'check_category_limit'), 10, 3);
    }

    /**
     * Validar licencia automáticamente si ha pasado el intervalo
     */
    public function maybe_validate_license() {
        $last_check = get_option($this->option_last_check, 0);
        $time_elapsed = time() - $last_check;

        // Solo validar si han pasado 7 días
        if ($time_elapsed >= self::VALIDATION_INTERVAL) {
            $license_key = get_option($this->option_license_key);

            if (!empty($license_key)) {
                $this->validate_license($license_key);
            }
        }
    }

    /**
     * Activar licencia
     *
     * @param string $license_key
     * @return array
     */
    public function activate_license($license_key) {
        // Limpiar y normalizar el license key
        $license_key = strtoupper(trim($license_key));

        // DEBUG: Log del license key original
        error_log('=== CodeCatalogo License Activation Debug ===');
        error_log('License key received: "' . $license_key . '"');
        error_log('License key length: ' . strlen($license_key));

        // Validación básica: no puede estar vacío
        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('Por favor ingresa un license key', 'catalogo70')
            );
        }

        $domain = $this->get_domain();

        // Advertencia para localhost
        if ($domain === 'localhost' || $domain === '127.0.0.1') {
            return array(
                'success' => false,
                'message' => __('No puedes activar una licencia en localhost. Por favor, usa un dominio real o configura un dominio de prueba en tu archivo hosts (ej: midominio.local). Para desarrollo local, cambia la URL de la API en el archivo de licencias.', 'catalogo70')
            );
        }

        // IMPORTANTE: La API espera el license key CON guiones y EN MAYÚSCULAS
        // NO limpiar ni modificar el formato
        $request_data = array(
            'license_key' => $license_key,
            'dominio' => $domain
        );

        // Debug: Log request data
        error_log('CodeCatalogo License Activation Request:');
        error_log('License Key (with dashes): ' . $license_key);
        error_log('License Key (without dashes, lowercase): ' . $license_key_without_dashes);
        error_log('License Key Length: ' . strlen($license_key_without_dashes));
        error_log('Domain: ' . $domain);
        error_log('Request Body: ' . json_encode($request_data, JSON_PRETTY_PRINT));

        $response = wp_remote_post($this->api_url . '/activar.php', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data),
            'timeout' => 15,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                                'message' => sprintf(
                    /* translators: %s: Error message from the connection attempt */
                    __('Error de conexión: %s', 'catalogo70'),
                    $response->get_error_message()
                )
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        // Debug logging
        error_log('CodeCatalogo License Activation Debug:');
        error_log('Status Code: ' . $status_code);
        error_log('Response Body: ' . wp_remote_retrieve_body($response));
        error_log('Decoded Body: ' . print_r($body, true));

        if ($status_code !== 200 || !isset($body['success'])) {
            // Mensajes específicos para códigos de error comunes
            $error_messages = array(
                429 => __('Demasiadas peticiones. Por favor espera 15 minutos antes de volver a intentar. Si el problema persiste, contacta con soporte.', 'catalogo70'),
                403 => __('Acceso denegado. Verifica que el license key sea correcto y esté asignado a este dominio.', 'catalogo70'),
                404 => __('Endpoint de la API no encontrado. Contacta con soporte.', 'catalogo70'),
                500 => __('Error en el servidor de licencias. Por favor intenta más tarde o contacta con soporte.', 'catalogo70'),
            );

            $default_message = isset($body['message']) && !empty($body['message'])
                ? $body['message']
                : (isset($error_messages[$status_code])
                    ? $error_messages[$status_code]
                    : __('Error desconocido al activar la licencia', 'catalogo70') . ' (Status: ' . $status_code . ')');

            return array(
                'success' => false,
                'message' => $default_message
            );
        }

        if ($body['success']) {
            // Guardar licencia activada
            update_option($this->option_license_key, $license_key);
            update_option($this->option_license_status, 'active');
            update_option($this->option_license_data, $body['data']);
            update_option($this->option_last_check, time());

            return array(
                'success' => true,
                'message' => __('Licencia activada exitosamente', 'catalogo70'),
                'data' => $body['data']
            );
        }

        return array(
            'success' => false,
            'message' => $body['message'] ?? __('No se pudo activar la licencia', 'catalogo70')
        );
    }

    /**
     * Validar licencia
     *
     * @param string|null $license_key
     * @return array
     */
    public function validate_license($license_key = null) {
        if (!$license_key) {
            $license_key = get_option($this->option_license_key);
        }

        if (!$license_key) {
            return array(
                'success' => false,
                'message' => __('No hay license key configurado', 'catalogo70')
            );
        }

        $domain = $this->get_domain();

        // IMPORTANTE: La API espera el license key CON guiones y EN MAYÚSCULAS
        $response = wp_remote_post($this->api_url . '/validar.php', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'dominio' => $domain
            )),
            'timeout' => 10,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            // En caso de error de conexión, mantener el estado actual
            return array(
                'success' => false,
                                'message' => sprintf(
                    /* translators: %s: Error message from the connection attempt */
                    __('Error de conexión: %s', 'catalogo70'),
                    $response->get_error_message()
                ),
                'cached' => true
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        // Actualizar timestamp del último chequeo
        update_option($this->option_last_check, time());

        if ($status_code === 200 && isset($body['success']) && $body['success']) {
            // Licencia válida
            update_option($this->option_license_status, 'active');
            update_option($this->option_license_data, $body['data']);

            return array(
                'success' => true,
                'message' => __('Licencia válida', 'catalogo70'),
                'data' => $body['data']
            );
        } else {
            // Licencia inválida
            update_option($this->option_license_status, 'invalid');
            update_option($this->option_license_data, null);

            return array(
                'success' => false,
                'message' => $body['message'] ?? __('Licencia inválida', 'catalogo70')
            );
        }
    }

    /**
     * Desactivar licencia
     *
     * @return array
     */
    public function deactivate_license() {
        $license_key = get_option($this->option_license_key);

        if (!$license_key) {
            return array(
                'success' => false,
                'message' => __('No hay licencia para desactivar', 'catalogo70')
            );
        }

        $domain = $this->get_domain();

        // IMPORTANTE: La API espera el license key CON guiones y EN MAYÚSCULAS
        $response = wp_remote_post($this->api_url . '/desactivar.php', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'dominio' => $domain
            )),
            'timeout' => 10,
            'sslverify' => true
        ));

        // Limpiar opciones locales independientemente del resultado de la API
        delete_option($this->option_license_key);
        delete_option($this->option_license_status);
        delete_option($this->option_license_data);
        delete_option($this->option_last_check);

        if (is_wp_error($response)) {
            return array(
                'success' => true,
                'message' => __('Licencia desactivada localmente (no se pudo contactar con el servidor)', 'catalogo70')
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'success' => true,
            'message' => __('Licencia desactivada exitosamente', 'catalogo70')
        );
    }

    /**
     * Verificar si el usuario tiene licencia Premium activa
     *
     * @return bool
     */
    public function is_premium() {
        $status = get_option($this->option_license_status, 'invalid');
        return $status === 'active';
    }

    /**
     * Obtener datos de la licencia
     *
     * @return array|null
     */
    public function get_license_data() {
        return get_option($this->option_license_data);
    }

    /**
     * Obtener estado de la licencia
     *
     * @return string
     */
    public function get_license_status() {
        return get_option($this->option_license_status, 'invalid');
    }

    /**
     * Verificar si se puede crear más productos (límite FREE)
     *
     * @return bool
     */
    public function can_create_product() {
        if ($this->is_premium()) {
            return true;
        }

        $count = wp_count_posts('codecatalogo_product');
        $total = $count->publish + $count->draft + $count->pending;

        return $total < self::FREE_LIMIT_PRODUCTS;
    }

    /**
     * Verificar si se puede crear más categorías (límite FREE)
     *
     * @return bool
     */
    public function can_create_category() {
        if ($this->is_premium()) {
            return true;
        }

                $count = wp_count_terms(array(
            'taxonomy' => 'codecatalogo_cat',
            'hide_empty' => false,
        ));
        if (is_wp_error($count)) {
            return true; // Permitir creación si hay error (probablemente taxonomía no existe aún)
        }

        return $count < self::FREE_LIMIT_CATEGORIES;
    }

    /**
     * Verificar si se puede crear más campos personalizados (límite FREE)
     *
     * @return bool
     */
    public function can_create_field() {
        if ($this->is_premium()) {
            return true;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'codecatalogo_fields';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if (!$table_exists) {
            return true; // Permitir creación si la tabla no existe aún
        }

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return $count < self::FREE_LIMIT_FIELDS;
    }

    /**
     * Obtener contadores actuales
     *
     * @return array
     */
    public function get_usage_stats() {
        global $wpdb;

        // Contar productos
        $products_count = wp_count_posts('codecatalogo_product');
        $total_products = 0;
        if ($products_count) {
            $total_products = ($products_count->publish ?? 0) + ($products_count->draft ?? 0) + ($products_count->pending ?? 0);
        }

        // Contar categorías (puede devolver WP_Error si la taxonomía no existe)
                $categories_count = wp_count_terms(array(
            'taxonomy' => 'codecatalogo_cat',
            'hide_empty' => false,
        ));
        if (is_wp_error($categories_count)) {
            $categories_count = 0;
        }

        // Contar campos (verificar si la tabla existe primero)
        $fields_table = $wpdb->prefix . 'codecatalogo_fields';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$fields_table}'");
        $fields_count = 0;
        if ($table_exists) {
            $fields_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$fields_table}");
        }

        return array(
            'products' => array(
                'current' => $total_products,
                'limit' => $this->is_premium() ? __('Ilimitado', 'catalogo70') : self::FREE_LIMIT_PRODUCTS,
                'percentage' => $this->is_premium() ? 0 : ($total_products / self::FREE_LIMIT_PRODUCTS) * 100
            ),
            'categories' => array(
                'current' => $categories_count,
                'limit' => $this->is_premium() ? __('Ilimitado', 'catalogo70') : self::FREE_LIMIT_CATEGORIES,
                'percentage' => $this->is_premium() ? 0 : ($categories_count / self::FREE_LIMIT_CATEGORIES) * 100
            ),
            'fields' => array(
                'current' => $fields_count,
                'limit' => $this->is_premium() ? __('Ilimitado', 'catalogo70') : self::FREE_LIMIT_FIELDS,
                'percentage' => $this->is_premium() ? 0 : ($fields_count / self::FREE_LIMIT_FIELDS) * 100
            )
        );
    }

        /**
     * Mostrar avisos de licencia en el admin
     */
    public function show_license_notices() {
        // Solo en Free
        if ($this->is_premium()) {
            return;
        }

        $stats = $this->get_usage_stats();
        
                echo '<div class="notice notice-info is-dismissible">';
        echo '<p>';
        echo '<strong>' . esc_html__('CodeCatalogo Pro [FREE]', 'catalogo70') . '</strong> &nbsp;|&nbsp; ';
        
        $labels = array(
            'products' => esc_html__('Productos', 'catalogo70'),
            'categories' => esc_html__('Categorías', 'catalogo70'),
            'fields' => esc_html__('Campos', 'catalogo70'),
        );
        
        $parts = array();
        foreach ($stats as $type => $data) {
            if (!is_numeric($data['limit'])) continue;
            $parts[] = sprintf(
                '<strong>%s:</strong> %d/%d',
                $labels[$type] ?? $type,
                $data['current'],
                $data['limit']
            );
        }
        
        echo wp_kses_post(implode(' | ', $parts));
        echo ' &nbsp; <a href="' . esc_url(admin_url('admin.php?page=codecatalogo-license')) . '" class="button button-small">';
        echo esc_html__('Actualizar a Premium', 'catalogo70');
        echo '</a>';
        echo '</p></div>';
    }

    /**
     * Obtener dominio actual
     *
     * @return string
     */
    private function get_domain() {
        return wp_parse_url(get_site_url(), PHP_URL_HOST);
    }

    /**
     * Obtener información del sitio
     *
     * @return array
     */
    private function get_site_info() {
        global $wp_version;

        return array(
            'nombre' => get_bloginfo('name'),
            'url' => get_site_url(),
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'plugin_version' => CODECATALOGO_VERSION,
            'servidor_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown'
        );
    }

    /**
     * Sanitizar license key
     *
     * @param string $license_key
     * @return string
     */
    private function sanitize_license_key($license_key) {
        // Remover espacios y convertir a mayúsculas
        $license_key = strtoupper(trim($license_key));

        // Normalizar: Eliminar TODOS los caracteres que no sean A-Z o 0-9 (incluyendo guiones)
        $clean_key = preg_replace('/[^A-Z0-9]/', '', $license_key);

        // Debe tener exactamente 32 caracteres
        if (strlen($clean_key) !== 32) {
            error_log('CodeCatalogo License Error: Invalid length. Expected 32, got ' . strlen($clean_key) . '. Clean key: ' . $clean_key);
            return '';
        }

        // Reformatear al formato correcto: XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX
        $formatted_key = substr($clean_key, 0, 8) . '-' .
                        substr($clean_key, 8, 8) . '-' .
                        substr($clean_key, 16, 8) . '-' .
                        substr($clean_key, 24, 8);

        error_log('CodeCatalogo License: Normalized key from "' . $license_key . '" to "' . $formatted_key . '"');

        return $formatted_key;
    }

    /**
     * Verificar límite de productos al intentar crear uno nuevo
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     */
    public function check_product_limit($post_id, $post, $update) {
        // Solo verificar para nuevos productos (no actualizaciones)
        if ($update || $post->post_type !== 'codecatalogo_product') {
            return;
        }

        // No verificar en autosave o revisiones
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Verificar límite
        if (!$this->can_create_product()) {
            // Eliminar el post que se acaba de crear
            wp_delete_post($post_id, true);

                        // Mensaje de error
                                    wp_die(
                            '<h1>' . esc_html__('Límite Alcanzado', 'catalogo70') . '</h1>' .
                            '<p>' . sprintf(
                                /* translators: %d: Maximum number of products allowed in the free version */
                                esc_html__('Has alcanzado el límite de %d productos en la versión Free.', 'catalogo70'),
                                intval(self::FREE_LIMIT_PRODUCTS)
                            ) . '</p>' .
                            '<p><a href="' . esc_url(admin_url('admin.php?page=codecatalogo-license')) . '" class="button button-primary">' .
                            esc_html__('Actualiza a Premium', 'catalogo70') .
                            '</a> ' .
                            '<a href="' . esc_url(admin_url('edit.php?post_type=codecatalogo_product')) . '" class="button">' .
                            esc_html__('Volver a Productos', 'catalogo70') .
                            '</a></p>',
                            esc_html__('Límite Alcanzado - CodeCatalogo Pro', 'catalogo70'),
                            array('response' => 403, 'back_link' => true)
                        );
        }
    }

    /**
     * Verificar límite de categorías al intentar crear una nueva
     *
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     */
    public function check_category_limit($term_id, $tt_id, $taxonomy) {
        // Solo verificar para categorías de productos
        if ($taxonomy !== 'codecatalogo_cat') {
            return;
        }

        // Verificar límite
        if (!$this->can_create_category()) {
            // Eliminar la categoría que se acaba de crear
            wp_delete_term($term_id, $taxonomy);

                        // Mensaje de error
                                    wp_die(
                            '<h1>' . esc_html__('Límite Alcanzado', 'catalogo70') . '</h1>' .
                            '<p>' . sprintf(
                                /* translators: %d: Maximum number of categories allowed in the free version */
                                esc_html__('Has alcanzado el límite de %d categorías en la versión Free.', 'catalogo70'),
                                intval(self::FREE_LIMIT_CATEGORIES)
                            ) . '</p>' .
                            '<p><a href="' . esc_url(admin_url('admin.php?page=codecatalogo-license')) . '" class="button button-primary">' .
                            esc_html__('Actualiza a Premium', 'catalogo70') .
                            '</a> ' .
                            '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=codecatalogo_cat&post_type=codecatalogo_product')) . '" class="button">' .
                            esc_html__('Volver a Categorías', 'catalogo70') .
                            '</a></p>',
                            esc_html__('Límite Alcanzado - CodeCatalogo Pro', 'catalogo70'),
                            array('response' => 403, 'back_link' => true)
                        );
        }
    }
}
