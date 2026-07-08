<?php
/**
 * Página de Importar/Exportar
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar licencia Premium
require_once CODECATALOGO_PATH . 'includes/class-codecatalogo-license.php';
require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-importer.php';
require_once CODECATALOGO_PATH . 'admin/class-codecatalogo-exporter.php';
$license_manager = new CodeCatalogo_License();
$is_premium = $license_manager->is_premium();

// Procesar importación (solo si es Premium)
if ($is_premium && isset($_POST['codecatalogo_import_nonce']) && check_admin_referer('codecatalogo_import_csv', 'codecatalogo_import_nonce')) {

    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $importer = new CodeCatalogo_Importer();

        $settings = array(
            'delimiter' => sanitize_text_field($_POST['delimiter']),
            'encoding' => sanitize_text_field($_POST['encoding']),
            'update_existing' => isset($_POST['update_existing']),
        );

        $result = $importer->import_from_csv($_FILES['csv_file']['tmp_name'], $settings);

        if ($result['success']) {
                        $results = $result['results'];
            echo '<div class="notice notice-success"><p>';
                        echo sprintf(
                            /* translators: %1$d: Number of products imported, %2$d: Number of products updated */
                            esc_html__('✅ Importación completada: %1$d productos importados, %2$d actualizados', 'catalogo70'),
                            intval($results['imported']),
                            intval($results['updated'])
                        );
            echo '</p></div>';
            
            if (!empty($results['warnings'])) {
                echo '<div class="notice notice-warning"><p><strong>⚠️ Advertencias:</strong></p><ul>';
                foreach ($results['warnings'] as $warning) {
                    echo '<li>' . esc_html($warning) . '</li>';
                }
                echo '</ul></div>';
            }
            
            if (!empty($results['errors'])) {
                echo '<div class="notice notice-error"><p><strong>❌ Errores:</strong></p><ul>';
                foreach ($results['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html__('Por favor selecciona un archivo CSV', 'catalogo70') . '</p></div>';
    }
}

// NOTA: Las descargas (plantilla y exportación) se procesan en CodeCatalogo_Settings::process_downloads()
// mediante el hook admin_init, ANTES de que se cargue esta página

?>

<div class="wrap codecatalogo-import-export">
    <h1><?php esc_html_e('Importar / Exportar Productos', 'catalogo70'); ?></h1>

    <?php if (!$is_premium): ?>
        <!-- Mensaje de función Premium -->
        <div class="codecatalogo-premium-required">
            <div class="codecatalogo-premium-lock">
                <span class="dashicons dashicons-lock"></span>
                <h2><?php esc_html_e('Función Premium', 'catalogo70'); ?></h2>
                <p><?php esc_html_e('La importación y exportación de productos es una funcionalidad exclusiva de la versión Premium.', 'catalogo70'); ?></p>

                <div class="codecatalogo-premium-benefits">
                    <h3><?php esc_html_e('Con Premium obtienes:', 'catalogo70'); ?></h3>
                    <ul>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Importar productos masivamente desde CSV', 'catalogo70'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Exportar todos tus productos a CSV', 'catalogo70'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Plantillas CSV predefinidas', 'catalogo70'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Productos, categorías y campos ilimitados', 'catalogo70'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Formularios de contacto avanzados', 'catalogo70'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('SEO y Schema.org avanzado', 'catalogo70'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Soporte prioritario', 'catalogo70'); ?></li>
                    </ul>
                </div>

                <div class="codecatalogo-premium-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=codecatalogo-license')); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php esc_html_e('Activar Licencia Premium', 'catalogo70'); ?>
                    </a>
                    <a href="https://codigo70.com/codecatalogo-pro" target="_blank" class="button button-secondary button-hero">
                        <?php esc_html_e('Comprar Licencia', 'catalogo70'); ?>
                    </a>
                </div>
            </div>

            <!-- Vista previa bloqueada -->
            <div class="codecatalogo-premium-preview">
                <div class="codecatalogo-blur-overlay">
                    <div class="codecatalogo-ie-section">
                        <h2><?php esc_html_e('📥 Importar Productos desde CSV', 'catalogo70'); ?></h2>
                        <p><?php esc_html_e('Vista previa de la funcionalidad de importación...', 'catalogo70'); ?></p>
                    </div>
                    <div class="codecatalogo-ie-section">
                        <h2><?php esc_html_e('📤 Exportar Productos a CSV', 'catalogo70'); ?></h2>
                        <p><?php esc_html_e('Vista previa de la funcionalidad de exportación...', 'catalogo70'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .codecatalogo-premium-required {
                margin-top: 20px;
            }
            .codecatalogo-premium-lock {
                background: #fff;
                border: 2px solid #2271b1;
                border-radius: 8px;
                padding: 40px;
                text-align: center;
                max-width: 800px;
                margin: 0 auto 30px;
            }
            .codecatalogo-premium-lock .dashicons-lock {
                font-size: 64px;
                width: 64px;
                height: 64px;
                color: #2271b1;
            }
            .codecatalogo-premium-lock h2 {
                font-size: 24px;
                margin: 20px 0 10px;
            }
            .codecatalogo-premium-benefits {
                margin: 30px 0;
                text-align: left;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }
            .codecatalogo-premium-benefits ul {
                list-style: none;
                padding: 0;
            }
            .codecatalogo-premium-benefits li {
                padding: 8px 0;
                font-size: 14px;
            }
            .codecatalogo-premium-benefits .dashicons {
                color: #28a745;
            }
            .codecatalogo-premium-actions {
                margin-top: 30px;
            }
            .codecatalogo-premium-actions .button {
                margin: 0 5px;
            }
            .codecatalogo-premium-preview {
                position: relative;
                pointer-events: none;
            }
            .codecatalogo-blur-overlay {
                filter: blur(5px);
                opacity: 0.3;
            }
        </style>

    <?php else: ?>
        <!-- Contenido normal para usuarios Premium -->
        <div class="codecatalogo-ie-container">

            <!-- IMPORTAR -->
            <div class="codecatalogo-ie-section">
            <h2><?php esc_html_e('📥 Importar Productos desde CSV', 'catalogo70'); ?></h2>
            
                        <div class="codecatalogo-notice-info">
                <p><strong><?php esc_html_e('⚠️ Importante antes de importar:', 'catalogo70'); ?></strong></p>
                <ul>
                    <li><?php esc_html_e('Las imágenes pueden ser URLs completas (https://...) y se descargarán automáticamente', 'catalogo70'); ?></li>
                    <li><?php esc_html_e('También soporta rutas locales: /wp-content/uploads/2025/01/imagen.jpg', 'catalogo70'); ?></li>
                    <li><?php esc_html_e('La galería de imágenes se separa con punto y coma (;) entre URLs', 'catalogo70'); ?></li>
                    <li><?php esc_html_e('Las categorías y etiquetas se separan con punto y coma (;)', 'catalogo70'); ?></li>
                    <li><?php esc_html_e('Si el producto existe (mismo título), se actualizará completamente', 'catalogo70'); ?></li>
                    <li><?php esc_html_e('Los campos marcados como requeridos NO pueden estar vacíos', 'catalogo70'); ?></li>
                </ul>
            </div>
            
            <!-- Descargar plantilla -->
            <div class="codecatalogo-template-download">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=codecatalogo-import-export&action=download_template'), 'codecatalogo_download_template', 'nonce')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Descargar Plantilla CSV', 'catalogo70'); ?>
                </a>
                <p class="description"><?php esc_html_e('Descarga una plantilla con los campos configurados actualmente', 'catalogo70'); ?></p>
            </div>
            
            <form method="post" enctype="multipart/form-data" class="codecatalogo-import-form">
                <?php wp_nonce_field('codecatalogo_import_csv', 'codecatalogo_import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="csv_file"><?php esc_html_e('Archivo CSV', 'catalogo70'); ?> *</label>
                        </th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="delimiter"><?php esc_html_e('Delimitador', 'catalogo70'); ?></label>
                        </th>
                        <td>
                            <select name="delimiter" id="delimiter">
                                <option value=",">,  (coma)</option>
                                <option value=";">; (punto y coma)</option>
                                <option value="|">| (pipe)</option>
                                <option value="	">Tab</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="encoding"><?php esc_html_e('Codificación', 'catalogo70'); ?></label>
                        </th>
                        <td>
                            <select name="encoding" id="encoding">
                                <option value="UTF-8">UTF-8</option>
                                <option value="ISO-8859-1">ISO-8859-1</option>
                                <option value="Windows-1252">Windows-1252</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Opciones', 'catalogo70'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="update_existing" value="1" checked>
                                <?php esc_html_e('Actualizar productos existentes (por título)', 'catalogo70'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="codecatalogo_import" class="button button-primary button-hero">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Importar Productos', 'catalogo70'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- EXPORTAR -->
        <div class="codecatalogo-ie-section">
            <h2><?php esc_html_e('📤 Exportar Productos a CSV', 'catalogo70'); ?></h2>
            
            <p><?php esc_html_e('Exporta todos tus productos actuales a un archivo CSV para editar o hacer respaldo.', 'catalogo70'); ?></p>
            
            <form method="post" class="codecatalogo-export-form">
                <?php wp_nonce_field('codecatalogo_export_csv', 'codecatalogo_export_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="export_delimiter"><?php esc_html_e('Delimitador', 'catalogo70'); ?></label>
                        </th>
                        <td>
                            <select name="export_delimiter" id="export_delimiter">
                                <option value=",">,  (coma)</option>
                                <option value=";">; (punto y coma)</option>
                                <option value="|">| (pipe)</option>
                                <option value="	">Tab</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="export_encoding"><?php esc_html_e('Codificación', 'catalogo70'); ?></label>
                        </th>
                        <td>
                            <select name="export_encoding" id="export_encoding">
                                <option value="UTF-8">UTF-8</option>
                                <option value="ISO-8859-1">ISO-8859-1</option>
                                <option value="Windows-1252">Windows-1252</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="codecatalogo_export" class="button button-primary button-hero">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Exportar Todos los Productos', 'catalogo70'); ?>
                    </button>
                </p>
            </form>
        </div>

    </div>
    <?php endif; ?>
</div>
