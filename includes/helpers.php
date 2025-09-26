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
        'output_directory' => trailingslashit(get_stylesheet_directory()) . 'shortcodes/',
    ];

    return wp_parse_args((array) get_option(UP_SL_OPTION_KEY, []), $defaults);
}

/**
 * Retourne le chemin de sortie choisi pour les fichiers générés.
 */
function up_sl_get_output_directory(): string {
    $settings = up_sl_get_settings();
    $directory = isset($settings['output_directory']) ? trim((string) $settings['output_directory']) : '';

    if ($directory === '') {
        $directory = trailingslashit(get_stylesheet_directory()) . 'shortcodes/';
    }

    return trailingslashit(wp_normalize_path($directory));
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
    $css_compiled = (string) get_post_meta($post_id, '_up_sl_compiled_css', true);

    // Flags de génération
    $should_generate_php  = (bool) get_post_meta($post_id, '_up_sl_generate_php_file', true);
    $should_generate_js   = (bool) get_post_meta($post_id, '_up_sl_generate_js_file', true);
    $should_generate_css  = (bool) get_post_meta($post_id, '_up_sl_generate_css_file', true);
    $should_generate_scss = (bool) get_post_meta($post_id, '_up_sl_generate_scss_file', true);

    // Générer le fichier PHP uniquement si demandé
    if ($should_generate_php && $php_code !== '') {
        $php_out = $php_code;
        // Préfixer par <?php si le début ne contient pas une balise d'ouverture
        $trimmed = ltrim($php_out);
        if (! preg_match('/^<\?(?:php)?/i', $trimmed)) {
            $php_out = "<?php\n" . $php_out;
        }
        file_put_contents($output_directory . $base . '.php', $php_out);
    }

    // Préparer les dossiers d'assets
    $assets_dir  = trailingslashit($output_directory) . 'assets/';
    $css_dir     = $assets_dir . 'css/';
    $js_dir      = $assets_dir . 'js/';
    $scss_dir    = $assets_dir . 'scss/';
    wp_mkdir_p($css_dir);
    wp_mkdir_p($js_dir);
    wp_mkdir_p($scss_dir);

    // JS
    if ($should_generate_js && $js_code !== '') {
        file_put_contents($js_dir . $base . '.js', $js_code);
    }

    // SCSS
    if ($should_generate_scss && $scss_code !== '') {
        file_put_contents($scss_dir . $base . '.scss', $scss_code);
    }

    // CSS (compilé)
    if ($should_generate_css && $css_compiled !== '') {
        file_put_contents($css_dir . $base . '.css', $css_compiled);
    }
}

