<?php
/**
 * Template para archivo de productos (catálogo)
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$display = new CodeCatalogo_Display();
$seo = new CodeCatalogo_SEO();
?>

<div class="codecatalogo-archive-wrapper">
    
    <header class="codecatalogo-archive-header">
        <h1 class="codecatalogo-archive-title">
            <?php
            if (is_tax('codecatalogo_cat')) {
                single_term_title();
            } elseif (is_tax('codecatalogo_tag')) {
                single_term_title();
            } else {
                echo esc_html__('Catálogo de Productos', 'catalogo70');
            }
            ?>
        </h1>
        
        <?php
        if (is_tax()) {
            $term_description = term_description();
            if (!empty($term_description)) {
                echo '<div class="codecatalogo-archive-description">' . wp_kses_post($term_description) . '</div>';
            }
        }
        ?>
    </header>
    
    <div class="codecatalogo-archive-content">
        <?php
        $category_id = '';
        if (is_tax('codecatalogo_cat')) {
            $category_id = get_queried_object_id();
        }
        
        $output = $display->render_catalog(array(
            'category' => $category_id,
            'per_page' => get_option('codecatalogo_products_per_page', 12),
            'layout' => 'grid',
            'columns' => '3',
            'show_filters' => 'yes',
            'show_search' => 'yes',
        ));
        echo $output;
        ?>
    </div>
    
</div>

<?php
get_footer();
