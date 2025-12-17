<?php
/**
 * Import/Export XML des shortcodes.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Importe un XML par défaut depuis le plugin.
 * Par défaut: defaults/up-shortcodes-default.xml dans le plugin.
 * Filtre: 'up_sl_plugin_default_xml_path' pour modifier le chemin.
 */
function up_sl_import_defaults_from_plugin() {
    $default = UP_SL_PATH . 'defaults/up-shortcodes-default.xml';
    /**
     * Permet d'écraser le chemin par défaut fourni par le plugin.
     * @param string $default
     */
    $file = (string) apply_filters('up_sl_plugin_default_xml_path', $default);

    if (!file_exists($file)) {
        // Fallback: essayer le thème si le plugin n'embarque pas le fichier
        return up_sl_import_defaults_from_theme();
    }

    $xml = file_get_contents($file);
    if ($xml === false) {
        return new WP_Error('up_sl_defaults_read', __('Impossible de lire le fichier XML par défaut du plugin.', 'up-shortcodes-library'));
    }

    return up_sl_import_from_xml($xml);
}

add_action('admin_menu', 'up_sl_register_import_export_page', 25);
add_action('admin_post_up_sl_export_all', 'up_sl_handle_export_all');

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

        if (isset($_POST['up_sl_ie_action']) && $_POST['up_sl_ie_action'] === 'import_defaults') {
            check_admin_referer('up_sl_import_defaults', 'up_sl_ie_nonce');
            $result = up_sl_import_defaults_from_plugin();
            if (is_wp_error($result)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                $notice = '<div class="notice notice-success"><p>' . esc_html__('Import par défaut effectué.', 'up-shortcodes-library') . '</p></div>';
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Import/Export des shortcodes', 'up-shortcodes-library') . '</h1>';
    echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    echo '<h2>' . esc_html__('Exporter', 'up-shortcodes-library') . '</h2>';
    $export_url = add_query_arg([
        'action'   => 'up_sl_export_all',
        '_wpnonce' => wp_create_nonce('up_sl_export_all'),
    ], admin_url('admin-post.php'));
    echo '<p><a href="' . esc_url($export_url) . '" class="button button-primary">' . esc_html__('Exporter tous les shortcodes (XML)', 'up-shortcodes-library') . '</a></p>';

    echo '<hr>';

    echo '<h2>' . esc_html__('Importer', 'up-shortcodes-library') . '</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('up_sl_import_shortcodes', 'up_sl_ie_nonce');
    echo '<input type="hidden" name="up_sl_ie_action" value="import">';
    echo '<input type="file" name="up_sl_ie_file" accept="text/xml,application/xml">';
    submit_button(__('Importer depuis un fichier XML', 'up-shortcodes-library'));
    echo '</form>';

    echo '<form method="post" style="margin-top:16px;">';
    wp_nonce_field('up_sl_import_defaults', 'up_sl_ie_nonce');
    echo '<input type="hidden" name="up_sl_ie_action" value="import_defaults">';
    submit_button(__('Importer les shortcodes par défaut (plugin)', 'up-shortcodes-library'), 'secondary');
    echo '</form>';

    echo '</div>';
}

/**
 * Stream du fichier XML pour téléchargement.
 */
function up_sl_handle_export_all(): void {
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Permissions insuffisantes.', 'up-shortcodes-library'));
    }
    check_admin_referer('up_sl_export_all');

    $xml = up_sl_generate_export_xml();
    $filename = 'up-shortcodes-' . gmdate('Ymd-His') . '.xml';
    up_sl_send_download($filename, $xml, 'application/xml; charset=utf-8');
}

function up_sl_send_download(string $filename, string $content, string $content_type = 'application/octet-stream'): void {
    if (function_exists('ob_get_level')) {
        while (ob_get_level()) {
            @ob_end_clean();
        }
    }
    nocache_headers();
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename=' . sanitize_file_name($filename));
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . strlen($content));
    echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
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
        $item = up_sl_create_xml_item($xml, $post);
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

        // Utiliser la chaîne 'OBJECT' au lieu de la constante pour satisfaire certains analyseurs statiques
        $existing = $slug ? get_page_by_path($slug, 'OBJECT', 'up-shortcodes') : null;

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
            $readme_code   = (string) ($meta->readme_code ?? '');
            // Nouveaux flags
            $gen_php       = (string) ($meta->generate_php_file ?? '');
            $gen_css       = (string) ($meta->generate_css_file ?? '');
            $gen_js        = (string) ($meta->generate_js_file ?? '');
            $gen_scss      = (string) ($meta->generate_scss_file ?? '');
            $gen_readme    = (string) ($meta->generate_readme_file ?? '');
            // Compat rétro
            $generate_file = (string) ($meta->generate_file ?? '');

            update_post_meta($post_id, '_up_sl_php_code', $php_code);
            update_post_meta($post_id, '_up_sl_js_code', $js_code);
            update_post_meta($post_id, '_up_sl_scss_code', $scss_code);
            update_post_meta($post_id, '_up_sl_readme_code', $readme_code);

            if ($gen_php !== '') update_post_meta($post_id, '_up_sl_generate_php_file', $gen_php === '1' ? '1' : '0');
            if ($gen_css !== '') update_post_meta($post_id, '_up_sl_generate_css_file', $gen_css === '1' ? '1' : '0');
            if ($gen_js !== '')  update_post_meta($post_id, '_up_sl_generate_js_file',  $gen_js  === '1' ? '1' : '0');
            if ($gen_scss !== '')update_post_meta($post_id, '_up_sl_generate_scss_file',$gen_scss=== '1' ? '1' : '0');
            if ($gen_readme !== '')update_post_meta($post_id, '_up_sl_generate_readme_file',$gen_readme=== '1' ? '1' : '0');

            if ($generate_file !== '') {
                update_post_meta($post_id, '_up_sl_generate_file', $generate_file === '1' ? '1' : '0');
            }
        }

        up_sl_generate_files($post_id);
        $count++;
    }

    return $count;
}

