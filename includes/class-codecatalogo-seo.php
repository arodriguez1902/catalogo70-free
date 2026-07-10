<?php
/**
 * Gestor de SEO y Schema Markup
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_SEO {
    
    private $field_manager;
    
    public function __construct() {
        $this->field_manager = new CodeCatalogo_Field_Manager();
        
        add_action('wp_head', array($this, 'add_schema_markup'), 5);
        add_filter('document_title_parts', array($this, 'modify_title'), 10, 1);
        add_action('wp_head', array($this, 'add_meta_tags'), 1);
        // REMOVIDO: add_filter('the_content', array($this, 'add_structured_data_to_content'));
    }
    
    /**
     * Agregar Schema.org markup
     */
    public function add_schema_markup() {
        if (!is_singular('codecatalogo_product')) {
            return;
        }
        
        if (!get_option('codecatalogo_enable_schema', 1)) {
            return;
        }
        
        global $post;
        
        $schema = $this->generate_product_schema($post->ID);
        
        if ($schema) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            echo "\n" . '</script>' . "\n";
        }
    }
    
    /**
     * Generar Schema.org para producto
     */
    private function generate_product_schema($product_id) {
        $product = get_post($product_id);
        
        if (!$product) {
            return false;
        }
        
        $fields = $this->field_manager->get_product_fields($product_id);
        
        // Schema básico
        $schema = array(
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => get_the_title($product_id),
            'description' => $this->get_product_description($product_id),
            'url' => get_permalink($product_id),
        );
        
        // Imagen
        if (has_post_thumbnail($product_id)) {
            $image_id = get_post_thumbnail_id($product_id);
            $image_url = wp_get_attachment_image_src($image_id, 'full');
            
            if ($image_url) {
                $schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $image_url[0],
                    'width' => $image_url[1],
                    'height' => $image_url[2],
                );
            }
        }
        
        // Marca
        $marca_field = null;
        foreach ($fields as $field) {
            if ($field->field_name === 'marca' && !empty($field->field_value)) {
                $marca_field = $field->field_value;
                break;
            }
        }
        
        if ($marca_field) {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => $marca_field,
            );
        }
        
        // SKU o Modelo
        $modelo_field = null;
        foreach ($fields as $field) {
            if ($field->field_name === 'modelo' && !empty($field->field_value)) {
                $modelo_field = $field->field_value;
                break;
            }
        }
        
        if ($modelo_field) {
            $schema['sku'] = $modelo_field;
            $schema['model'] = $modelo_field;
        } else {
            $schema['sku'] = 'PROD-' . $product_id;
        }
        
        // Propiedades adicionales desde campos personalizados
        $additional_properties = array();
        
        foreach ($fields as $field) {
            if ($field->is_seo_relevant && !empty($field->field_value)) {
                $additional_properties[] = array(
                    '@type' => 'PropertyValue',
                    'name' => $field->field_label,
                    'value' => $field->field_value,
                );
            }
        }
        
        if (!empty($additional_properties)) {
            $schema['additionalProperty'] = $additional_properties;
        }
        
        // Categorías
        $categories = get_the_terms($product_id, 'codecatalogo_cat');
        if ($categories && !is_wp_error($categories)) {
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            $schema['category'] = implode(', ', $category_names);
        }
        
        // Información de la organización
        $schema['offers'] = array(
            '@type' => 'Offer',
            'availability' => 'https://schema.org/InStock',
            'url' => get_permalink($product_id),
        );
        
        // Agregar organizacion
        $schema['manufacturer'] = array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
        );
        
        return $schema;
    }
    
    /**
     * Obtener descripción del producto
     */
    private function get_product_description($product_id) {
        $excerpt = get_the_excerpt($product_id);
        
        if (!empty($excerpt)) {
            return wp_strip_all_tags($excerpt);
        }
        
        $content = get_post_field('post_content', $product_id);
        $content = wp_strip_all_tags($content);
        $content = wp_trim_words($content, 30, '...');
        
        return $content;
    }
    
    /**
     * Modificar título de la página
     */
    public function modify_title($title) {
        if (!is_singular('codecatalogo_product')) {
            return $title;
        }
        
        global $post;
        
        $fields = $this->field_manager->get_product_fields($post->ID);
        
        // Intentar mejorar el título con información relevante
        $marca = '';
        $modelo = '';
        
        foreach ($fields as $field) {
            if ($field->field_name === 'marca' && !empty($field->field_value)) {
                $marca = $field->field_value;
            }
            if ($field->field_name === 'modelo' && !empty($field->field_value)) {
                $modelo = $field->field_value;
            }
        }
        
        if ($marca || $modelo) {
            $enhanced_title = $title['title'];
            
            if ($marca && $modelo) {
                $enhanced_title .= ' - ' . $marca . ' ' . $modelo;
            } elseif ($marca) {
                $enhanced_title .= ' - ' . $marca;
            } elseif ($modelo) {
                $enhanced_title .= ' - ' . $modelo;
            }
            
            $title['title'] = $enhanced_title;
        }
        
        return $title;
    }
    
    /**
     * Agregar meta tags
     */
    public function add_meta_tags() {
        if (!is_singular('codecatalogo_product')) {
            return;
        }
        
        if (!get_option('codecatalogo_enable_seo', 1)) {
            return;
        }
        
        global $post;
        
        $description = $this->get_product_description($post->ID);
        $image_url = get_the_post_thumbnail_url($post->ID, 'large');
        
        // Meta description
        if ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
        
        // Open Graph
        echo '<meta property="og:type" content="product">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr(get_the_title($post->ID)) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '">' . "\n";
        
        if ($image_url) {
            echo '<meta property="og:image" content="' . esc_url($image_url) . '">' . "\n";
        }
        
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        
        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr(get_the_title($post->ID)) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        
        if ($image_url) {
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '">' . "\n";
        }
        
        // Canonical
        echo '<link rel="canonical" href="' . esc_url(get_permalink($post->ID)) . '">' . "\n";
    }
    
    /**
     * Generar breadcrumbs
     */
    public function render_breadcrumbs($product_id = null) {
        if (!$product_id) {
            global $post;
            $product_id = $post->ID;
        }
        
        $breadcrumbs = array();
        
        // Home
        $breadcrumbs[] = array(
            'name' => __('Inicio','catalogo70free'),
            'url' => home_url('/'),
        );
        
        // Catálogo
        $catalog_slug = get_option('codecatalogo_catalog_slug', 'catalogo');
        $breadcrumbs[] = array(
            'name' => __('Catálogo','catalogo70free'),
            'url' => home_url('/' . $catalog_slug . '/'),
        );
        
        // Categorías
        $categories = get_the_terms($product_id, 'codecatalogo_cat');
        if ($categories && !is_wp_error($categories)) {
            $category = reset($categories);
            $breadcrumbs[] = array(
                'name' => $category->name,
                'url' => get_term_link($category),
            );
        }
        
        // Producto actual
        $breadcrumbs[] = array(
            'name' => get_the_title($product_id),
            'url' => get_permalink($product_id),
        );
        
        // Renderizar
        $output = '<nav class="codecatalogo-breadcrumbs" aria-label="breadcrumb">';
        $output .= '<ol class="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">';
        
        foreach ($breadcrumbs as $index => $crumb) {
            $position = $index + 1;
            $is_last = ($position === count($breadcrumbs));
            
            $output .= '<li class="breadcrumb-item' . ($is_last ? ' active' : '') . '" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            
            if (!$is_last) {
                $output .= '<a itemprop="item" href="' . esc_url($crumb['url']) . '">';
                $output .= '<span itemprop="name">' . esc_html($crumb['name']) . '</span>';
                $output .= '</a>';
            } else {
                $output .= '<span itemprop="name">' . esc_html($crumb['name']) . '</span>';
            }
            
            $output .= '<meta itemprop="position" content="' . $position . '">';
            $output .= '</li>';
        }
        
        $output .= '</ol>';
        $output .= '</nav>';
        
        return $output;
    }
}

// Inicializar
new CodeCatalogo_SEO();