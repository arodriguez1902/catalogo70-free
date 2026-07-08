<?php
/**
 * Internacionalización
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_i18n {
    
    /**
     * Cargar el dominio de texto del plugin
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'codecatalogo-pro',
            false,
            dirname(CODECATALOGO_BASENAME) . '/languages/'
        );
    }
}