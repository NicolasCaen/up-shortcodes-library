<?php
/**
 * Helpers pour UP Shortcodes Library.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Retourne les réglages du plugin.
 */
function up_sl_get_settings(): array {
    $defaults = [
        // Pour mode "custom" si sélectionné
        'output_directory' => trailingslashit(get_stylesheet_directory()) . 'shortcodes/',
        // Sélection d'emplacement sans saisir un chemin complet
        'base_location' => 'theme', // theme | mu | plugin | custom
        'relative_subdir' => 'shortcodes',
        // Par défaut on englobe chaque shortcode dans son sous-dossier
        'wrap_in_subfolder' => '1',
    ];

    return wp_parse_args((array) get_option(UP_SL_OPTION_KEY, []), $defaults);
}

/**
 * Retourne le chemin de sortie choisi pour les fichiers générés.
 */
function up_sl_get_output_directory(): string {
    $settings = up_sl_get_settings();

    $base_location = isset($settings['base_location']) ? (string) $settings['base_location'] : 'theme';
    $relative = isset($settings['relative_subdir']) ? trim((string) $settings['relative_subdir'], "/ ") : 'shortcodes';

    // Si custom, utiliser directement output_directory
    if ($base_location === 'custom') {
        $directory = isset($settings['output_directory']) ? trim((string) $settings['output_directory']) : '';
        if ($directory === '') {
            $directory = trailingslashit(get_stylesheet_directory()) . 'shortcodes/';
        }
        return trailingslashit(wp_normalize_path($directory));
    }

    // Sinon, calculer à partir des bases connues
    switch ($base_location) {
        case 'mu':
            $base_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/mu-plugins' : (defined('ABSPATH') ? rtrim(ABSPATH, "\\/") . '/wp-content/mu-plugins' : ''));
            break;
        case 'plugin':
            $base_dir = UP_SL_PATH;
            break;
        case 'theme':
        default:
            $base_dir = get_stylesheet_directory();
            break;
    }

    $base_dir = wp_normalize_path($base_dir);
    $dir = trailingslashit($base_dir);
    if ($relative !== '') {
        $dir .= trailingslashit($relative);
    }

    return trailingslashit(wp_normalize_path($dir));
}

/**
 * Nettoie et valide un chemin fourni par l'utilisateur.
 */
function up_sl_sanitize_directory(string $directory): string {
    $directory = wp_normalize_path(trim($directory));

    if ($directory === '') {
        return ''; // Sera remplacé par défaut plus tard.
    }

    if (! str_ends_with($directory, '/')) {
        $directory .= '/';
    }

    return $directory;
}

/**
 * Sanitize le nom de fichier de base et l'assure qu'il commence par "shortcode-".
 * Supprime toute extension fournie par l'utilisateur.
 */
function up_sl_sanitize_filename_base(string $raw, string $fallback_slug = ''): string {
    $base = trim($raw);
    // Supprimer une éventuelle extension
    $base = preg_replace('/\.[a-zA-Z0-9]+$/', '', $base ?? '') ?? '';
    if ($base === '') {
        $base = $fallback_slug;
    }
    $base = str_replace('_', '-', $base);
    if (function_exists('remove_accents')) {
        $base = remove_accents($base);
    }
    $base = sanitize_title($base);
    if ($base === '') {
        $base = 'shortcode-item';
    }
    if (! str_starts_with($base, 'shortcode-')) {
        $base = 'shortcode-' . ltrim($base, '-');
    }
    return $base;
}

/**
 * Compile le SCSS en CSS si la librairie scssphp est disponible.
 * Retourne false si la compilation n'est pas possible.
 */
