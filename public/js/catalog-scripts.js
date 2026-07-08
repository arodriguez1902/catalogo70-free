/**
 * Scripts del catálogo público
 * CodeCatalogo Pro
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ===================================
        // Filtros y Búsqueda AJAX
        // ===================================
        
        var searchTimeout;
        var currentPage = 1;
        var isLoading = false;
        
        // Búsqueda en tiempo real
        $('.codecatalogo-search').on('keyup', function() {
            clearTimeout(searchTimeout);
            var searchTerm = $(this).val();
            
            searchTimeout = setTimeout(function() {
                if (searchTerm.length >= 3 || searchTerm.length === 0) {
                    currentPage = 1;
                    loadProducts();
                }
            }, 500);
        });
        
        // Botón de búsqueda
        $('.codecatalogo-search-btn').on('click', function() {
            currentPage = 1;
            loadProducts();
        });
        
        // Cambio en filtros
        $('.codecatalogo-filter').on('change', function() {
            currentPage = 1;
            loadProducts();
        });
        
        // Limpiar filtros
        $('.codecatalogo-clear-filters').on('click', function() {
            $('.codecatalogo-filter').val('');
            $('.codecatalogo-search').val('');
            currentPage = 1;
            loadProducts();
        });
        
        // Función para cargar productos
        function loadProducts() {
            if (isLoading) return;
            
            isLoading = true;
            var wrapper = $('.codecatalogo-wrapper');
            var grid = wrapper.find('.codecatalogo-products-grid');
            
            // Mostrar loading
            wrapper.find('.codecatalogo-loading').fadeIn();
            
            // Recopilar filtros
            var filters = {};
            $('.codecatalogo-filter[data-filter-field]').each(function() {
                var fieldId = $(this).data('filter-field');
                var value = $(this).val();
                if (value) {
                    filters[fieldId] = value;
                }
            });
            
            var category = $('.codecatalogo-filter[data-filter-type="category"]').val() || '';
            var search = $('.codecatalogo-search').val() || '';
            
            $.ajax({
                url: codecatalogoPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'codecatalogo_filter_products',
                    filters: filters,
                    category: category,
                    search: search,
                    page: currentPage
                },
                success: function(response) {
                    if (response.success) {
                        renderProducts(response.data);
                    } else {
                        showError(codecatalogoPublic.strings.error);
                    }
                },
                error: function() {
                    showError(codecatalogoPublic.strings.error);
                },
                complete: function() {
                    wrapper.find('.codecatalogo-loading').fadeOut();
                    isLoading = false;
                }
            });
        }
        
        // Renderizar productos
        function renderProducts(data) {
            var grid = $('.codecatalogo-products-grid');
            
            if (data.products.length === 0) {
                grid.html('<div class="codecatalogo-no-results"><p>' + codecatalogoPublic.strings.no_results + '</p></div>');
                $('.codecatalogo-pagination').hide();
                return;
            }
            
            var html = '';
            data.products.forEach(function(product) {
                html += buildProductCard(product);
            });
            
            grid.html(html);
            
            // Actualizar paginación
            if (data.max_pages > 1) {
                renderPagination(data.current_page, data.max_pages);
                $('.codecatalogo-pagination').show();
            } else {
                $('.codecatalogo-pagination').hide();
            }
            
            // Scroll suave al inicio de los resultados
            $('html, body').animate({
                scrollTop: grid.offset().top - 100
            }, 500);
        }
        
        // Construir HTML de tarjeta de producto
        function buildProductCard(product) {
            var thumbnail = product.thumbnail ? 
                '<img src="' + product.thumbnail + '" alt="' + escapeHtml(product.title) + '">' :
                '<div class="codecatalogo-card-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
            
            var html = '<div class="codecatalogo-product-card" data-product-id="' + product.id + '">';
            html += '<div class="codecatalogo-card-image">';
            html += '<a href="' + product.permalink + '">' + thumbnail + '</a>';
            html += '</div>';
            html += '<div class="codecatalogo-card-content">';
            html += '<h3 class="codecatalogo-card-title"><a href="' + product.permalink + '">' + escapeHtml(product.title) + '</a></h3>';
            
            if (product.excerpt) {
                html += '<div class="codecatalogo-card-excerpt">' + product.excerpt + '</div>';
            }
            
            if (product.fields && product.fields.length > 0) {
                html += '<ul class="codecatalogo-card-specs">';
                product.fields.slice(0, 3).forEach(function(field) {
                    html += '<li>';
                    if (field.icon) {
                        html += '<span class="dashicons ' + field.icon + '"></span>';
                    }
                    html += '<strong>' + escapeHtml(field.label) + ':</strong>';
                    html += '<span>' + escapeHtml(field.value) + '</span>';
                    html += '</li>';
                });
                html += '</ul>';
            }
            
            html += '</div>';
            html += '<div class="codecatalogo-card-footer">';
            html += '<a href="' + product.permalink + '" class="codecatalogo-card-link">';
            html += codecatalogoPublic.strings.ver_detalles || 'Ver detalles';
            html += '<span class="dashicons dashicons-arrow-right-alt2"></span>';
            html += '</a>';
            html += '</div>';
            html += '</div>';
            
            return html;
        }
        
        // Renderizar paginación
        function renderPagination(current, total) {
            var html = '<nav class="codecatalogo-pagination-nav">';
            
            // Anterior
            if (current > 1) {
                html += '<a href="#" class="page-numbers codecatalogo-page-link" data-page="' + (current - 1) + '">&laquo; Anterior</a>';
            }
            
            // Páginas
            for (var i = 1; i <= total; i++) {
                if (i === current) {
                    html += '<span class="page-numbers current">' + i + '</span>';
                } else if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
                    html += '<a href="#" class="page-numbers codecatalogo-page-link" data-page="' + i + '">' + i + '</a>';
                } else if (i === current - 3 || i === current + 3) {
                    html += '<span class="page-numbers dots">…</span>';
                }
            }
            
            // Siguiente
            if (current < total) {
                html += '<a href="#" class="page-numbers codecatalogo-page-link" data-page="' + (current + 1) + '">Siguiente &raquo;</a>';
            }
            
            html += '</nav>';
            
            $('.codecatalogo-pagination').html(html);
        }
        
        // Click en paginación
        $(document).on('click', '.codecatalogo-page-link', function(e) {
            e.preventDefault();
            currentPage = $(this).data('page');
            loadProducts();
        });
        
        // ===================================
        // Modal de Contacto
        // ===================================
        
        // Abrir modal
        $(document).on('click', '.codecatalogo-form-btn', function() {
            var productId = $(this).data('product-id');
            var productName = $(this).data('product-name');
            
            $('#contact-product-id').val(productId);
            $('#contact-product-name').val(productName);
            
            // Mostrar nombre del producto en el modal
            $('#codecatalogo-contact-modal h3').html('Solicitar información sobre:<br><strong>' + productName + '</strong>');
            
            $('#codecatalogo-contact-modal').fadeIn();
        });

                // Cerrar modal
        $('.codecatalogo-modal-close, .codecatalogo-modal-overlay, .codecatalogo-modal-cancel').on('click', function() {
            $('#codecatalogo-contact-modal').fadeOut();
            $('#codecatalogo-contact-form')[0].reset();
            $('.codecatalogo-form-message').hide();
            // Restaurar título original
            $('#codecatalogo-contact-modal h3').text('Solicitar Información');
        });

        // Enviar formulario de contacto
        $('#codecatalogo-contact-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('button[type="submit"]');
            var messageDiv = form.find('.codecatalogo-form-message');
            
            // Validar campos requeridos
            var name = $('#contact-name').val().trim();
            var email = $('#contact-email').val().trim();
            var message = $('#contact-message').val().trim();
            
            if (!name || !email || !message) {
                showFormMessage('error', codecatalogoPublic.strings.required_fields);
                return;
            }
            
            // Deshabilitar botón
            submitBtn.prop('disabled', true).text(codecatalogoPublic.strings.sending);
            
            $.ajax({
                url: codecatalogoPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'codecatalogo_send_contact_form',
                    nonce: codecatalogoPublic.nonce,
                    product_id: $('#contact-product-id').val(),
                    product_name: $('#contact-product-name').val(),
                    name: name,
                    email: email,
                    phone: $('#contact-phone').val(),
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        showFormMessage('success', response.data.message);
                        form[0].reset();
                        
                        // Cerrar modal después de 2 segundos
                        setTimeout(function() {
                            $('#codecatalogo-contact-modal').fadeOut();
                            messageDiv.hide();
                            $('#codecatalogo-contact-modal h3').text('Solicitar Información');
                        }, 2000);
                    } else {
                        showFormMessage('error', response.data.message);
                    }
                },
                error: function() {
                    showFormMessage('error', codecatalogoPublic.strings.error);
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Enviar');
                }
            });
        });

        function showFormMessage(type, message) {
            var messageDiv = $('.codecatalogo-form-message');
            messageDiv.removeClass('success error').addClass(type);
            messageDiv.html(message).fadeIn();
        }
        
        // ===================================
        // Búsqueda en Widget
        // ===================================
        
        $('.codecatalogo-search-form').on('submit', function(e) {
            e.preventDefault();
            
            var searchTerm = $(this).find('.codecatalogo-search-input').val().trim();
            var resultsDiv = $(this).siblings('.codecatalogo-search-results');
            
            if (searchTerm.length < 3) {
                resultsDiv.hide();
                return;
            }
            
            $.ajax({
                url: codecatalogoPublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'codecatalogo_search_products',
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success && response.data.results.length > 0) {
                        var html = '<div class="codecatalogo-search-results-list">';
                        response.data.results.forEach(function(result) {
                            html += '<div class="codecatalogo-search-result-item">';
                            if (result.thumbnail) {
                                html += '<img src="' + result.thumbnail + '" alt="' + escapeHtml(result.title) + '">';
                            }
                            html += '<a href="' + result.url + '">' + escapeHtml(result.title) + '</a>';
                            html += '</div>';
                        });
                        html += '</div>';
                        resultsDiv.html(html).fadeIn();
                    } else {
                        resultsDiv.html('<p>No se encontraron resultados.</p>').fadeIn();
                    }
                }
            });
        });
        
        // Cerrar resultados al hacer click fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.codecatalogo-search-widget').length) {
                $('.codecatalogo-search-results').fadeOut();
            }
        });
        
        // ===================================
        // Galería de imágenes (si se implementa en el futuro)
        // ===================================
        
        // Aquí podrías agregar funcionalidad de lightbox o galería
        
        // ===================================
        // Funciones auxiliares
        // ===================================
        
        function showError(message) {
            alert(message);
        }
        
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // ===================================
        // Smooth Scroll para enlaces internos
        // ===================================
        
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 800);
            }
        });
        
        // ===================================
        // Lazy Loading de Imágenes (opcional)
        // ===================================
        
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            $('.codecatalogo-lazy-image').each(function() {
                imageObserver.observe(this);
            });
        }
        
    });
    
})(jQuery);