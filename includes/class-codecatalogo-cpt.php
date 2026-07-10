<?php
/**
 * Custom Post Type y Taxonomías
 */

if (!defined('ABSPATH')) {
    exit;
}

class CodeCatalogo_CPT {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_filter('template_include', array($this, 'load_templates'));
        add_filter('single_template', array($this, 'load_single_template'));
        add_filter('archive_template', array($this, 'load_archive_template'));

        // Agregar columna ID en categorías y etiquetas
        add_filter('manage_edit-codecatalogo_cat_columns', array($this, 'add_taxonomy_id_column'));
        add_filter('manage_edit-codecatalogo_tag_columns', array($this, 'add_taxonomy_id_column'));
        add_filter('manage_codecatalogo_cat_custom_column', array($this, 'show_taxonomy_id_column'), 10, 3);
        add_filter('manage_codecatalogo_tag_custom_column', array($this, 'show_taxonomy_id_column'), 10, 3);

        // Mejorar paginación y columnas del listado de productos
        add_filter('manage_codecatalogo_product_posts_columns', array($this, 'add_product_columns'));
        add_action('manage_codecatalogo_product_posts_custom_column', array($this, 'show_product_columns'), 10, 2);
                add_filter('manage_edit-codecatalogo_product_sortable_columns', array($this, 'sortable_product_columns'));
        // add_action('pre_get_posts', array($this, 'improve_products_query'));
    }
    
    /**
     * Registrar Custom Post Type
     */
    public function register_post_type() {
        $product_slug = get_option('codecatalogo_product_slug', 'producto');
        $catalog_slug = get_option('codecatalogo_catalog_slug', 'catalogo');
        
        $labels = array(
            'name'                  => _x('Productos', 'Post type general name','catalogo70free'),
            'singular_name'         => _x('Producto', 'Post type singular name','catalogo70free'),
            'menu_name'             => _x('Catálogo Pro', 'Admin Menu text','catalogo70free'),
            'name_admin_bar'        => _x('Producto', 'Add New on Toolbar','catalogo70free'),
            'add_new'               => __('Añadir Nuevo','catalogo70free'),
            'add_new_item'          => __('Añadir Nuevo Producto','catalogo70free'),
            'new_item'              => __('Nuevo Producto','catalogo70free'),
            'edit_item'             => __('Editar Producto','catalogo70free'),
            'view_item'             => __('Ver Producto','catalogo70free'),
            'all_items'             => __('Todos los Productos','catalogo70free'),
            'search_items'          => __('Buscar Productos','catalogo70free'),
            'parent_item_colon'     => __('Productos Padre:','catalogo70free'),
            'not_found'             => __('No se encontraron productos.','catalogo70free'),
            'not_found_in_trash'    => __('No se encontraron productos en la papelera.','catalogo70free'),
            'featured_image'        => _x('Imagen del Producto', 'Overrides the "Featured Image" phrase','catalogo70free'),
            'set_featured_image'    => _x('Establecer imagen del producto', 'Overrides the "Set featured image" phrase','catalogo70free'),
            'remove_featured_image' => _x('Remover imagen del producto', 'Overrides the "Remove featured image" phrase','catalogo70free'),
            'use_featured_image'    => _x('Usar como imagen del producto', 'Overrides the "Use as featured image" phrase','catalogo70free'),
            'archives'              => _x('Archivo de Productos', 'The post type archive label used in nav menus','catalogo70free'),
            'insert_into_item'      => _x('Insertar en producto', 'Overrides the "Insert into post" phrase','catalogo70free'),
            'uploaded_to_this_item' => _x('Subido a este producto', 'Overrides the "Uploaded to this post" phrase','catalogo70free'),
            'filter_items_list'     => _x('Filtrar lista de productos', 'Screen reader text for the filter links','catalogo70free'),
            'items_list_navigation' => _x('Navegación de productos', 'Screen reader text for the pagination','catalogo70free'),
            'items_list'            => _x('Lista de productos', 'Screen reader text for the items list','catalogo70free'),
        );
        
        $args = array(
            'labels'                => $labels,
            'description'           => __('Productos del catálogo','catalogo70free'),
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => array('slug' => $product_slug, 'with_front' => false),
            'capability_type'       => 'post',
            'has_archive'           => $catalog_slug,
            'hierarchical'          => false,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-products',
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'),
            'show_in_rest'          => true,
            'rest_base'             => 'productos',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        
        register_post_type('codecatalogo_product', $args);
    }
    
    /**
     * Registrar taxonomías
     */
    public function register_taxonomies() {
        // Categorías
        $cat_labels = array(
            'name'                       => _x('Categorías', 'taxonomy general name','catalogo70free'),
            'singular_name'              => _x('Categoría', 'taxonomy singular name','catalogo70free'),
            'search_items'               => __('Buscar Categorías','catalogo70free'),
            'popular_items'              => __('Categorías Populares','catalogo70free'),
            'all_items'                  => __('Todas las Categorías','catalogo70free'),
            'parent_item'                => __('Categoría Padre','catalogo70free'),
            'parent_item_colon'          => __('Categoría Padre:','catalogo70free'),
            'edit_item'                  => __('Editar Categoría','catalogo70free'),
            'update_item'                => __('Actualizar Categoría','catalogo70free'),
            'add_new_item'               => __('Añadir Nueva Categoría','catalogo70free'),
            'new_item_name'              => __('Nombre de Nueva Categoría','catalogo70free'),
            'separate_items_with_commas' => __('Separar categorías con comas','catalogo70free'),
            'add_or_remove_items'        => __('Añadir o remover categorías','catalogo70free'),
            'choose_from_most_used'      => __('Elegir de las más usadas','catalogo70free'),
            'not_found'                  => __('No se encontraron categorías.','catalogo70free'),
            'menu_name'                  => __('Categorías','catalogo70free'),
        );
        
        $cat_args = array(
            'hierarchical'          => true,
            'labels'                => $cat_labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => array('slug' => 'categoria-producto', 'hierarchical' => true),
            'show_in_rest'          => true,
            'rest_base'             => 'categorias',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
        );
        
        register_taxonomy('codecatalogo_cat', array('codecatalogo_product'), $cat_args);
        
        // Etiquetas
        $tag_labels = array(
            'name'                       => _x('Etiquetas', 'taxonomy general name','catalogo70free'),
            'singular_name'              => _x('Etiqueta', 'taxonomy singular name','catalogo70free'),
            'search_items'               => __('Buscar Etiquetas','catalogo70free'),
            'popular_items'              => __('Etiquetas Populares','catalogo70free'),
            'all_items'                  => __('Todas las Etiquetas','catalogo70free'),
            'edit_item'                  => __('Editar Etiqueta','catalogo70free'),
            'update_item'                => __('Actualizar Etiqueta','catalogo70free'),
            'add_new_item'               => __('Añadir Nueva Etiqueta','catalogo70free'),
            'new_item_name'              => __('Nombre de Nueva Etiqueta','catalogo70free'),
            'menu_name'                  => __('Etiquetas','catalogo70free'),
        );
        
        $tag_args = array(
            'hierarchical'          => false,
            'labels'                => $tag_labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => array('slug' => 'etiqueta-producto'),
            'show_in_rest'          => true,
        );
        
        register_taxonomy('codecatalogo_tag', array('codecatalogo_product'), $tag_args);
    }
    
    /**
     * Cargar templates personalizados
     */
    public function load_templates($template) {
        if (is_post_type_archive('codecatalogo_product') || is_tax('codecatalogo_cat') || is_tax('codecatalogo_tag')) {
            $custom_template = $this->load_archive_template($template);
            if ($custom_template) {
                return $custom_template;
            }
        }
        
        if (is_singular('codecatalogo_product')) {
            $custom_template = $this->load_single_template($template);
            if ($custom_template) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    public function load_single_template($template) {
        if (is_singular('codecatalogo_product')) {
            $plugin_template = CODECATALOGO_PATH . 'public/templates/single-producto.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
    
    public function load_archive_template($template) {
        if (is_post_type_archive('codecatalogo_product') || is_tax('codecatalogo_cat') || is_tax('codecatalogo_tag')) {
            $plugin_template = CODECATALOGO_PATH . 'public/templates/archive-producto.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    /**
     * Agregar columna ID a las taxonomías
     */
    public function add_taxonomy_id_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'name') {
                $new_columns['term_id'] = __('ID','catalogo70free');
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    /**
     * Mostrar el ID en la columna
     */
    public function show_taxonomy_id_column($content, $column_name, $term_id) {
        if ($column_name === 'term_id') {
            return '<strong>' . $term_id . '</strong>';
        }
        return $content;
    }

    /**
     * Agregar columnas personalizadas al listado de productos
     */
    public function add_product_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns['product_id'] = __('ID','catalogo70free');
            }
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['product_image'] = __('Imagen','catalogo70free');
            }
        }
        return $new_columns;
    }

    /**
     * Mostrar contenido de columnas personalizadas
     */
    public function show_product_columns($column, $post_id) {
        switch ($column) {
                        case 'product_id':
                echo '<strong>' . esc_html($post_id) . '</strong>';
                break;

                        case 'product_image':
                $thumbnail = get_the_post_thumbnail($post_id, array(50, 50));
                echo $thumbnail ? wp_kses_post($thumbnail) : '<span class="dashicons dashicons-format-image" style="font-size:50px;color:#ddd;"></span>';
                break;
        }
    }

    /**
     * Hacer columnas ordenables
     */
    public function sortable_product_columns($columns) {
        $columns['product_id'] = 'ID';
        return $columns;
    }

    /**
     * Mejorar query de productos en admin
     */
    public function improve_products_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        // Para el listado de productos en admin
        if ($query->get('post_type') === 'codecatalogo_product') {
            // Configurar posts por página desde settings
            $per_page = get_option('codecatalogo_products_per_page', 50);
            $query->set('posts_per_page', $per_page);

            // Ordenamiento por ID si se solicita
            if ($query->get('orderby') === 'ID') {
                $query->set('orderby', 'ID');
            }
        }
    }
}

// Inicializar
new CodeCatalogo_CPT();