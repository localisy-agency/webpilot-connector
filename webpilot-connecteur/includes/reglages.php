<?php
// Réglages → WebPilot Connector : jeton de connexion et, en option, clé de
// connexion rapide fournis par WebPilot ; compte utilisé pour la connexion rapide.

add_action('admin_menu', function () {
    add_options_page('WebPilot Connector', 'WebPilot Connector', 'manage_options', 'webpilot-connecteur', 'webpilot_page_reglages');
});

add_action('admin_init', function () {
    // Le champ accepte le jeton, la clé de connexion rapide, ou les deux (« jeton.clé ») ;
    // ce qui n'est pas fourni est conservé.
    register_setting('webpilot_connecteur', WEBPILOT_OPTION_JETON, array(
        'type' => 'string',
        'sanitize_callback' => function ($valeur) {
            $actuel = get_option(WEBPILOT_OPTION_JETON, '');
            if (trim((string) $valeur) === '') {
                return $actuel;
            }
            $config = webpilot_lire_configuration($valeur);
            if ($config === null) {
                add_settings_error(WEBPILOT_OPTION_JETON, 'jeton_invalide', 'Valeur invalide : copiez-la à nouveau depuis WebPilot.');
                return $actuel;
            }
            if ($config['cle'] !== null) {
                webpilot_enregistrer_cle_connexion($config['cle']);
                add_settings_error(WEBPILOT_OPTION_JETON, 'cle_enregistree', 'Clé de connexion rapide enregistrée. Choisissez le compte à utiliser.', 'updated');
            }
            return $config['jeton'] !== null ? $config['jeton'] : $actuel;
        },
    ));
    // Compte utilisé par la connexion rapide : un administrateur, ou 0 (désactivée).
    register_setting('webpilot_connecteur', WEBPILOT_OPTION_COMPTE_CONNEXION, array(
        'type' => 'integer',
        'sanitize_callback' => function ($valeur) {
            $id = (int) $valeur;
            if ($id <= 0) {
                return 0;
            }
            $utilisateur = get_user_by('id', $id);
            return $utilisateur && user_can($utilisateur, 'manage_options') ? $id : 0;
        },
    ));
});

// Jamais chargée automatiquement à chaque page.
add_action('add_option_' . WEBPILOT_OPTION_JETON, function () {
    global $wpdb;
    $wpdb->update($wpdb->options, array('autoload' => 'no'), array('option_name' => WEBPILOT_OPTION_JETON));
});

// Désactiver la connexion rapide : compte retiré et clé effacée.
add_action('admin_post_webpilot_desactiver_connexion', function () {
    if (!current_user_can('manage_options')) {
        wp_die('', '', array('response' => 403));
    }
    check_admin_referer('webpilot_desactiver_connexion');
    update_option(WEBPILOT_OPTION_COMPTE_CONNEXION, 0);
    delete_option(WEBPILOT_OPTION_CLE_CONNEXION);
    wp_safe_redirect(admin_url('options-general.php?page=webpilot-connecteur'));
    exit;
});

function webpilot_page_reglages() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $configure = get_option(WEBPILOT_OPTION_JETON, '') !== '';
    $derniere = (int) get_option(WEBPILOT_OPTION_DERNIERE, 0);
    $cle = (string) get_option(WEBPILOT_OPTION_CLE_CONNEXION, '') !== '';
    $compte = (int) get_option(WEBPILOT_OPTION_COMPTE_CONNEXION, 0);
    $admins = get_users(array('role' => 'administrator', 'orderby' => 'login'));
    $journal = get_option(WEBPILOT_OPTION_JOURNAL_CONNEXION, array());
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
                connecté — dernière requête de WebPilot le <?php echo esc_html(wp_date('j F Y à H:i', $derniere)); ?>.
            <?php else : ?>
                jeton configuré, WebPilot ne s'est pas encore connecté.
            <?php endif; ?>
        </p>
        <form method="post" action="options.php">
            <?php settings_fields('webpilot_connecteur'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="webpilot-jeton">Jeton ou clé</label></th>
                    <td>
                        <input type="password" id="webpilot-jeton" name="<?php echo esc_attr(WEBPILOT_OPTION_JETON); ?>" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $configure ? '•••••••• (laisser vide pour garder l\'actuel)' : 'Coller la valeur générée dans WebPilot'; ?>" />
                        <p class="description">Généré dans la fiche du site sur WebPilot (onglet Connecteur) : jeton, clé de connexion rapide, ou les deux.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="webpilot-compte">Connexion rapide</label></th>
                    <td>
                        <select id="webpilot-compte" name="<?php echo esc_attr(WEBPILOT_OPTION_COMPTE_CONNEXION); ?>" <?php disabled(!$cle); ?>>
                            <option value="0">Aucun — connexion rapide désactivée</option>
                            <?php foreach ($admins as $admin) : ?>
                                <option value="<?php echo (int) $admin->ID; ?>" <?php selected($compte, (int) $admin->ID); ?>><?php echo esc_html($admin->user_login); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo $cle ? 'Compte ouvert par le bouton « Se connecter » de WebPilot.' : 'Collez d\'abord une clé de connexion rapide générée dans WebPilot.'; ?>
                            La connexion rapide ouvre une session sans passer par la double authentification de WordPress.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Enregistrer'); ?>
        </form>
        <?php if ($cle) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="webpilot_desactiver_connexion" />
                <?php wp_nonce_field('webpilot_desactiver_connexion'); ?>
                <?php submit_button('Désactiver la connexion rapide et effacer la clé', 'secondary', 'submit', false); ?>
            </form>
        <?php endif; ?>
        <?php if (is_array($journal) && $journal) : ?>
            <h2>Dernières connexions rapides</h2>
            <table class="widefat striped" style="max-width:640px">
                <thead><tr><th>Date</th><th>Compte</th><th>Adresse IP</th></tr></thead>
                <tbody>
                    <?php foreach ($journal as $ligne) : ?>
                        <tr>
                            <td><?php echo esc_html(wp_date('j F Y à H:i', (int) $ligne['date'])); ?></td>
                            <td><?php echo esc_html($ligne['compte']); ?></td>
                            <td><?php echo esc_html($ligne['ip']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
