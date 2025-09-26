<?php
/**
 * Déclaration du CPT et des metaboxes pour les shortcodes.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'up_sl_register_shortcodes_cpt');

function up_sl_register_shortcodes_cpt(): void {
    $labels = [
        'name'               => __('Shortcodes', 'up-shortcodes-library'),
        'singular_name'      => __('Shortcode', 'up-shortcodes-library'),
        'add_new'            => __('Ajouter', 'up-shortcodes-library'),
        'add_new_item'       => __('Ajouter un shortcode', 'up-shortcodes-library'),
        'edit_item'          => __('Modifier le shortcode', 'up-shortcodes-library'),
        'new_item'           => __('Nouveau shortcode', 'up-shortcodes-library'),
        'view_item'          => __('Voir le shortcode', 'up-shortcodes-library'),
        'search_items'       => __('Rechercher un shortcode', 'up-shortcodes-library'),
        'not_found'          => __('Aucun shortcode trouvé', 'up-shortcodes-library'),
        'not_found_in_trash' => __('Aucun shortcode dans la corbeille', 'up-shortcodes-library'),
        'menu_name'          => __('Shortcodes', 'up-shortcodes-library'),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-editor-code',
        'supports'           => ['title', 'editor', 'excerpt'],
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'rewrite'            => false,
        'query_var'          => false,
        'show_in_rest'       => true,
    ];

    register_post_type('up-shortcodes', $args);
}

add_action('add_meta_boxes', 'up_sl_register_shortcodes_meta_boxes');

function up_sl_register_shortcodes_meta_boxes(): void {
    add_meta_box(
        'up_sl_shortcode_code',
        __('Code du shortcode', 'up-shortcodes-library'),
        'up_sl_render_shortcode_meta_box',
        'up-shortcodes',
        'normal',
        'default'
    );

    add_meta_box(
        'up_sl_shortcode_settings',
        __('Nom de fichier et génération', 'up-shortcodes-library'),
        'up_sl_render_shortcode_settings_meta_box',
        'up-shortcodes',
        'side',
        'default'
    );
}

function up_sl_render_shortcode_meta_box(WP_Post $post): void {
    wp_nonce_field('up_sl_save_meta', 'up_sl_meta_nonce');

    $php_code  = (string) get_post_meta($post->ID, '_up_sl_php_code', true);
    $js_code   = (string) get_post_meta($post->ID, '_up_sl_js_code', true);
    $scss_code = (string) get_post_meta($post->ID, '_up_sl_scss_code', true);
    $css_code  = (string) get_post_meta($post->ID, '_up_sl_compiled_css', true);
    ?>
    <p>
        <label for="up_sl_php_code"><strong><?php esc_html_e('Code PHP du shortcode', 'up-shortcodes-library'); ?></strong></label>
    </p>
    <textarea id="up_sl_php_code" name="up_sl_php_code" rows="12" style="width:100%;font-family:monospace;" spellcheck="false"><?php echo esc_textarea($php_code); ?></textarea>
    <p class="description"><?php esc_html_e('Définissez ici la fonction add_shortcode. Le contenu est enregistré tel quel.', 'up-shortcodes-library'); ?></p>

    <hr>

    <p>
        <label for="up_sl_js_code"><strong><?php esc_html_e('JavaScript (optionnel)', 'up-shortcodes-library'); ?></strong></label>
    </p>
    <textarea id="up_sl_js_code" name="up_sl_js_code" rows="8" style="width:100%;font-family:monospace;" spellcheck="false"><?php echo esc_textarea($js_code); ?></textarea>
    <p class="description"><?php esc_html_e('Code JS à charger lorsque le shortcode est utilisé.', 'up-shortcodes-library'); ?></p>

    <p>
        <label for="up_sl_scss_code"><strong><?php esc_html_e('SCSS/CSS (optionnel)', 'up-shortcodes-library'); ?></strong></label>
    </p>
    <textarea id="up_sl_scss_code" name="up_sl_scss_code" rows="8" style="width:100%;font-family:monospace;" spellcheck="false"><?php echo esc_textarea($scss_code); ?></textarea>
    <p class="description"><?php esc_html_e('Styles associés au shortcode. Le CSS compilé est généré lors de la sauvegarde.', 'up-shortcodes-library'); ?></p>

    <p>
        <label for="up_sl_css_code"><strong><?php esc_html_e('CSS compilé (lecture seule)', 'up-shortcodes-library'); ?></strong></label>
    </p>
    <textarea id="up_sl_css_code" rows="8" style="width:100%;font-family:monospace;" spellcheck="false" readonly><?php echo esc_textarea($css_code); ?></textarea>
    <p class="description"><?php esc_html_e('Aperçu du CSS compilé à la dernière sauvegarde.', 'up-shortcodes-library'); ?></p>
    <?php
}

function up_sl_render_shortcode_settings_meta_box(WP_Post $post): void {
    $generate_php = (bool) get_post_meta($post->ID, '_up_sl_generate_php_file', true);
    $generate_css = (bool) get_post_meta($post->ID, '_up_sl_generate_css_file', true);
    $generate_js  = (bool) get_post_meta($post->ID, '_up_sl_generate_js_file', true);
    $generate_scss = (bool) get_post_meta($post->ID, '_up_sl_generate_scss_file', true);
    $output_directory = up_sl_get_output_directory();
    $slug = $post->post_name ?: __('(slug non défini)', 'up-shortcodes-library');
    $file_name = (string) get_post_meta($post->ID, '_up_sl_file_name', true);
    ?>
    <p>
        <label for="up_sl_file_name"><strong><?php esc_html_e('Nom de fichier (sans extension)', 'up-shortcodes-library'); ?></strong></label>
        <input type="text" id="up_sl_file_name" name="up_sl_file_name" class="widefat" value="<?php echo esc_attr($file_name); ?>" placeholder="<?php echo esc_attr('shortcode-' . sanitize_title($slug)); ?>">
        <small class="description"><?php esc_html_e('Doit commencer par "shortcode-". Si ce n’est pas le cas, il sera préfixé automatiquement.', 'up-shortcodes-library'); ?></small>
    </p>
    <p>
        <label>
            <input type="checkbox" name="up_sl_generate_php_file" value="1" <?php checked($generate_php); ?>>
            <?php esc_html_e('Générer le fichier PHP du shortcode', 'up-shortcodes-library'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="up_sl_generate_css_file" value="1" <?php checked($generate_css); ?>>
            <?php esc_html_e('Générer le fichier CSS', 'up-shortcodes-library'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="up_sl_generate_js_file" value="1" <?php checked($generate_js); ?>>
            <?php esc_html_e('Générer le fichier JS', 'up-shortcodes-library'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="up_sl_generate_scss_file" value="1" <?php checked($generate_scss); ?>>
            <?php esc_html_e('Générer le fichier SCSS', 'up-shortcodes-library'); ?>
        </label>
    </p>
    <p class="description">
        <?php
        printf(
            esc_html__('Les fichiers seront créés dans %s. Le PHP sera au niveau racine, les assets dans %s.', 'up-shortcodes-library'),
            '<code>' . esc_html($output_directory) . '</code>',
            '<code>assets/css, assets/js, assets/scss</code>'
        );
        ?>
    </p>
    <?php
}

add_action('save_post_up-shortcodes', 'up_sl_save_shortcode_meta');

function up_sl_save_shortcode_meta(int $post_id): void {
    if (! isset($_POST['up_sl_meta_nonce']) || ! wp_verify_nonce($_POST['up_sl_meta_nonce'], 'up_sl_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    $php_code  = isset($_POST['up_sl_php_code']) ? wp_unslash((string) $_POST['up_sl_php_code']) : '';
    $js_code   = isset($_POST['up_sl_js_code']) ? wp_unslash((string) $_POST['up_sl_js_code']) : '';
    $scss_code = isset($_POST['up_sl_scss_code']) ? wp_unslash((string) $_POST['up_sl_scss_code']) : '';
    $file_name_raw = isset($_POST['up_sl_file_name']) ? (string) $_POST['up_sl_file_name'] : '';
    $generate_php  = isset($_POST['up_sl_generate_php_file']) ? '1' : '0';
    $generate_css  = isset($_POST['up_sl_generate_css_file']) ? '1' : '0';
    $generate_js   = isset($_POST['up_sl_generate_js_file']) ? '1' : '0';
    $generate_scss = isset($_POST['up_sl_generate_scss_file']) ? '1' : '0';

    // Déterminer et sanitiser le nom de fichier de base
    $slug = get_post_field('post_name', $post_id) ?: '';
    $file_name = up_sl_sanitize_filename_base($file_name_raw, $slug);

    update_post_meta($post_id, '_up_sl_php_code', $php_code);
    update_post_meta($post_id, '_up_sl_js_code', $js_code);
    update_post_meta($post_id, '_up_sl_scss_code', $scss_code);
    update_post_meta($post_id, '_up_sl_file_name', $file_name);
    update_post_meta($post_id, '_up_sl_generate_php_file', $generate_php);
    update_post_meta($post_id, '_up_sl_generate_css_file', $generate_css);
    update_post_meta($post_id, '_up_sl_generate_js_file', $generate_js);
    update_post_meta($post_id, '_up_sl_generate_scss_file', $generate_scss);

    // Compiler le SCSS en CSS (si possible) et sauvegarder en méta pour aperçu
    $compiled_css = up_sl_compile_scss($scss_code);
    if ($compiled_css === false) {
        $compiled_css = $scss_code; // Fallback: copier tel quel
    }
    update_post_meta($post_id, '_up_sl_compiled_css', $compiled_css);

    up_sl_generate_files($post_id);
}
