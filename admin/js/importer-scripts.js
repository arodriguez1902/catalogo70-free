/**
 * Scripts para Importador/Exportador
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Validación del formulario de importación
        $('.codecatalogo-import-form').on('submit', function(e) {
            var fileInput = $('#csv_file');
            
            if (!fileInput.val()) {
                e.preventDefault();
                alert('Por favor selecciona un archivo CSV');
                return false;
            }
            
            // Validar extensión
            var fileName = fileInput.val();
            var fileExtension = fileName.split('.').pop().toLowerCase();
            
            if (fileExtension !== 'csv') {
                e.preventDefault();
                alert('Por favor selecciona un archivo con extensión .csv');
                return false;
            }
            
            // Confirmar importación
            if (!confirm('¿Estás seguro de que deseas importar este archivo?\n\nAsegúrate de:\n- Haber subido las imágenes vía FTP\n- Verificar el formato del CSV\n- Hacer un respaldo de tus datos actuales')) {
                e.preventDefault();
                return false;
            }
            
            // Mostrar mensaje de procesamiento
            var submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="dashicons dashicons-update spin"></span> Importando...');
        });
        
        // Validación del formulario de exportación
        $('.codecatalogo-export-form').on('submit', function() {
            var submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="dashicons dashicons-update spin"></span> Exportando...');
        });
        
        // Información del archivo seleccionado
        $('#csv_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            var fileSize = this.files[0] ? (this.files[0].size / 1024 / 1024).toFixed(2) : 0;
            
            if (fileName) {
                var info = '<p style="margin-top: 10px; color: #0073aa;"><strong>Archivo seleccionado:</strong> ' + fileName + ' (' + fileSize + ' MB)</p>';
                $(this).parent().find('.file-info').remove();
                $(this).after('<div class="file-info">' + info + '</div>');
            }
        });
        
        // CSS para spinner
        if (!$('#codecatalogo-spinner-css').length) {
            $('head').append('<style id="codecatalogo-spinner-css">.dashicons.spin { animation: codecatalogo-spin 1s linear infinite; } @keyframes codecatalogo-spin { to { transform: rotate(360deg); } }</style>');
        }
        
    });
    
})(jQuery);