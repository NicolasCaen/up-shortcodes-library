<?php
/**
 * Page d'administration pour les réglages de sortie.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'up_sl_register_settings_page');

function up_sl_register_settings_page(): void {
    add_submenu_page(
        'edit.php?post_type=up-shortcodes',
        __('Réglages des shortcodes', 'up-shortcodes-library'),
        __('Réglages', 'up-shortcodes-library'),
        'manage_options',
        'up-sl-settings',
        'up_sl_render_settings_page'
    );
}

function up_sl_render_settings_page(): void {
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Vous n’avez pas les permissions nécessaires pour accéder à cette page.', 'up-shortcodes-library'));
    }

    if (isset($_POST['up_sl_settings_nonce']) && wp_verify_nonce($_POST['up_sl_settings_nonce'], 'up_sl_save_settings')) {
        $base_location = isset($_POST['up_sl_base_location']) ? sanitize_text_field(wp_unslash((string) $_POST['up_sl_base_location'])) : 'theme';
        $relative_subdir = isset($_POST['up_sl_relative_subdir']) ? sanitize_text_field(wp_unslash((string) $_POST['up_sl_relative_subdir'])) : 'shortcodes';
        $directory = isset($_POST['up_sl_output_directory']) ? up_sl_sanitize_directory((string) $_POST['up_sl_output_directory']) : '';
        $wrap = isset($_POST['up_sl_wrap_in_subfolder']) ? '1' : '0';

        $settings = up_sl_get_settings();
        $settings['base_location'] = in_array($base_location, ['theme','mu','plugin','custom'], true) ? $base_location : 'theme';
        $settings['relative_subdir'] = $relative_subdir !== '' ? trim($relative_subdir, "/ ") : 'shortcodes';
        // Only persist custom directory when custom is selected
        if ($settings['base_location'] === 'custom' && $directory !== '') {
            $settings['output_directory'] = $directory;
        }
        $settings['wrap_in_subfolder'] = $wrap;

        update_option(UP_SL_OPTION_KEY, $settings);

        echo '<div class="notice notice-success"><p>' . esc_html__('Réglages sauvegardés.', 'up-shortcodes-library') . '</p></div>';
    }

    $settings = up_sl_get_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Réglages des shortcodes', 'up-shortcodes-library'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('up_sl_save_settings', 'up_sl_settings_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Emplacement de base', 'up-shortcodes-library'); ?>
                    </th>
                    <td>
                        <?php $base = !empty($settings['base_location']) ? $settings['base_location'] : 'theme'; ?>
                        <fieldset>
                            <label><input type="radio" name="up_sl_base_location" value="theme" <?php checked($base, 'theme'); ?>> <?php esc_html_e('Thème actif', 'up-shortcodes-library'); ?> (<?php echo esc_html(get_stylesheet_directory()); ?>)</label><br>
                            <?php
                            $content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (defined('ABSPATH') ? rtrim(ABSPATH, "\\/") . '/wp-content' : '');
                            $mu_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : ($content_dir ? $content_dir . '/mu-plugins' : '');
                            ?>
                            <label><input type="radio" name="up_sl_base_location" value="mu" <?php checked($base, 'mu'); ?>> <?php esc_html_e('MU-plugins', 'up-shortcodes-library'); ?> (<?php echo esc_html($mu_dir); ?>)</label><br>
                            <label><input type="radio" name="up_sl_base_location" value="plugin" <?php checked($base, 'plugin'); ?>> <?php esc_html_e('Ce plugin', 'up-shortcodes-library'); ?> (<?php echo esc_html(UP_SL_PATH); ?>)</label><br>
                            <label><input type="radio" name="up_sl_base_location" value="custom" <?php checked($base, 'custom'); ?>> <?php esc_html_e('Chemin personnalisé', 'up-shortcodes-library'); ?></label>
                        </fieldset>
                        <p class="description"><?php esc_html_e('Sélectionnez la base. Vous n’aurez plus à saisir le chemin absolu, sauf si vous choisissez "Chemin personnalisé".', 'up-shortcodes-library'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="up_sl_relative_subdir"><?php esc_html_e('Sous-dossier relatif', 'up-shortcodes-library'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="up_sl_relative_subdir" name="up_sl_relative_subdir" value="<?php echo esc_attr(!empty($settings['relative_subdir']) ? $settings['relative_subdir'] : 'shortcodes'); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Nom du sous-dossier sous la base sélectionnée (ex: shortcodes).', 'up-shortcodes-library'); ?></p>
                    </td>
                </tr>
                <tr id="up-sl-custom-path-row">
                    <th scope="row">
                        <label for="up_sl_output_directory"><?php esc_html_e('Dossier de génération (chemin personnalisé)', 'up-shortcodes-library'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="up_sl_output_directory" name="up_sl_output_directory" value="<?php echo esc_attr($settings['output_directory'] ?? ''); ?>" class="regular-text" placeholder="/chemin/absolu/vers/shortcodes" >
                        <p class="description"><?php esc_html_e('Utilisé uniquement si "Chemin personnalisé" est sélectionné ci-dessus.', 'up-shortcodes-library'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="up_sl_wrap_in_subfolder"><?php esc_html_e('Sous-dossier par shortcode', 'up-shortcodes-library'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="up_sl_wrap_in_subfolder" name="up_sl_wrap_in_subfolder" value="1" <?php checked(!empty($settings['wrap_in_subfolder']) ? $settings['wrap_in_subfolder'] : '1', '1'); ?>>
                            <?php esc_html_e('Envelopper chaque shortcode dans son propre sous-dossier', 'up-shortcodes-library'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Quand cette option est cochée (par défaut), les fichiers d’un shortcode sont placés dans shortcodes/<nom>/. Le fichier PHP et le CSS sont au même niveau, et les JS/SCSS dans shortcodes/<nom>/assets/js et assets/scss.', 'up-shortcodes-library'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <script>
            (function(){
              function toggleCustom(){
                var customRow = document.getElementById('up-sl-custom-path-row');
                var input = document.getElementById('up_sl_output_directory');
                var radios = document.querySelectorAll('input[name="up_sl_base_location"]');
                var base = 'theme';
                for (var i=0;i<radios.length;i++){ if (radios[i].checked){ base = radios[i].value; break; } }
                var isCustom = (base === 'custom');
                if (customRow){ customRow.style.display = isCustom ? '' : 'none'; }
                if (input){ input.disabled = !isCustom; }
              }
              document.addEventListener('change', function(e){
                if (e.target && e.target.name === 'up_sl_base_location'){ toggleCustom(); }
              });
              document.addEventListener('DOMContentLoaded', toggleCustom);
              toggleCustom();
            })();
            </script>

            <?php submit_button(__('Enregistrer les modifications', 'up-shortcodes-library')); ?>
        </form>
    </div>
    <?php
}
