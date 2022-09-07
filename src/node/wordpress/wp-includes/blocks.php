<?php
 function remove_block_asset_path_prefix( $asset_handle_or_path ) { $path_prefix = 'file:'; if ( 0 !== strpos( $asset_handle_or_path, $path_prefix ) ) { return $asset_handle_or_path; } $path = substr( $asset_handle_or_path, strlen( $path_prefix ) ); if ( strpos( $path, './' ) === 0 ) { $path = substr( $path, 2 ); } return $path; } function generate_block_asset_handle( $block_name, $field_name ) { if ( 0 === strpos( $block_name, 'core/' ) ) { $asset_handle = str_replace( 'core/', 'wp-block-', $block_name ); if ( 0 === strpos( $field_name, 'editor' ) ) { $asset_handle .= '-editor'; } if ( 0 === strpos( $field_name, 'view' ) ) { $asset_handle .= '-view'; } return $asset_handle; } $field_mappings = array( 'editorScript' => 'editor-script', 'script' => 'script', 'viewScript' => 'view-script', 'editorStyle' => 'editor-style', 'style' => 'style', ); return str_replace( '/', '-', $block_name ) . '-' . $field_mappings[ $field_name ]; } function register_block_script_handle( $metadata, $field_name ) { if ( empty( $metadata[ $field_name ] ) ) { return false; } $script_handle = $metadata[ $field_name ]; $script_path = remove_block_asset_path_prefix( $metadata[ $field_name ] ); if ( $script_handle === $script_path ) { return $script_handle; } $script_handle = generate_block_asset_handle( $metadata['name'], $field_name ); $script_asset_path = wp_normalize_path( realpath( dirname( $metadata['file'] ) . '/' . substr_replace( $script_path, '.asset.php', - strlen( '.js' ) ) ) ); if ( ! file_exists( $script_asset_path ) ) { _doing_it_wrong( __FUNCTION__, sprintf( __( 'The asset file for the "%1$s" defined in "%2$s" block definition is missing.' ), $field_name, $metadata['name'] ), '5.5.0' ); return false; } $wpinc_path_norm = wp_normalize_path( realpath( ABSPATH . WPINC ) ); $theme_path_norm = wp_normalize_path( get_theme_file_path() ); $script_path_norm = wp_normalize_path( realpath( dirname( $metadata['file'] ) . '/' . $script_path ) ); $is_core_block = isset( $metadata['file'] ) && 0 === strpos( $metadata['file'], $wpinc_path_norm ); $is_theme_block = 0 === strpos( $script_path_norm, $theme_path_norm ); $script_uri = plugins_url( $script_path, $metadata['file'] ); if ( $is_core_block ) { $script_uri = includes_url( str_replace( $wpinc_path_norm, '', $script_path_norm ) ); } elseif ( $is_theme_block ) { $script_uri = get_theme_file_uri( str_replace( $theme_path_norm, '', $script_path_norm ) ); } $script_asset = require $script_asset_path; $script_dependencies = isset( $script_asset['dependencies'] ) ? $script_asset['dependencies'] : array(); $result = wp_register_script( $script_handle, $script_uri, $script_dependencies, isset( $script_asset['version'] ) ? $script_asset['version'] : false ); if ( ! $result ) { return false; } if ( ! empty( $metadata['textdomain'] ) && in_array( 'wp-i18n', $script_dependencies, true ) ) { wp_set_script_translations( $script_handle, $metadata['textdomain'] ); } return $script_handle; } function register_block_style_handle( $metadata, $field_name ) { if ( empty( $metadata[ $field_name ] ) ) { return false; } $wpinc_path_norm = wp_normalize_path( realpath( ABSPATH . WPINC ) ); $theme_path_norm = wp_normalize_path( get_theme_file_path() ); $is_core_block = isset( $metadata['file'] ) && 0 === strpos( $metadata['file'], $wpinc_path_norm ); if ( $is_core_block && ! wp_should_load_separate_core_block_assets() ) { return false; } $suffix = SCRIPT_DEBUG ? '' : '.min'; $style_handle = $metadata[ $field_name ]; $style_path = remove_block_asset_path_prefix( $metadata[ $field_name ] ); if ( $style_handle === $style_path && ! $is_core_block ) { return $style_handle; } $style_uri = plugins_url( $style_path, $metadata['file'] ); if ( $is_core_block ) { $style_path = "style$suffix.css"; $style_uri = includes_url( 'blocks/' . str_replace( 'core/', '', $metadata['name'] ) . "/style$suffix.css" ); } $style_path_norm = wp_normalize_path( realpath( dirname( $metadata['file'] ) . '/' . $style_path ) ); $is_theme_block = 0 === strpos( $style_path_norm, $theme_path_norm ); if ( $is_theme_block ) { $style_uri = get_theme_file_uri( str_replace( $theme_path_norm, '', $style_path_norm ) ); } $style_handle = generate_block_asset_handle( $metadata['name'], $field_name ); $block_dir = dirname( $metadata['file'] ); $style_file = realpath( "$block_dir/$style_path" ); $has_style_file = false !== $style_file; $version = ! $is_core_block && isset( $metadata['version'] ) ? $metadata['version'] : false; $style_uri = $has_style_file ? $style_uri : false; $result = wp_register_style( $style_handle, $style_uri, array(), $version ); if ( file_exists( str_replace( '.css', '-rtl.css', $style_file ) ) ) { wp_style_add_data( $style_handle, 'rtl', 'replace' ); } if ( $has_style_file ) { wp_style_add_data( $style_handle, 'path', $style_file ); } $rtl_file = str_replace( "$suffix.css", "-rtl$suffix.css", $style_file ); if ( is_rtl() && file_exists( $rtl_file ) ) { wp_style_add_data( $style_handle, 'path', $rtl_file ); } return $result ? $style_handle : false; } function get_block_metadata_i18n_schema() { static $i18n_block_schema; if ( ! isset( $i18n_block_schema ) ) { $i18n_block_schema = wp_json_file_decode( __DIR__ . '/block-i18n.json' ); } return $i18n_block_schema; } function register_block_type_from_metadata( $file_or_folder, $args = array() ) { $filename = 'block.json'; $metadata_file = ( substr( $file_or_folder, -strlen( $filename ) ) !== $filename ) ? trailingslashit( $file_or_folder ) . $filename : $file_or_folder; if ( ! file_exists( $metadata_file ) ) { return false; } $metadata = wp_json_file_decode( $metadata_file, array( 'associative' => true ) ); if ( ! is_array( $metadata ) || empty( $metadata['name'] ) ) { return false; } $metadata['file'] = wp_normalize_path( realpath( $metadata_file ) ); $metadata = apply_filters( 'block_type_metadata', $metadata ); if ( ! empty( $metadata['name'] ) && 0 === strpos( $metadata['name'], 'core/' ) ) { $block_name = str_replace( 'core/', '', $metadata['name'] ); if ( ! isset( $metadata['style'] ) ) { $metadata['style'] = "wp-block-$block_name"; } if ( ! isset( $metadata['editorStyle'] ) ) { $metadata['editorStyle'] = "wp-block-{$block_name}-editor"; } } $settings = array(); $property_mappings = array( 'apiVersion' => 'api_version', 'title' => 'title', 'category' => 'category', 'parent' => 'parent', 'icon' => 'icon', 'description' => 'description', 'keywords' => 'keywords', 'attributes' => 'attributes', 'providesContext' => 'provides_context', 'usesContext' => 'uses_context', 'supports' => 'supports', 'styles' => 'styles', 'variations' => 'variations', 'example' => 'example', ); $textdomain = ! empty( $metadata['textdomain'] ) ? $metadata['textdomain'] : null; $i18n_schema = get_block_metadata_i18n_schema(); foreach ( $property_mappings as $key => $mapped_key ) { if ( isset( $metadata[ $key ] ) ) { $settings[ $mapped_key ] = $metadata[ $key ]; if ( $textdomain && isset( $i18n_schema->$key ) ) { $settings[ $mapped_key ] = translate_settings_using_i18n_schema( $i18n_schema->$key, $settings[ $key ], $textdomain ); } } } if ( ! empty( $metadata['editorScript'] ) ) { $settings['editor_script'] = register_block_script_handle( $metadata, 'editorScript' ); } if ( ! empty( $metadata['script'] ) ) { $settings['script'] = register_block_script_handle( $metadata, 'script' ); } if ( ! empty( $metadata['viewScript'] ) ) { $settings['view_script'] = register_block_script_handle( $metadata, 'viewScript' ); } if ( ! empty( $metadata['editorStyle'] ) ) { $settings['editor_style'] = register_block_style_handle( $metadata, 'editorStyle' ); } if ( ! empty( $metadata['style'] ) ) { $settings['style'] = register_block_style_handle( $metadata, 'style' ); } $settings = apply_filters( 'block_type_metadata_settings', array_merge( $settings, $args ), $metadata ); return WP_Block_Type_Registry::get_instance()->register( $metadata['name'], $settings ); } function register_block_type( $block_type, $args = array() ) { if ( is_string( $block_type ) && file_exists( $block_type ) ) { return register_block_type_from_metadata( $block_type, $args ); } return WP_Block_Type_Registry::get_instance()->register( $block_type, $args ); } function unregister_block_type( $name ) { return WP_Block_Type_Registry::get_instance()->unregister( $name ); } function has_blocks( $post = null ) { if ( ! is_string( $post ) ) { $wp_post = get_post( $post ); if ( $wp_post instanceof WP_Post ) { $post = $wp_post->post_content; } } return false !== strpos( (string) $post, '<!-- wp:' ); } function has_block( $block_name, $post = null ) { if ( ! has_blocks( $post ) ) { return false; } if ( ! is_string( $post ) ) { $wp_post = get_post( $post ); if ( $wp_post instanceof WP_Post ) { $post = $wp_post->post_content; } } if ( false === strpos( $block_name, '/' ) ) { $block_name = 'core/' . $block_name; } $has_block = false !== strpos( $post, '<!-- wp:' . $block_name . ' ' ); if ( ! $has_block ) { $serialized_block_name = strip_core_block_namespace( $block_name ); if ( $serialized_block_name !== $block_name ) { $has_block = false !== strpos( $post, '<!-- wp:' . $serialized_block_name . ' ' ); } } return $has_block; } function get_dynamic_block_names() { $dynamic_block_names = array(); $block_types = WP_Block_Type_Registry::get_instance()->get_all_registered(); foreach ( $block_types as $block_type ) { if ( $block_type->is_dynamic() ) { $dynamic_block_names[] = $block_type->name; } } return $dynamic_block_names; } function serialize_block_attributes( $block_attributes ) { $encoded_attributes = wp_json_encode( $block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); $encoded_attributes = preg_replace( '/--/', '\\u002d\\u002d', $encoded_attributes ); $encoded_attributes = preg_replace( '/</', '\\u003c', $encoded_attributes ); $encoded_attributes = preg_replace( '/>/', '\\u003e', $encoded_attributes ); $encoded_attributes = preg_replace( '/&/', '\\u0026', $encoded_attributes ); $encoded_attributes = preg_replace( '/\\\\"/', '\\u0022', $encoded_attributes ); return $encoded_attributes; } function strip_core_block_namespace( $block_name = null ) { if ( is_string( $block_name ) && 0 === strpos( $block_name, 'core/' ) ) { return substr( $block_name, 5 ); } return $block_name; } function get_comment_delimited_block_content( $block_name, $block_attributes, $block_content ) { if ( is_null( $block_name ) ) { return $block_content; } $serialized_block_name = strip_core_block_namespace( $block_name ); $serialized_attributes = empty( $block_attributes ) ? '' : serialize_block_attributes( $block_attributes ) . ' '; if ( empty( $block_content ) ) { return sprintf( '<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes ); } return sprintf( '<!-- wp:%s %s-->%s<!-- /wp:%s -->', $serialized_block_name, $serialized_attributes, $block_content, $serialized_block_name ); } function serialize_block( $block ) { $block_content = ''; $index = 0; foreach ( $block['innerContent'] as $chunk ) { $block_content .= is_string( $chunk ) ? $chunk : serialize_block( $block['innerBlocks'][ $index++ ] ); } if ( ! is_array( $block['attrs'] ) ) { $block['attrs'] = array(); } return get_comment_delimited_block_content( $block['blockName'], $block['attrs'], $block_content ); } function serialize_blocks( $blocks ) { return implode( '', array_map( 'serialize_block', $blocks ) ); } function filter_block_content( $text, $allowed_html = 'post', $allowed_protocols = array() ) { $result = ''; $blocks = parse_blocks( $text ); foreach ( $blocks as $block ) { $block = filter_block_kses( $block, $allowed_html, $allowed_protocols ); $result .= serialize_block( $block ); } return $result; } function filter_block_kses( $block, $allowed_html, $allowed_protocols = array() ) { $block['attrs'] = filter_block_kses_value( $block['attrs'], $allowed_html, $allowed_protocols ); if ( is_array( $block['innerBlocks'] ) ) { foreach ( $block['innerBlocks'] as $i => $inner_block ) { $block['innerBlocks'][ $i ] = filter_block_kses( $inner_block, $allowed_html, $allowed_protocols ); } } return $block; } function filter_block_kses_value( $value, $allowed_html, $allowed_protocols = array() ) { if ( is_array( $value ) ) { foreach ( $value as $key => $inner_value ) { $filtered_key = filter_block_kses_value( $key, $allowed_html, $allowed_protocols ); $filtered_value = filter_block_kses_value( $inner_value, $allowed_html, $allowed_protocols ); if ( $filtered_key !== $key ) { unset( $value[ $key ] ); } $value[ $filtered_key ] = $filtered_value; } } elseif ( is_string( $value ) ) { return wp_kses( $value, $allowed_html, $allowed_protocols ); } return $value; } function excerpt_remove_blocks( $content ) { $allowed_inner_blocks = array( null, 'core/freeform', 'core/heading', 'core/html', 'core/list', 'core/media-text', 'core/paragraph', 'core/preformatted', 'core/pullquote', 'core/quote', 'core/table', 'core/verse', ); $allowed_wrapper_blocks = array( 'core/columns', 'core/column', 'core/group', ); $allowed_wrapper_blocks = apply_filters( 'excerpt_allowed_wrapper_blocks', $allowed_wrapper_blocks ); $allowed_blocks = array_merge( $allowed_inner_blocks, $allowed_wrapper_blocks ); $allowed_blocks = apply_filters( 'excerpt_allowed_blocks', $allowed_blocks ); $blocks = parse_blocks( $content ); $output = ''; foreach ( $blocks as $block ) { if ( in_array( $block['blockName'], $allowed_blocks, true ) ) { if ( ! empty( $block['innerBlocks'] ) ) { if ( in_array( $block['blockName'], $allowed_wrapper_blocks, true ) ) { $output .= _excerpt_render_inner_blocks( $block, $allowed_blocks ); continue; } foreach ( $block['innerBlocks'] as $inner_block ) { if ( ! in_array( $inner_block['blockName'], $allowed_inner_blocks, true ) || ! empty( $inner_block['innerBlocks'] ) ) { continue 2; } } } $output .= render_block( $block ); } } return $output; } function _excerpt_render_inner_blocks( $parsed_block, $allowed_blocks ) { $output = ''; foreach ( $parsed_block['innerBlocks'] as $inner_block ) { if ( ! in_array( $inner_block['blockName'], $allowed_blocks, true ) ) { continue; } if ( empty( $inner_block['innerBlocks'] ) ) { $output .= render_block( $inner_block ); } else { $output .= _excerpt_render_inner_blocks( $inner_block, $allowed_blocks ); } } return $output; } function render_block( $parsed_block ) { global $post; $parent_block = null; $pre_render = apply_filters( 'pre_render_block', null, $parsed_block, $parent_block ); if ( ! is_null( $pre_render ) ) { return $pre_render; } $source_block = $parsed_block; $parsed_block = apply_filters( 'render_block_data', $parsed_block, $source_block, $parent_block ); $context = array(); if ( $post instanceof WP_Post ) { $context['postId'] = $post->ID; $context['postType'] = $post->post_type; } $context = apply_filters( 'render_block_context', $context, $parsed_block, $parent_block ); $block = new WP_Block( $parsed_block, $context ); return $block->render(); } function parse_blocks( $content ) { $parser_class = apply_filters( 'block_parser_class', 'WP_Block_Parser' ); $parser = new $parser_class(); return $parser->parse( $content ); } function do_blocks( $content ) { $blocks = parse_blocks( $content ); $output = ''; foreach ( $blocks as $block ) { $output .= render_block( $block ); } $priority = has_filter( 'the_content', 'wpautop' ); if ( false !== $priority && doing_filter( 'the_content' ) && has_blocks( $content ) ) { remove_filter( 'the_content', 'wpautop', $priority ); add_filter( 'the_content', '_restore_wpautop_hook', $priority + 1 ); } return $output; } function _restore_wpautop_hook( $content ) { $current_priority = has_filter( 'the_content', '_restore_wpautop_hook' ); add_filter( 'the_content', 'wpautop', $current_priority - 1 ); remove_filter( 'the_content', '_restore_wpautop_hook', $current_priority ); return $content; } function block_version( $content ) { return has_blocks( $content ) ? 1 : 0; } function register_block_style( $block_name, $style_properties ) { return WP_Block_Styles_Registry::get_instance()->register( $block_name, $style_properties ); } function unregister_block_style( $block_name, $block_style_name ) { return WP_Block_Styles_Registry::get_instance()->unregister( $block_name, $block_style_name ); } function block_has_support( $block_type, $feature, $default = false ) { $block_support = $default; if ( $block_type && property_exists( $block_type, 'supports' ) ) { $block_support = _wp_array_get( $block_type->supports, $feature, $default ); } return true === $block_support || is_array( $block_support ); } function wp_migrate_old_typography_shape( $metadata ) { if ( ! isset( $metadata['supports'] ) ) { return $metadata; } $typography_keys = array( '__experimentalFontFamily', '__experimentalFontStyle', '__experimentalFontWeight', '__experimentalLetterSpacing', '__experimentalTextDecoration', '__experimentalTextTransform', 'fontSize', 'lineHeight', ); foreach ( $typography_keys as $typography_key ) { $support_for_key = _wp_array_get( $metadata['supports'], array( $typography_key ), null ); if ( null !== $support_for_key ) { _doing_it_wrong( 'register_block_type_from_metadata()', sprintf( __( 'Block "%1$s" is declaring %2$s support in %3$s file under %4$s. %2$s support is now declared under %5$s.' ), $metadata['name'], "<code>$typography_key</code>", '<code>block.json</code>', "<code>supports.$typography_key</code>", "<code>supports.typography.$typography_key</code>" ), '5.8.0' ); _wp_array_set( $metadata['supports'], array( 'typography', $typography_key ), $support_for_key ); unset( $metadata['supports'][ $typography_key ] ); } } return $metadata; } function build_query_vars_from_query_block( $block, $page ) { $query = array( 'post_type' => 'post', 'order' => 'DESC', 'orderby' => 'date', 'post__not_in' => array(), ); if ( isset( $block->context['query'] ) ) { if ( ! empty( $block->context['query']['postType'] ) ) { $post_type_param = $block->context['query']['postType']; if ( is_post_type_viewable( $post_type_param ) ) { $query['post_type'] = $post_type_param; } } if ( isset( $block->context['query']['sticky'] ) && ! empty( $block->context['query']['sticky'] ) ) { $sticky = get_option( 'sticky_posts' ); if ( 'only' === $block->context['query']['sticky'] ) { $query['post__in'] = $sticky; } else { $query['post__not_in'] = array_merge( $query['post__not_in'], $sticky ); } } if ( ! empty( $block->context['query']['exclude'] ) ) { $excluded_post_ids = array_map( 'intval', $block->context['query']['exclude'] ); $excluded_post_ids = array_filter( $excluded_post_ids ); $query['post__not_in'] = array_merge( $query['post__not_in'], $excluded_post_ids ); } if ( isset( $block->context['query']['perPage'] ) && is_numeric( $block->context['query']['perPage'] ) ) { $per_page = absint( $block->context['query']['perPage'] ); $offset = 0; if ( isset( $block->context['query']['offset'] ) && is_numeric( $block->context['query']['offset'] ) ) { $offset = absint( $block->context['query']['offset'] ); } $query['offset'] = ( $per_page * ( $page - 1 ) ) + $offset; $query['posts_per_page'] = $per_page; } if ( ! empty( $block->context['query']['categoryIds'] ) || ! empty( $block->context['query']['tagIds'] ) ) { $tax_query = array(); if ( ! empty( $block->context['query']['categoryIds'] ) ) { $tax_query[] = array( 'taxonomy' => 'category', 'terms' => array_filter( array_map( 'intval', $block->context['query']['categoryIds'] ) ), 'include_children' => false, ); } if ( ! empty( $block->context['query']['tagIds'] ) ) { $tax_query[] = array( 'taxonomy' => 'post_tag', 'terms' => array_filter( array_map( 'intval', $block->context['query']['tagIds'] ) ), 'include_children' => false, ); } $query['tax_query'] = $tax_query; } if ( ! empty( $block->context['query']['taxQuery'] ) ) { $query['tax_query'] = array(); foreach ( $block->context['query']['taxQuery'] as $taxonomy => $terms ) { if ( is_taxonomy_viewable( $taxonomy ) && ! empty( $terms ) ) { $query['tax_query'][] = array( 'taxonomy' => $taxonomy, 'terms' => array_filter( array_map( 'intval', $terms ) ), 'include_children' => false, ); } } } if ( isset( $block->context['query']['order'] ) && in_array( strtoupper( $block->context['query']['order'] ), array( 'ASC', 'DESC' ), true ) ) { $query['order'] = strtoupper( $block->context['query']['order'] ); } if ( isset( $block->context['query']['orderBy'] ) ) { $query['orderby'] = $block->context['query']['orderBy']; } if ( isset( $block->context['query']['author'] ) && (int) $block->context['query']['author'] > 0 ) { $query['author'] = (int) $block->context['query']['author']; } if ( ! empty( $block->context['query']['search'] ) ) { $query['s'] = $block->context['query']['search']; } } return $query; } function get_query_pagination_arrow( $block, $is_next ) { $arrow_map = array( 'none' => '', 'arrow' => array( 'next' => '→', 'previous' => '←', ), 'chevron' => array( 'next' => '»', 'previous' => '«', ), ); if ( ! empty( $block->context['paginationArrow'] ) && array_key_exists( $block->context['paginationArrow'], $arrow_map ) && ! empty( $arrow_map[ $block->context['paginationArrow'] ] ) ) { $pagination_type = $is_next ? 'next' : 'previous'; $arrow_attribute = $block->context['paginationArrow']; $arrow = $arrow_map[ $block->context['paginationArrow'] ][ $pagination_type ]; $arrow_classes = "wp-block-query-pagination-$pagination_type-arrow is-arrow-$arrow_attribute"; return "<span class='$arrow_classes'>$arrow</span>"; } return null; } function _wp_multiple_block_styles( $metadata ) { foreach ( array( 'style', 'editorStyle' ) as $key ) { if ( ! empty( $metadata[ $key ] ) && is_array( $metadata[ $key ] ) ) { $default_style = array_shift( $metadata[ $key ] ); foreach ( $metadata[ $key ] as $handle ) { $args = array( 'handle' => $handle ); if ( 0 === strpos( $handle, 'file:' ) && isset( $metadata['file'] ) ) { $style_path = remove_block_asset_path_prefix( $handle ); $theme_path_norm = wp_normalize_path( get_theme_file_path() ); $style_path_norm = wp_normalize_path( realpath( dirname( $metadata['file'] ) . '/' . $style_path ) ); $is_theme_block = isset( $metadata['file'] ) && 0 === strpos( $metadata['file'], $theme_path_norm ); $style_uri = plugins_url( $style_path, $metadata['file'] ); if ( $is_theme_block ) { $style_uri = get_theme_file_uri( str_replace( $theme_path_norm, '', $style_path_norm ) ); } $args = array( 'handle' => sanitize_key( "{$metadata['name']}-{$style_path}" ), 'src' => $style_uri, ); } wp_enqueue_block_style( $metadata['name'], $args ); } $metadata[ $key ] = $default_style; } } return $metadata; } add_filter( 'block_type_metadata', '_wp_multiple_block_styles' ); function build_comment_query_vars_from_block( $block ) { $comment_args = array( 'orderby' => 'comment_date_gmt', 'order' => 'ASC', 'status' => 'approve', 'no_found_rows' => false, ); if ( is_user_logged_in() ) { $comment_args['include_unapproved'] = array( get_current_user_id() ); } else { $unapproved_email = wp_get_unapproved_comment_author_email(); if ( $unapproved_email ) { $comment_args['include_unapproved'] = array( $unapproved_email ); } } if ( ! empty( $block->context['postId'] ) ) { $comment_args['post_id'] = (int) $block->context['postId']; } if ( get_option( 'thread_comments' ) ) { $comment_args['hierarchical'] = 'threaded'; } else { $comment_args['hierarchical'] = false; } if ( get_option( 'page_comments' ) === '1' || get_option( 'page_comments' ) === true ) { $per_page = get_option( 'comments_per_page' ); $default_page = get_option( 'default_comments_page' ); if ( $per_page > 0 ) { $comment_args['number'] = $per_page; $page = (int) get_query_var( 'cpage' ); if ( $page ) { $comment_args['paged'] = $page; } elseif ( 'oldest' === $default_page ) { $comment_args['paged'] = 1; } elseif ( 'newest' === $default_page ) { $max_num_pages = (int) ( new WP_Comment_Query( $comment_args ) )->max_num_pages; if ( 0 !== $max_num_pages ) { $comment_args['paged'] = $max_num_pages; } } if ( 0 === $page && isset( $comment_args['paged'] ) && $comment_args['paged'] > 0 ) { set_query_var( 'cpage', $comment_args['paged'] ); } } } return $comment_args; } function get_comments_pagination_arrow( $block, $pagination_type = 'next' ) { $arrow_map = array( 'none' => '', 'arrow' => array( 'next' => '→', 'previous' => '←', ), 'chevron' => array( 'next' => '»', 'previous' => '«', ), ); if ( ! empty( $block->context['comments/paginationArrow'] ) && ! empty( $arrow_map[ $block->context['comments/paginationArrow'] ][ $pagination_type ] ) ) { $arrow_attribute = $block->context['comments/paginationArrow']; $arrow = $arrow_map[ $block->context['comments/paginationArrow'] ][ $pagination_type ]; $arrow_classes = "wp-block-comments-pagination-$pagination_type-arrow is-arrow-$arrow_attribute"; return "<span class='$arrow_classes'>$arrow</span>"; } return null; } 