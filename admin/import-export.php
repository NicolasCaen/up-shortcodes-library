<?php
/**
 * Import/Export XML des shortcodes.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'up_sl_register_import_export_page', 25);

function up_sl_register_import_export_page(): void {
    add_submenu_page(
        'edit.php?post_type=up-shortcodes',
        __('Importer/Exporter', 'up-shortcodes-library'),
        __('Import/Export', 'up-shortcodes-library'),
        'manage_options',
        'up-sl-import-export',
        'up_sl_render_import_export_page'
    );
}

function up_sl_render_import_export_page(): void {
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Vous n’avez pas les permissions nécessaires pour accéder à cette page.', 'up-shortcodes-library'));
    }

    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['up_sl_ie_action']) && $_POST['up_sl_ie_action'] === 'export') {
            check_admin_referer('up_sl_export_shortcodes', 'up_sl_ie_nonce');
            up_sl_stream_export();
            exit;
        }

        if (isset($_POST['up_sl_ie_action']) && $_POST['up_sl_ie_action'] === 'import') {
            check_admin_referer('up_sl_import_shortcodes', 'up_sl_ie_nonce');

            if (! empty($_FILES['up_sl_ie_file']['tmp_name']) && is_uploaded_file($_FILES['up_sl_ie_file']['tmp_name'])) {
                $xml = file_get_contents($_FILES['up_sl_ie_file']['tmp_name']);
                $result = up_sl_import_from_xml($xml ?: '');

                if (is_wp_error($result)) {
                    $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-success"><p>' . esc_html__('Opération réalisée avec succès.', 'up-shortcodes-library') . '</p></div>';
                }
            } else {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Aucun fichier valide fourni.', 'up-shortcodes-library') . '</p></div>';
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Import/Export des shortcodes', 'up-shortcodes-library') . '</h1>';
    echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    echo '<h2>' . esc_html__('Exporter', 'up-shortcodes-library') . '</h2>';
    echo '<form method="post">';
    wp_nonce_field('up_sl_export_shortcodes', 'up_sl_ie_nonce');
    echo '<input type="hidden" name="up_sl_ie_action" value="export">';
    submit_button(__('Télécharger le fichier XML', 'up-shortcodes-library'));
    echo '</form>';

    echo '<hr>';

    echo '<h2>' . esc_html__('Importer', 'up-shortcodes-library') . '</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('up_sl_import_shortcodes', 'up_sl_ie_nonce');
    echo '<input type="hidden" name="up_sl_ie_action" value="import">';
    echo '<input type="file" name="up_sl_ie_file" accept="text/xml,application/xml">';
    submit_button(__('Importer le fichier XML', 'up-shortcodes-library'));
    echo '</form>';

    echo '</div>';
}

/**
 * Stream du fichier XML pour téléchargement.
 */
function up_sl_stream_export(): void {
    $xml = up_sl_generate_export_xml();

    nocache_headers();
    $filename = 'up-shortcodes-' . gmdate('Ymd-His') . '.xml';

    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xml));

    echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Génère le XML des shortcodes.
 */
function up_sl_generate_export_xml(): string {
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $root = $xml->createElement('shortcodes');
    $xml->appendChild($root);

    $posts = get_posts([
        'post_type'      => 'up-shortcodes',
        'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
        'posts_per_page' => -1,
    ]);

    foreach ($posts as $post) {
        $item = $xml->createElement('item');

        $post_node = $xml->createElement('post');
        $post_node->appendChild($xml->createElement('title', htmlspecialchars($post->post_title)));
        $post_node->appendChild($xml->createElement('slug', htmlspecialchars($post->post_name)));
        $post_node->appendChild($xml->createElement('status', htmlspecialchars($post->post_status)));

        $content_node = $xml->createElement('content');
        $content_node->appendChild($xml->createCDATASection($post->post_content));
        $post_node->appendChild($content_node);

        $excerpt_node = $xml->createElement('excerpt');
        $excerpt_node->appendChild($xml->createCDATASection($post->post_excerpt));
        $post_node->appendChild($excerpt_node);

        $item->appendChild($post_node);

        $meta_node = $xml->createElement('meta');

        $meta_fields = [
            'php_code'      => (string) get_post_meta($post->ID, '_up_sl_php_code', true),
            'js_code'       => (string) get_post_meta($post->ID, '_up_sl_js_code', true),
            'scss_code'     => (string) get_post_meta($post->ID, '_up_sl_scss_code', true),
            'generate_file' => (string) get_post_meta($post->ID, '_up_sl_generate_file', true),
        ];

        foreach ($meta_fields as $key => $value) {
            $meta_item = $xml->createElement($key);
            $meta_item->appendChild($xml->createCDATASection($value));
            $meta_node->appendChild($meta_item);
        }

        $item->appendChild($meta_node);

        $root->appendChild($item);
    }

    return $xml->saveXML() ?: '';
}

/**
 * Importe les shortcodes à partir d'un XML.
 */
function up_sl_import_from_xml(string $xml_string) {
    if ($xml_string === '') {
        return new WP_Error('up_sl_empty_xml', __('Le fichier XML est vide.', 'up-shortcodes-library'));
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_string);

    if (! $xml) {
        return new WP_Error('up_sl_invalid_xml', __('Impossible de parser le fichier XML.', 'up-shortcodes-library'));
    }

    $count = 0;

    foreach ($xml->item as $item) {
        $post_data = $item->post ?? null;
        if (! $post_data) {
            continue;
        }

        $title   = (string) ($post_data->title ?? '');
        $slug    = sanitize_title((string) ($post_data->slug ?? ''));
        $status  = (string) ($post_data->status ?? 'draft');
        $content = (string) ($post_data->content ?? '');
        $excerpt = (string) ($post_data->excerpt ?? '');

        $existing = $slug ? get_page_by_path($slug, OBJECT, 'up-shortcodes') : null;

        $postarr = [
            'post_title'   => $title,
            'post_name'    => $slug ?: sanitize_title($title ?: uniqid('shortcode-')),
            'post_status'  => $status ?: 'draft',
            'post_type'    => 'up-shortcodes',
            'post_content' => $content,
            'post_excerpt' => $excerpt,
        ];

        if ($existing) {
            $postarr['ID'] = $existing->ID;
            $post_id = wp_update_post($postarr, true);
        } else {
            $post_id = wp_insert_post($postarr, true);
        }

        if (is_wp_error($post_id)) {
            continue;
        }

        $meta = $item->meta ?? null;
        if ($meta) {
            $php_code      = (string) ($meta->php_code ?? '');
            $js_code       = (string) ($meta->js_code ?? '');
            $scss_code     = (string) ($meta->scss_code ?? '');
            $generate_file = (string) ($meta->generate_file ?? '');

            update_post_meta($post_id, '_up_sl_php_code', $php_code);
            update_post_meta($post_id, '_up_sl_js_code', $js_code);
            update_post_meta($post_id, '_up_sl_scss_code', $scss_code);
            update_post_meta($post_id, '_up_sl_generate_file', $generate_file === '1' ? '1' : '0');
        }

        up_sl_generate_files($post_id);
        $count++;
    }

    return $count;
}
