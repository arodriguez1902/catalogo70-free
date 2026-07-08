<?php
/**
 * Página de gestión de licencias
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener instancia de la clase de licencias
require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-license.php';
$license_manager = new CodeCatalogo_License();

// Procesar acciones
$message = '';
$message_type = '';

if (isset($_POST['codecatalogo_activate_license'])) {
    check_admin_referer('codecatalogo_activate_license', 'codecatalogo_license_nonce');

    $license_key = sanitize_text_field($_POST['license_key']);
    $result = $license_manager->activate_license($license_key);

    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

if (isset($_POST['codecatalogo_deactivate_license'])) {
    check_admin_referer('codecatalogo_deactivate_license', 'codecatalogo_license_nonce');

    $result = $license_manager->deactivate_license();

    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

if (isset($_POST['codecatalogo_validate_license'])) {
    check_admin_referer('codecatalogo_validate_license', 'codecatalogo_license_nonce');

    $result = $license_manager->validate_license();

    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// Obtener datos actuales
$is_premium = $license_manager->is_premium();
$license_data = $license_manager->get_license_data();
$license_key = get_option('codecatalogo_license_key');
$usage_stats = $license_manager->get_usage_stats();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>


    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="codecatalogo-license-page">
        <div class="codecatalogo-license-grid">

            <!-- Estado de la licencia -->
            <div class="codecatalogo-license-box">
                <h2><?php esc_html_e('Estado de la Licencia', 'catalogo70'); ?></h2>

                <?php if ($is_premium): ?>
                    <div class="codecatalogo-license-status active">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <h3><?php esc_html_e('Licencia Premium Activa', 'catalogo70'); ?></h3>
                    </div>

                    <?php if ($license_data): ?>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <th><?php esc_html_e('License Key:', 'catalogo70'); ?></th>
                                    <td><code><?php echo esc_html($license_key); ?></code></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Tipo:', 'catalogo70'); ?></th>
                                    <td><strong><?php echo esc_html(ucfirst($license_data['tipo'] ?? 'premium')); ?></strong></td>
                                </tr>
                                <?php if (isset($license_data['fecha_expiracion'])): ?>
                                <tr>
                                    <th><?php esc_html_e('Expira:', 'catalogo70'); ?></th>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license_data['fecha_expiracion']))); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (isset($license_data['producto']['nombre'])): ?>
                                <tr>
                                    <th><?php esc_html_e('Producto:', 'catalogo70'); ?></th>
                                    <td><?php echo esc_html($license_data['producto']['nombre']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <div class="codecatalogo-license-actions">
                        <form method="post" style="display: inline-block; margin-right: 10px;">
                            <?php wp_nonce_field('codecatalogo_validate_license', 'codecatalogo_license_nonce'); ?>
                            <button type="submit" name="codecatalogo_validate_license" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Revalidar Licencia', 'catalogo70'); ?>
                            </button>
                        </form>

                        <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php esc_attr_e('¿Estás seguro de que deseas desactivar la licencia?', 'catalogo70'); ?>');">
                            <?php wp_nonce_field('codecatalogo_deactivate_license', 'codecatalogo_license_nonce'); ?>
                            <button type="submit" name="codecatalogo_deactivate_license" class="button button-link-delete">
                                <?php esc_html_e('Desactivar Licencia', 'catalogo70'); ?>
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="codecatalogo-license-status inactive">
                        <span class="dashicons dashicons-warning"></span>
                        <h3><?php esc_html_e('Versión Free', 'catalogo70'); ?></h3>
                        <p><?php esc_html_e('Activa tu licencia Premium para desbloquear todas las funcionalidades.', 'catalogo70'); ?></p>
                    </div>

                    <form method="post">
                        <?php wp_nonce_field('codecatalogo_activate_license', 'codecatalogo_license_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="license_key"><?php esc_html_e('License Key', 'catalogo70'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="license_key"
                                           name="license_key"
                                           value=""
                                           class="regular-text"
                                           placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX"
                                           pattern="[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}"
                                           style="text-transform: uppercase;"
                                           required>
                                    <p class="description">
                                        <?php esc_html_e('Ingresa tu license key en formato XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX (32 caracteres)', 'catalogo70'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" name="codecatalogo_activate_license" class="button button-primary button-large">
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php esc_html_e('Activar Licencia', 'catalogo70'); ?>
                            </button>
                        </p>
                    </form>

                    <div class="codecatalogo-purchase-cta">
                        <p>
                            <strong><?php esc_html_e('¿No tienes una licencia?', 'catalogo70'); ?></strong><br>
                            <a href="https://codigo70.com/codecatalogo-pro" target="_blank" class="button button-secondary">
                                <?php esc_html_e('Comprar Licencia Premium', 'catalogo70'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Estadísticas de uso -->
            <div class="codecatalogo-license-box">
                <h2><?php esc_html_e('Uso Actual', 'catalogo70'); ?></h2>

                <?php foreach ($usage_stats as $type => $data): ?>
                    <div class="codecatalogo-usage-item">
                        <div class="usage-header">
                            <span class="usage-label">
                                <?php
                                $labels = array(
                                    'products' => __('Productos', 'catalogo70'),
                                    'categories' => __('Categorías', 'catalogo70'),
                                    'fields' => __('Campos Personalizados', 'catalogo70')
                                );
                                echo esc_html($labels[$type] ?? $type);
                                ?>
                            </span>
                                                        <span class="usage-count">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        '%d / %s',
                                        $data['current'],
                                        is_numeric($data['limit']) ? $data['limit'] : $data['limit']
                                    )
                                );
                                ?>
                            </span>
                        </div>

                        <?php if (is_numeric($data['limit'])): ?>
                            <div class="usage-bar">
                                <div class="usage-progress" style="width: <?php echo esc_attr(min($data['percentage'], 100)); ?>%;"
                                     data-percentage="<?php echo esc_attr($data['percentage']); ?>"></div>
                            </div>
                        <?php else: ?>
                            <div class="usage-unlimited">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Ilimitado', 'catalogo70'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!$is_premium): ?>
                    <div class="codecatalogo-upgrade-notice">
                        <p><strong><?php esc_html_e('Actualiza a Premium:', 'catalogo70'); ?></strong></p>
                        <ul>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Productos ilimitados', 'catalogo70'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Categorías ilimitadas', 'catalogo70'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Campos personalizados ilimitados', 'catalogo70'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Import/Export de productos', 'catalogo70'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Formularios de contacto', 'catalogo70'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('SEO avanzado', 'catalogo70'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Soporte prioritario', 'catalogo70'); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.codecatalogo-license-page {
    max-width: 1200px;
}

.codecatalogo-license-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.codecatalogo-license-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
}

.codecatalogo-license-box h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.codecatalogo-license-status {
    text-align: center;
    padding: 30px 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.codecatalogo-license-status.active {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.codecatalogo-license-status.inactive {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.codecatalogo-license-status .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
}

.codecatalogo-license-status h3 {
    margin: 10px 0;
    font-size: 18px;
}

.codecatalogo-license-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.codecatalogo-purchase-cta {
    margin-top: 20px;
    padding: 15px;
    background: #f0f0f1;
    border-radius: 4px;
    text-align: center;
}

.codecatalogo-usage-item {
    margin-bottom: 20px;
}

.usage-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.usage-label {
    font-weight: 600;
}

.usage-count {
    color: #666;
}

.usage-bar {
    height: 20px;
    background: #f0f0f1;
    border-radius: 10px;
    overflow: hidden;
}

.usage-progress {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    transition: width 0.3s ease;
}

.usage-progress[data-percentage^="8"],
.usage-progress[data-percentage^="9"],
.usage-progress[data-percentage^="10"] {
    background: linear-gradient(90deg, #d63638, #b32d2e);
}

.usage-unlimited {
    text-align: center;
    padding: 10px;
    background: #d4edda;
    border-radius: 4px;
    color: #155724;
    font-weight: 600;
}

.usage-unlimited .dashicons {
    color: #28a745;
}

.codecatalogo-upgrade-notice {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-left: 4px solid #2271b1;
}

.codecatalogo-upgrade-notice ul {
    margin: 10px 0;
    padding-left: 0;
    list-style: none;
}

.codecatalogo-upgrade-notice li {
    padding: 5px 0;
}

.codecatalogo-upgrade-notice .dashicons {
    color: #28a745;
}

@media (max-width: 782px) {
    .codecatalogo-license-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-formatear license key a mayúsculas con guiones
    var licenseInput = document.getElementById('license_key');
    if (licenseInput) {
        licenseInput.addEventListener('input', function(e) {
            var value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            var formatted = '';

            // Formato: XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX (8-8-8-8 = 32 caracteres)
            for (var i = 0; i < value.length && i < 32; i++) {
                if (i > 0 && i % 8 === 0) {
                    formatted += '-';
                }
                formatted += value[i];
            }

            e.target.value = formatted;
        });
    }
});
</script>