function up_sl_compile_scss(string $scss)
{
    $code = (string) $scss;
    if ($code === '') {
        return '';
    }

    // Préfixer avec un fichier de mixins si disponible
    $default_mixins_path = trailingslashit(UP_SL_PATH) . 'assets/scss/_mixins.scss';
    $mixins_path = apply_filters('up_sl_base_mixins_path', $default_mixins_path);
    if (is_string($mixins_path) && file_exists($mixins_path)) {
        $mixins = @file_get_contents($mixins_path);
        if (is_string($mixins) && $mixins !== '') {
            $code = $mixins . "\n" . $code;
        }
    }

    // Utiliser scssphp si disponible (ex: présent dans un autre plugin)
    if (class_exists('ScssPhp\\ScssPhp\\Compiler')) {
        try {
            $compiler = new \ScssPhp\ScssPhp\Compiler();
            $css = $compiler->compileString($code)->getCss();
            return $css;
        } catch (\Throwable $e) {
            return false;
        }
    }
    return false;
}

/**
 * Génère les fichiers pour un shortcode donné.
 */
function up_sl_generate_files(int $post_id): void {
    $output_directory = up_sl_get_output_directory();
    wp_mkdir_p($output_directory);

    // Déterminer le nom de base des fichiers
    $slug = (string) get_post_field('post_name', $post_id);
    $file_name_meta = (string) get_post_meta($post_id, '_up_sl_file_name', true);
    $base = up_sl_sanitize_filename_base($file_name_meta, $slug);

    // Récupérer contenus
    $php_code   = (string) get_post_meta($post_id, '_up_sl_php_code', true);
    $js_code    = (string) get_post_meta($post_id, '_up_sl_js_code', true);
    $scss_code  = (string) get_post_meta($post_id, '_up_sl_scss_code', true);
    $readme_code = (string) get_post_meta($post_id, '_up_sl_readme_code', true);
    $css_compiled = (string) get_post_meta($post_id, '_up_sl_compiled_css', true);

    // Flags de génération
    $should_generate_php  = (bool) get_post_meta($post_id, '_up_sl_generate_php_file', true);
    $should_generate_js   = (bool) get_post_meta($post_id, '_up_sl_generate_js_file', true);
    $should_generate_css  = (bool) get_post_meta($post_id, '_up_sl_generate_css_file', true);
    $should_generate_scss = (bool) get_post_meta($post_id, '_up_sl_generate_scss_file', true);
    $should_generate_readme = (bool) get_post_meta($post_id, '_up_sl_generate_readme_file', true);

    // Déterminer le mode: sous-dossier par shortcode, ou non
    $settings = up_sl_get_settings();
    $wrap = !empty($settings['wrap_in_subfolder']) && $settings['wrap_in_subfolder'] === '1';

    // Base dir selon option
    $base_dir = $wrap ? trailingslashit($output_directory) . $base . '/' : trailingslashit($output_directory);
    wp_mkdir_p($base_dir);

    // PHP + CSS au même niveau
    $php_path = $base_dir . $base . '.php';
    $css_path = $base_dir . $base . '.css';
    $readme_path = $base_dir . 'README.md';

    // Dossiers assets (JS/SCSS) dépendent de l'option wrap
    $assets_dir = $wrap
        ? $base_dir . 'assets/'
        : trailingslashit($output_directory) . 'assets/';
    $js_dir   = $assets_dir . 'js/';
    $scss_dir = $assets_dir . 'scss/';
    wp_mkdir_p($js_dir);
    wp_mkdir_p($scss_dir);

    // Générer le fichier PHP uniquement si demandé
    if ($should_generate_php && $php_code !== '') {
        $php_out = $php_code;
        // Préfixer par <?php si le début ne contient pas une balise d'ouverture
        $trimmed = ltrim($php_out);
        if (! preg_match('/^<\?(?:php)?/i', $trimmed)) {
            $php_out = "<?php\n" . $php_out;
        }
        file_put_contents($php_path, $php_out);
    }

    // JS
    if ($should_generate_js && $js_code !== '') {
        file_put_contents($js_dir . $base . '.js', $js_code);
    }

    // SCSS
    if ($should_generate_scss && $scss_code !== '') {
        file_put_contents($scss_dir . $base . '.scss', $scss_code);
    }

    // CSS (compilé) — au même niveau que le PHP
    if ($should_generate_css && $css_compiled !== '') {
        file_put_contents($css_path, $css_compiled);
    }

    // README.md
    if ($should_generate_readme && $readme_code !== '') {
        file_put_contents($readme_path, $readme_code);
    }
}

