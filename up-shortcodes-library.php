<?php
/**
 * Plugin Name: UP Shortcodes Library
 * Description: Gestion des shortcodes via un CPT avec génération automatique de fichiers.
 * Version: 1.2.0
 * Author: Nicolas Gehin
 * Text Domain: up-shortcodes-library
 */

if (! defined('ABSPATH')) {
    exit;
}

define('UP_SL_VERSION', '1.2.0');
define('UP_SL_PATH', plugin_dir_path(__FILE__));
define('UP_SL_URL', plugin_dir_url(__FILE__));

define('UP_SL_OPTION_KEY', 'up_sl_settings');

defaults_up_sl_register_settings();

require_once UP_SL_PATH . 'includes/helpers.php';
require_once UP_SL_PATH . 'includes/cpt-shortcodes.php';
require_once UP_SL_PATH . 'admin/admin-page.php';
require_once UP_SL_PATH . 'admin/import-export.php';

/**
 * Initialise les réglages par défaut à l'activation.
 */
function defaults_up_sl_register_settings(): void {
    if (! get_option(UP_SL_OPTION_KEY)) {
        $default_dir = trailingslashit(get_stylesheet_directory()) . 'shortcodes/';
        update_option(UP_SL_OPTION_KEY, [
            // Utilisé si base_location = custom
            'output_directory' => $default_dir,
            // Nouveaux réglages v1.1
            'base_location'    => 'theme',
            'relative_subdir'  => 'shortcodes',
            'wrap_in_subfolder'=> '1',
        ]);
    }
}

register_activation_hook(__FILE__, 'defaults_up_sl_register_settings');

/**
 * Enqueue des scripts/styles d'admin (CodeMirror) pour l'édition des shortcodes.
 */
add_action('admin_enqueue_scripts', function($hook){
    global $post;
    $is_edit_screen = ($hook === 'post.php');
    $is_new_screen  = ($hook === 'post-new.php');
    $is_shortcodes  = false;

    if ($is_edit_screen && $post && isset($post->post_type)) {
        $is_shortcodes = ($post->post_type === 'up-shortcodes');
    } elseif ($is_new_screen) {
        // Vérifier via post_type dans l'URL ou l'écran courant
        $pt = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
        if ($pt) {
            $is_shortcodes = ($pt === 'up-shortcodes');
        } else if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && isset($screen->post_type)) {
                $is_shortcodes = ($screen->post_type === 'up-shortcodes');
            }
        }
    }

    if (($is_edit_screen || $is_new_screen) && $is_shortcodes) {
        // Enqueue CodeMirror for PHP
        $php_settings = wp_enqueue_code_editor(['type' => 'text/x-php', 'codemirror' => ['theme' => 'monokai']]);
        // Enqueue CodeMirror for SCSS
        $scss_settings = wp_enqueue_code_editor(['type' => 'text/scss', 'codemirror' => ['theme' => 'monokai']]);
        // Enqueue CodeMirror for JS
        $js_settings = wp_enqueue_code_editor(['type' => 'javascript', 'codemirror' => ['theme' => 'monokai']]);
        // Enqueue CodeMirror for Markdown
        $md_settings = wp_enqueue_code_editor(['type' => 'text/markdown', 'codemirror' => ['theme' => 'monokai']]);

        wp_enqueue_script('code-editor');
        wp_enqueue_style('code-editor');

        wp_enqueue_style('up-sl-admin', UP_SL_URL . 'assets/admin.css', [], UP_SL_VERSION);
        wp_enqueue_script('up-sl-admin', UP_SL_URL . 'assets/admin.js', ['jquery', 'code-editor'], UP_SL_VERSION, true);

        // Passer les settings à JS
        wp_localize_script('up-sl-admin', 'upSLCodeMirrorSettings', [
            'php' => $php_settings,
            'scss' => $scss_settings,
            'js' => $js_settings,
            'md' => $md_settings,
        ]);
    }
});
