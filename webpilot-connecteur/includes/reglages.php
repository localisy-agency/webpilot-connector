<?php
// Réglages → webpilot : jeton de connexion fourni par webpilot.

add_action('admin_menu', function () {
    add_options_page('WebPilot Connector', 'WebPilot Connector', 'manage_options', 'webpilot-connecteur', 'webpilot_page_reglages');
});

add_action('admin_init', function () {
    register_setting('webpilot_connecteur', WEBPILOT_OPTION_JETON, array(
        'type' => 'string',
        'sanitize_callback' => function ($valeur) {
            $valeur = strtolower(trim((string) $valeur));
            // Champ laissé vide : on garde le jeton existant.
            if ($valeur === '') {
                return get_option(WEBPILOT_OPTION_JETON, '');
            }
            if (!preg_match('/^[0-9a-f]{64}$/', $valeur)) {
                add_settings_error(WEBPILOT_OPTION_JETON, 'jeton_invalide', 'Jeton invalide : copiez-le à nouveau depuis webpilot.');
                return get_option(WEBPILOT_OPTION_JETON, '');
            }
            return $valeur;
        },
    ));
});

// Jamais chargée automatiquement à chaque page.
add_action('add_option_' . WEBPILOT_OPTION_JETON, function () {
    global $wpdb;
    $wpdb->update($wpdb->options, array('autoload' => 'no'), array('option_name' => WEBPILOT_OPTION_JETON));
});

function webpilot_page_reglages() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $configure = get_option(WEBPILOT_OPTION_JETON, '') !== '';
    $derniere = (int) get_option(WEBPILOT_OPTION_DERNIERE, 0);
    ?>
    <div class="wrap">
        <h1>WebPilot Connector</h1>
        <p class="description">Développé par Localisy.</p>
        <p>Ce connecteur permet à WebPilot de lire l'état technique du site (versions, extensions, mises à jour, dernière sauvegarde). Il ne modifie rien.</p>
        <p>
            <strong>État :</strong>
            <?php if (!$configure) : ?>
                aucun jeton configuré.
            <?php elseif ($derniere) : ?>
                connecté — dernière requête de webpilot le <?php echo esc_html(wp_date('j F Y à H:i', $derniere)); ?>.
            <?php else : ?>
                jeton configuré, webpilot ne s'est pas encore connecté.
            <?php endif; ?>
        </p>
        <form method="post" action="options.php">
            <?php settings_fields('webpilot_connecteur'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="webpilot-jeton">Jeton de connexion</label></th>
                    <td>
                        <input type="password" id="webpilot-jeton" name="<?php echo esc_attr(WEBPILOT_OPTION_JETON); ?>" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $configure ? '•••••••• (laisser vide pour garder le jeton actuel)' : 'Coller le jeton généré dans webpilot'; ?>" />
                        <p class="description">Généré dans la fiche du site sur webpilot (onglet Connecteur).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Enregistrer'); ?>
        </form>
    </div>
    <?php
}
