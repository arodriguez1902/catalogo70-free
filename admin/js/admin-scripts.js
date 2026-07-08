/**
 * Scripts del área de administración
 * CodeCatalogo Pro
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ===================================
        // Media Uploader
        // ===================================
        
        var mediaUploader;
        
        $(document).on('click', '.codecatalogo-upload-file', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var targetInput = $('#' + button.data('target'));
            
            // Si el uploader ya existe, abrirlo
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Crear el media uploader
            mediaUploader = wp.media({
                title: 'Seleccionar Archivo',
                button: {
                    text: 'Usar este archivo'
                },
                multiple: false
            });
            
            // Cuando se selecciona un archivo
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                targetInput.val(attachment.url);
                
                // Actualizar preview si existe
                var preview = targetInput.closest('.codecatalogo-file-field').find('.codecatalogo-file-preview');
                if (preview.length) {
                    preview.html('<a href="' + attachment.url + '" target="_blank" class="button"><span class="dashicons dashicons-media-document"></span> Ver Archivo</a>');
                } else {
                    targetInput.after('<div class="codecatalogo-file-preview"><a href="' + attachment.url + '" target="_blank" class="button"><span class="dashicons dashicons-media-document"></span> Ver Archivo</a></div>');
                }
            });
            
            mediaUploader.open();
        });
        
        // ===================================
        // CTA Type Toggle
        // ===================================
        
        $('#codecatalogo_cta_type').on('change', function() {
            var type = $(this).val();
            
            $('.codecatalogo-cta-toggle').removeClass('active');
            
            if (type === 'whatsapp' || type === 'both') {
                $('#codecatalogo_whatsapp_config').addClass('active');
            }
            
            if (type === 'form' || type === 'both') {
                $('#codecatalogo_form_config').addClass('active');
            }
        });
        
        // ===================================
        // Gestión de Campos - Página Admin
        // ===================================
        
        // Sortable
        if ($('#codecatalogo-fields-sortable').length) {
            $('#codecatalogo-fields-sortable').sortable({
                handle: '.handle',
                placeholder: 'ui-state-highlight',
                update: function() {
                    var order = [];
                    $('#codecatalogo-fields-sortable tr').each(function() {
                        order.push($(this).data('field-id'));
                    });
                    
                    // Guardar orden
                    $.ajax({
                        url: codecatalogoAdmin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'codecatalogo_reorder_fields',
                            nonce: codecatalogoAdmin.nonce,
                            order: order
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotice('success', response.data.message);
                            }
                        }
                    });
                }
            });
        }
        
                // ===================================
        // Mejoras en el modal de campos
        // ===================================
        
        // Mostrar/ocultar campo de opciones según tipo de campo
        $('#field-type').on('change', function() {
            var type = $(this).val();
            if (type === 'select') {
                $('#field-options-wrapper').slideDown();
                $('#field-unit-wrapper').hide();
            } else if (type === 'number') {
                $('#field-options-wrapper').slideUp();
                $('#field-unit-wrapper').show();
            } else {
                $('#field-options-wrapper').slideUp();
                $('#field-unit-wrapper').show();
            }
        });
        
        // Auto-generar nombre interno desde la etiqueta
        $('#field-label').on('blur', function() {
            var nameField = $('#field-name');
            // Solo auto-generar si está vacío
            if (!nameField.val()) {
                var label = $(this).val().toLowerCase()
                    .replace(/[^a-z0-9áéíóúñ\s]/g, '')
                    .replace(/[á]/g, 'a')
                    .replace(/[é]/g, 'e')
                    .replace(/[í]/g, 'i')
                    .replace(/[ó]/g, 'o')
                    .replace(/[ú]/g, 'u')
                    .replace(/[ñ]/g, 'n')
                    .replace(/\s+/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
                nameField.val(label);
            }
        });
        
        // Agregar campo
        $('#codecatalogo-add-field').on('click', function() {
            $('#codecatalogo-modal-title').text('Agregar Campo');
            $('#codecatalogo-field-form')[0].reset();
            $('#field-id').val('');
            $('#field-name').prop('readonly', false);
            $('#field-options-wrapper').hide();
            $('#field-unit-wrapper').show();
            $('#codecatalogo-field-modal').fadeIn();
        });
        

























                // Editar campo
        $(document).on('click', '.codecatalogo-edit-field', function() {
            var fieldId = $(this).data('field-id');
            var row = $(this).closest('tr');
            
            $('#codecatalogo-modal-title').text('Editar Campo');
            $('#field-id').val(fieldId);
            $('#field-name').val(row.find('code').text()).prop('readonly', true);
            $('#field-label').val(row.find('td:eq(2)').text());
            $('#field-type').val(row.find('td:eq(3)').text()).trigger('change');
            
            // Checkboxes - buscar los ✓ en las columnas
            var cols = row.find('td');
            
            $('input[name="show_in_card"]').prop('checked', $(cols[4]).text().trim() === '✓');
            $('input[name="show_in_filter"]').prop('checked', $(cols[5]).text().trim() === '✓');
            $('input[name="is_seo_relevant"]').prop('checked', $(cols[6]).text().trim() === '✓');
            $('input[name="is_required"]').prop('checked', $(cols[7]).text().trim() === '✓');
            
            $('#codecatalogo-field-modal').fadeIn();
        });
        
        // Eliminar campo
        $(document).on('click', '.codecatalogo-delete-field', function() {
            if (!confirm(codecatalogoAdmin.strings.confirm_delete)) {
                return;
            }
            
            var fieldId = $(this).data('field-id');
            var row = $(this).closest('tr');
            
            $.ajax({
                url: codecatalogoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'codecatalogo_delete_field',
                    nonce: codecatalogoAdmin.nonce,
                    field_id: fieldId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(function() {
                            $(this).remove();
                        });
                        showNotice('success', response.data.message);
                    } else {
                        showNotice('error', response.data.message);
                    }
                }
            });
        });
        
        // Cerrar modal
        $('.codecatalogo-close-modal').on('click', function() {
            $('#codecatalogo-field-modal').fadeOut();
        });
        
        $(document).on('click', '#codecatalogo-field-modal', function(e) {
            if (e.target.id === 'codecatalogo-field-modal' || $(e.target).hasClass('codecatalogo-modal-close')) {
                $(this).fadeOut();
            }
        });
        
        // Enviar formulario de campo
        $('#codecatalogo-field-form').on('submit', function(e) {
            e.preventDefault();
            
            var fieldId = $('#field-id').val();
            var action = fieldId ? 'codecatalogo_update_field' : 'codecatalogo_create_field';
            
            // Procesar opciones de select
            var fieldOptions = null;
            if ($('#field-type').val() === 'select') {
                var optionsText = $('#field-options').val().trim();
                if (optionsText) {
                    fieldOptions = {};
                    var lines = optionsText.split('\n');
                    $.each(lines, function(i, line) {
                        line = line.trim();
                        if (line) {
                            var parts = line.split('=');
                            var key = parts[0].trim();
                            var val = parts.length > 1 ? parts.slice(1).join('=').trim() : parts[0].trim();
                            fieldOptions[key] = val;
                        }
                    });
                }
            }
            
                        var formData = {
                action: action,
                nonce: codecatalogoAdmin.nonce,
                field_id: fieldId,
                field_name: $('#field-name').val(),
                field_label: $('#field-label').val(),
                field_type: $('#field-type').val(),
                field_group: $('#field-group').val(),
                field_unit: $('#field-unit').val(),
                field_options: fieldOptions,
                show_in_card: $('input[name="show_in_card"]').is(':checked') ? '1' : '0',
                show_in_filter: $('input[name="show_in_filter"]').is(':checked') ? '1' : '0',
                is_seo_relevant: $('input[name="is_seo_relevant"]').is(':checked') ? '1' : '0',
                is_required: $('input[name="is_required"]').is(':checked') ? '1' : '0'
            };
            
            $.ajax({
                url: codecatalogoAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        $('#codecatalogo-field-modal').fadeOut();
                        location.reload(); // Recargar para mostrar el nuevo campo
                    } else {
                        showNotice('error', response.data.message);
                    }
                }
            });
        });
        
        // ===================================
        // Funciones auxiliares
        // ===================================
        
        function showNotice(type, message) {
            var noticeClass = 'notice notice-' + type;
            var notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
    });
    
})(jQuery);