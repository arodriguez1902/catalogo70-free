<?php
/**
 * Template para tarjeta de producto
 * Variables disponibles: $product_id, $fields, $cta
 */

if (!defined('ABSPATH')) {
    exit;
}

$thumbnail = get_the_post_thumbnail_url($product_id, 'medium');
$categories = get_the_terms($product_id, 'codecatalogo_cat');
?>

<div class="codecatalogo-product-card" data-product-id="<?php echo esc_attr($product_id); ?>">
    
    <div class="codecatalogo-card-image">
        <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
            <?php if ($thumbnail): ?>
                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title($product_id)); ?>">
            <?php else: ?>
                <div class="codecatalogo-card-placeholder">
                    <span class="dashicons dashicons-format-image"></span>
                </div>
            <?php endif; ?>
        </a>
        
        <?php if ($categories && !is_wp_error($categories)): ?>
        <div class="codecatalogo-card-badge">
            <?php echo esc_html($categories[0]->name); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="codecatalogo-card-content">
        
        <h3 class="codecatalogo-card-title">
            <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                <?php echo esc_html(get_the_title($product_id)); ?>
            </a>
        </h3>
        
        <?php if (has_excerpt($product_id)): ?>
        <div class="codecatalogo-card-excerpt">
            <?php echo esc_html(wp_trim_words(get_the_excerpt($product_id), 15)); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($fields)): ?>
        <ul class="codecatalogo-card-specs">
            <?php
            $count = 0;
            foreach ($fields as $field):
                if (!empty($field->field_value) && $field->field_type !== 'file'):
                    if ($count >= 3) break; // Máximo 3 campos en la tarjeta
                    $count++;
            ?>
                                <li>
                    <strong><?php echo esc_html($field->field_label); ?>:</strong>
                    <span><?php echo esc_html($field->field_value); ?></span>
                </li>
            <?php
                endif;
            endforeach;
            ?>
        </ul>
        <?php endif; ?>
        
    </div>
    
    <div class="codecatalogo-card-footer">
        
        <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="codecatalogo-card-link">
            <?php esc_html_e('Ver detalles', 'catalogo70'); ?>
            <span class="dashicons dashicons-arrow-right-alt2"></span>
        </a>
        
        <?php if ($cta && $cta->show_on_card): ?>
        <div class="codecatalogo-card-cta">
            <?php
                        $cta_handler = new CodeCatalogo_CTA_Handler();
            $cta_button = $cta_handler->render_cta_button($product_id, 'card');
            echo wp_kses_post($cta_button);
            ?>
        </div>
        <?php endif; ?>
        
    </div>
    
</div>