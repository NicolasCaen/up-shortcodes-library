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
        $directory = isset($_POST['up_sl_output_directory']) ? up_sl_sanitize_directory((string) $_POST['up_sl_output_directory']) : '';

        $settings = up_sl_get_settings();
        $settings['output_directory'] = $directory !== '' ? $directory : $settings['output_directory'];

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
                        <label for="up_sl_output_directory"><?php esc_html_e('Dossier de génération', 'up-shortcodes-library'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="up_sl_output_directory" name="up_sl_output_directory" value="<?php echo esc_attr($settings['output_directory']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Chemin absolu vers le dossier où seront enregistrés les fichiers des shortcodes.', 'up-shortcodes-library'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Enregistrer les modifications', 'up-shortcodes-library')); ?>
        </form>
    </div>
    <?php
}