/**
 * Crée un noeud XML <item> pour un post donné.
 */
function up_sl_create_xml_item(DOMDocument $xml, WP_Post $post): DOMElement {
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
        'php_code'              => (string) get_post_meta($post->ID, '_up_sl_php_code', true),
        'js_code'               => (string) get_post_meta($post->ID, '_up_sl_js_code', true),
        'scss_code'             => (string) get_post_meta($post->ID, '_up_sl_scss_code', true),
        'readme_code'           => (string) get_post_meta($post->ID, '_up_sl_readme_code', true),
        // Flags de génération (nouvelle logique)
        'generate_php_file'     => (string) get_post_meta($post->ID, '_up_sl_generate_php_file', true),
        'generate_css_file'     => (string) get_post_meta($post->ID, '_up_sl_generate_css_file', true),
        'generate_js_file'      => (string) get_post_meta($post->ID, '_up_sl_generate_js_file', true),
        'generate_scss_file'    => (string) get_post_meta($post->ID, '_up_sl_generate_scss_file', true),
        'generate_readme_file'  => (string) get_post_meta($post->ID, '_up_sl_generate_readme_file', true),
        // Compat rétro
        'generate_file'         => (string) get_post_meta($post->ID, '_up_sl_generate_file', true),
    ];

    foreach ($meta_fields as $key => $value) {
        $meta_item = $xml->createElement($key);
        $meta_item->appendChild($xml->createCDATASection($value));
        $meta_node->appendChild($meta_item);
    }

    $item->appendChild($meta_node);

    return $item;
}

/**
 * Sauvegarde (ajoute ou met à jour) un shortcode dans le fichier XML par défaut du plugin.
 */
function up_sl_save_to_defaults(int $post_id): void {
    $target_file = UP_SL_PATH . 'defaults/up-shortcodes-default.xml';
    
    // Créer le dossier defaults s'il n'existe pas
    $dir = dirname($target_file);
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->preserveWhiteSpace = false;
    $xml->formatOutput = true;

    if (file_exists($target_file)) {
        // Charger existant
        $loaded = @$xml->load($target_file);
        if (!$loaded) {
            // Si corrompu ou vide, on repart de zéro
            $root = $xml->createElement('shortcodes');
            $xml->appendChild($root);
        }
    } else {
        // Nouveau
        $root = $xml->createElement('shortcodes');
        $xml->appendChild($root);
    }

    $xpath = new DOMXPath($xml);
    $post = get_post($post_id);
    if (!$post) return;

    // Vérifier si le slug existe déjà dans le XML
    $slug = $post->post_name;
    // On cherche un <slug> qui contient exactement le slug
    // XPath 1.0 n'est pas très friendly pour ça mais on suppose structure fixe
    $query = "//item[post/slug='$slug']";
    $entries = $xpath->query($query);

    // Si trouvé, on supprime l'ancien item
    if ($entries->length > 0) {
        $old_item = $entries->item(0);
        $old_item->parentNode->removeChild($old_item);
    }

    // Créer le nouvel item
    // Note: up_sl_create_xml_item attend que le noeud appartienne au document passed
    // Mais ici le document est $xml (celui qu'on vient de créer/charger)
    // Donc ça marche si on passe $xml
    $new_item = up_sl_create_xml_item($xml, $post);

    // Ajouter à la racine (ou <shortcodes>)
    $xml->documentElement->appendChild($new_item);

    // Sauvegarder
    $xml->save($target_file);
}

/**
 * Importe un XML par défaut depuis le thème.
 * Permet au thème de fournir un fichier d'exemples via différents emplacements.
 * Filtre: 'up_sl_theme_default_xml_candidates' pour modifier la liste des chemins candidats.
 */
function up_sl_import_defaults_from_theme() {
    $theme_dir = wp_normalize_path(get_stylesheet_directory());
    $candidates = [
        $theme_dir . '/shortcodes/up-shortcodes-default.xml',
        $theme_dir . '/defaults/up-shortcodes-default.xml',
        $theme_dir . '/up-shortcodes-default.xml',
    ];

    /**
     * Permet d'ajouter/retirer des chemins candidats.
     * @param array $candidates
     */
    $candidates = (array) apply_filters('up_sl_theme_default_xml_candidates', $candidates);

    $file = '';
    foreach ($candidates as $path) {
        if (is_string($path) && $path !== '' && file_exists($path)) {
            $file = $path;
            break;
        }
    }

    if ($file === '') {
        return new WP_Error('up_sl_defaults_missing', __('Fichier XML par défaut introuvable dans le thème.', 'up-shortcodes-library'));
    }

    $xml = file_get_contents($file);
    if ($xml === false) {
        return new WP_Error('up_sl_defaults_read', __('Impossible de lire le fichier XML par défaut.', 'up-shortcodes-library'));
    }

    return up_sl_import_from_xml($xml);
}
