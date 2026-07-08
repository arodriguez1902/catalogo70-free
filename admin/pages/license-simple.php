<?php
/**
 * Página de activación de licencia - VERSIÓN SIMPLIFICADA
 */

if (!defined('ABSPATH')) {
    exit;
}

$message = '';
$message_type = '';

// ACTIVAR LICENCIA DIRECTAMENTE
if (isset($_POST['activate_now'])) {
    check_admin_referer('activate_now', 'nonce');

    $license_key = strtoupper(trim(sanitize_text_field($_POST['license_key'])));
    $domain = wp_parse_url(get_site_url(), PHP_URL_HOST);

    // NO limpiar - enviar tal cual con guiones (como Postman)
    // La API espera el formato: XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX

    // Preparar request IGUAL que Postman
    $api_url = 'https://coders.codigo70.com/api/activar.php';
    $request_body = json_encode(array(
        'license_key' => $license_key,
        'dominio' => $domain
    ));

    // Hacer request a la API
    $response = wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => $request_body,
        'timeout' => 15,
        'sslverify' => true
    ));

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Mostrar lo que se envió (para debug)
    $debug_info = array(
        'license_key' => $license_key,
        'dominio' => $domain,
        'api_url' => $api_url,
        'request_body' => json_decode($request_body, true),
        'status_code' => $status_code,
        'response_body' => $body
    );

    // Debug logs
    error_log('=== ACTIVACIÓN DIRECTA ===');
    error_log('License key original: ' . $license_key);
    error_log('License key limpio: ' . $clean_key);
    error_log('Dominio: ' . $domain);
    error_log('Status Code: ' . $status_code);
    error_log('Response: ' . print_r($body, true));

    // Procesar respuesta
    if ($status_code === 200 && isset($body['success']) && $body['success']) {
        // GUARDAR LICENCIA ACTIVA
        update_option('codecatalogo_license_key', strtoupper($license_key));
        update_option('codecatalogo_license_status', 'active');
        update_option('codecatalogo_license_data', $body['data'] ?? array());
        update_option('codecatalogo_license_last_check', time());

        $message = '✅ ¡Licencia activada exitosamente!';
        $message_type = 'success';
    } else {
        $error_msg = isset($body['message']) ? $body['message'] : 'Error desconocido';
        $message = '❌ Error: ' . $error_msg . ' (Status: ' . $status_code . ')';
        $message_type = 'error';
    }
}

// DESACTIVAR LICENCIA
if (isset($_POST['deactivate_now'])) {
    check_admin_referer('deactivate_now', 'nonce');

    delete_option('codecatalogo_license_key');
    delete_option('codecatalogo_license_status');
    delete_option('codecatalogo_license_data');
    delete_option('codecatalogo_license_last_check');

    $message = 'Licencia desactivada';
    $message_type = 'success';
}

// Estado actual
$is_active = get_option('codecatalogo_license_status') === 'active';
$current_key = get_option('codecatalogo_license_key');
$license_data = get_option('codecatalogo_license_data');
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        .license-container {
            max-width: 800px;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .license-active {
            padding: 20px;
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            margin: 20px 0;
        }
        .license-inactive {
            padding: 20px;
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            margin: 20px 0;
        }
        .big-input {
            width: 100%;
            padding: 12px;
            font-size: 18px;
            font-family: monospace;
            text-transform: uppercase;
            margin: 10px 0;
        }
        .big-button {
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            color: white;
        }
        .btn-green { background: #28a745; }
        .btn-green:hover { background: #218838; }
        .btn-red { background: #dc3545; }
        .btn-red:hover { background: #c82333; }
        .notice-success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .notice-error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>🔐 Activación de Licencia - VERSIÓN SIMPLE</h1>

        <?php if ($message): ?>
                        <div class="notice-<?php echo esc_attr($message_type); ?>">
                <strong><?php echo esc_html($message); ?></strong>
            </div>
        <?php endif; ?>

        <?php if (isset($debug_info)): ?>
            <div style="background: #f0f0f0; padding: 20px; border: 2px solid #333; margin: 20px 0; font-family: monospace; font-size: 12px;">
                <h3 style="margin-top: 0; color: #d63638;">🔍 DEBUG - Lo que se envió a la API:</h3>
                <pre style="background: white; padding: 15px; overflow-x: auto;"><?php echo esc_html(json_encode($debug_info, JSON_PRETTY_PRINT)); ?></pre>
                <p style="margin-bottom: 0;"><strong>COPIA esto y compáralo con lo que envías desde Postman</strong></p>
            </div>
        <?php endif; ?>

        <div class="license-container">
            <h2>Estado Actual</h2>
            <p><strong>Dominio:</strong> <?php echo esc_html(wp_parse_url(get_site_url(), PHP_URL_HOST)); ?></p>

            <?php if ($is_active): ?>
                <div class="license-active">
                    <h2 style="color: #28a745; margin-top: 0;">✅ LICENCIA ACTIVA</h2>
                    <p><strong>License Key:</strong> <code><?php echo esc_html($current_key); ?></code></p>
                    <?php if ($license_data && isset($license_data['fecha_expiracion'])): ?>
                        <p><strong>Expira:</strong> <?php echo esc_html($license_data['fecha_expiracion']); ?></p>
                    <?php endif; ?>

                    <form method="post" style="margin-top: 20px;">
                        <?php wp_nonce_field('deactivate_now', 'nonce'); ?>
                        <button type="submit" name="deactivate_now" class="big-button btn-red"
                                onclick="return confirm('¿Seguro que deseas desactivar?')">
                            Desactivar Licencia
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="license-inactive">
                    <h2 style="color: #856404; margin-top: 0;">⚠️ SIN LICENCIA ACTIVA</h2>

                    <form method="post">
                        <?php wp_nonce_field('activate_now', 'nonce'); ?>

                        <label style="font-weight: bold; font-size: 16px;">
                            Ingresa tu License Key:
                        </label>
                        <input type="text"
                               name="license_key"
                               value="6100CB28-BF8ACEA7-C70B1CF1-AEFF4D3E"
                               class="big-input"
                               required>

                        <p style="color: #666; margin: 10px 0;">
                            Puedes ingresar el license key con o sin guiones. El sistema lo procesará automáticamente.
                        </p>

                        <button type="submit" name="activate_now" class="big-button btn-green">
                            🚀 ACTIVAR LICENCIA AHORA
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="license-container" style="background: #f8f9fa;">
            <h3>ℹ️ Información</h3>
            <ul>
                <li>Esta es una versión simplificada que NO usa la clase de licencias</li>
                <li>Conecta DIRECTAMENTE con la API de validación</li>
                <li>No tiene validaciones de formato complicadas</li>
                <li>Si funciona desde Postman, funcionará aquí</li>
            </ul>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-license')); ?>">← Volver a la página principal de licencias</a></p>
        </div>
    </div>
</body>
</html>
