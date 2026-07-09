<?php
/**
 * Template producto individual - Versión FREE
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$field_manager = new CodeCatalogo_Field_Manager();
$seo = new CodeCatalogo_SEO();

while (have_posts()) : the_post();
    
    $product_id = get_the_ID();
    $fields = $field_manager->get_product_fields($product_id);
    
    ?>
    
    <article id="product-<?php the_ID(); ?>" <?php post_class('codecatalogo-single-product'); ?>>
        
        <div class="codecatalogo-breadcrumbs-wrapper">
            <?php echo wp_kses_post($seo->render_breadcrumbs($product_id)); ?>
        </div>
        
        <div class="codecatalogo-product-container">
            
            <div class="codecatalogo-product-gallery">
                <?php
                $gallery_ids = get_post_meta($product_id, '_codecatalogo_gallery_ids', true);
                $gallery_ids = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();
                $all_images = array();

                if (has_post_thumbnail()) {
                    $all_images[] = array(
                        'id' => get_post_thumbnail_id($product_id),
                        'full' => get_the_post_thumbnail_url($product_id, 'full'),
                        'large' => get_the_post_thumbnail_url($product_id, 'large'),
                        'thumb' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                    );
                }

                foreach ($gallery_ids as $attach_id) {
                    $attach_id = intval(trim($attach_id));
                    if ($attach_id && $attach_id !== get_post_thumbnail_id($product_id)) {
                        $full = wp_get_attachment_image_src($attach_id, 'full');
                        $large = wp_get_attachment_image_src($attach_id, 'large');
                        $thumb = wp_get_attachment_image_src($attach_id, 'thumbnail');
                        if ($full) {
                            $all_images[] = array('id' => $attach_id, 'full' => $full[0], 'large' => $large ? $large[0] : $full[0], 'thumb' => $thumb ? $thumb[0] : $full[0]);
                        }
                    }
                }
                ?>

                <?php if (!empty($all_images)): ?>
                    <div class="codecatalogo-gallery-main" id="codecatalogo-gallery-main">
                        <div class="codecatalogo-gallery-track">
                            <?php foreach ($all_images as $index => $image): ?>
                            <div class="codecatalogo-gallery-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
                                <img src="<?php echo esc_url($image['large']); ?>" alt="<?php echo esc_attr(get_the_title() . ' - imagen ' . ($index + 1)); ?>" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($all_images) > 1): ?>
                        <button type="button" class="codecatalogo-gallery-nav codecatalogo-gallery-prev" aria-label="<?php esc_attr_e('Anterior', 'catalogo70'); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                        <button type="button" class="codecatalogo-gallery-nav codecatalogo-gallery-next" aria-label="<?php esc_attr_e('Siguiente', 'catalogo70'); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                        <?php endif; ?>
                    </div>
                    <?php if (count($all_images) > 1): ?>
                    <div class="codecatalogo-gallery-thumbs">
                        <?php foreach ($all_images as $index => $image): ?>
                        <div class="codecatalogo-gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
                            <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr(get_the_title() . ' - miniatura ' . ($index + 1)); ?>" loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="codecatalogo-product-image-placeholder"><span class="dashicons dashicons-format-image"></span></div>
                <?php endif; ?>
            </div>

            <script>
            (function() {
                var mainEl = document.getElementById('codecatalogo-gallery-main');
                if (!mainEl) return;
                var track = mainEl.querySelector('.codecatalogo-gallery-track');
                var slides = track.querySelectorAll('.codecatalogo-gallery-slide');
                var thumbs = document.querySelectorAll('.codecatalogo-gallery-thumb');
                var prevBtn = mainEl.querySelector('.codecatalogo-gallery-prev');
                var nextBtn = mainEl.querySelector('.codecatalogo-gallery-next');
                var currentIndex = 0;
                function goTo(index) {
                    if (index < 0) index = slides.length - 1;
                    if (index >= slides.length) index = 0;
                    currentIndex = index;
                    slides.forEach(function(s, i) { s.classList.toggle('active', i === index); });
                    thumbs.forEach(function(t, i) { t.classList.toggle('active', i === index); });
                    track.style.transform = 'translateX(-' + (index * slides[0].offsetWidth) + 'px)';
                }
                if (prevBtn && nextBtn) { prevBtn.addEventListener('click', function() { goTo(currentIndex - 1); }); nextBtn.addEventListener('click', function() { goTo(currentIndex + 1); }); }
                thumbs.forEach(function(t) { t.addEventListener('click', function() { goTo(parseInt(this.getAttribute('data-index'))); }); });
                var startX = 0, isDragging = false;
                mainEl.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; isDragging = true; }, { passive: true });
                mainEl.addEventListener('touchend', function(e) {
                    if (!isDragging) return;
                    var endX = e.changedTouches[0].clientX;
                    var diff = startX - endX;
                    if (Math.abs(diff) > 50) { if (diff > 0) goTo(currentIndex + 1); else goTo(currentIndex - 1); }
                    isDragging = false;
                }, { passive: true });
            })();
            </script>
            
            <div class="codecatalogo-product-info">
                <header class="codecatalogo-product-header">
                    <h1 class="codecatalogo-product-title"><?php the_title(); ?></h1>
                    <?php $categories = get_the_terms($product_id, 'codecatalogo_cat');
                    if ($categories && !is_wp_error($categories)): ?>
                    <div class="codecatalogo-product-categories">
                        <?php foreach ($categories as $category): ?>
                            <a href="<?php echo esc_url(get_term_link($category)); ?>" class="codecatalogo-category-badge"><?php echo esc_html($category->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </header>
                <?php if (has_excerpt()): ?>
                <div class="codecatalogo-product-excerpt"><?php the_excerpt(); ?></div>
                <?php endif; ?>
                <?php if (!empty($fields)): ?>
                <div class="codecatalogo-product-specs">
                    <h3><?php esc_html_e('Especificaciones Técnicas', 'catalogo70'); ?></h3>
                    <table class="codecatalogo-specs-table">
                        <tbody>
                            <?php foreach ($fields as $field):
                                if (!empty($field->field_value)):
                            ?>
                            <tr>
                                <th><?php echo esc_html($field->field_label); ?></th>
                                <td>
                                    <?php if ($field->field_type === 'file' && !empty($field->field_value)): ?>
                                        <a href="<?php echo esc_url($field->field_value); ?>" target="_blank" class="codecatalogo-file-link"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Descargar', 'catalogo70'); ?></a>
                                    <?php elseif ($field->field_type === 'url'): ?>
                                        <a href="<?php echo esc_url($field->field_value); ?>" target="_blank"><?php echo esc_html($field->field_value); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html($field->field_value); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (get_the_content()): ?>
        <div class="codecatalogo-product-content">
            <h2><?php esc_html_e('Descripción Detallada', 'catalogo70'); ?></h2>
            <?php the_content(); ?>
        </div>
        <?php endif; ?>
        
        <?php
        $related = new WP_Query(array(
            'post_type' => 'codecatalogo_product',
            'posts_per_page' => 4,
            'post__not_in' => array($product_id),
            'tax_query' => array(array('taxonomy' => 'codecatalogo_cat', 'field' => 'term_id', 'terms' => wp_get_post_terms($product_id, 'codecatalogo_cat', array('fields' => 'ids')))),
        ));
        if ($related->have_posts()):
        ?>
        <div class="codecatalogo-related-products">
            <h2><?php esc_html_e('Productos Relacionados', 'catalogo70'); ?></h2>
            <div class="codecatalogo-products-grid codecatalogo-columns-4">
                <?php $display = new CodeCatalogo_Display();
                while ($related->have_posts()): $related->the_post(); $display->render_product_card(get_the_ID()); endwhile;
                wp_reset_postdata(); ?>
            </div>
        </div>
        <?php endif; ?>
        
    </article>
    
<?php
endwhile;

get_footer();
