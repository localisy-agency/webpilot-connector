<?php
// État technique du site renvoyé à webpilot (lecture seule).

function webpilot_rafraichir_mises_a_jour_si_ancien() {
    $douze_heures = 12 * HOUR_IN_SECONDS;
    require_once ABSPATH . 'wp-admin/includes/update.php';
    $core = get_site_transient('update_core');
    if (!$core || empty($core->last_checked) || time() - $core->last_checked > $douze_heures) {
        wp_version_check();
    }
    $extensions = get_site_transient('update_plugins');
    if (!$extensions || empty($extensions->last_checked) || time() - $extensions->last_checked > $douze_heures) {
        wp_update_plugins();
    }
    $themes = get_site_transient('update_themes');
    if (!$themes || empty($themes->last_checked) || time() - $themes->last_checked > $douze_heures) {
        wp_update_themes();
    }
}

function webpilot_maj_wordpress() {
    $core = get_site_transient('update_core');
    if (!$core || empty($core->updates)) {
        return null;
    }
    foreach ($core->updates as $maj) {
        if (isset($maj->response) && $maj->response === 'upgrade') {
            return $maj->current;
        }
    }
    return null;
}

function webpilot_etat() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    webpilot_rafraichir_mises_a_jour_si_ancien();

    $maj_extensions = get_site_transient('update_plugins');
    $extensions = array();
    foreach (get_plugins() as $fichier => $infos) {
        $extensions[] = array(
            'fichier' => $fichier,
            'nom' => $infos['Name'],
            'version' => $infos['Version'],
            'active' => is_plugin_active($fichier),
            'miseAJour' => isset($maj_extensions->response[$fichier]) ? webpilot_nouvelle_version($maj_extensions->response[$fichier]) : null,
        );
    }

    $maj_themes = get_site_transient('update_themes');
    $actif = get_stylesheet();
    $themes = array();
    foreach (wp_get_themes() as $slug => $theme) {
        $themes[] = array(
            'slug' => $slug,
            'nom' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'actif' => $slug === $actif,
            'miseAJour' => isset($maj_themes->response[$slug]) ? webpilot_nouvelle_version($maj_themes->response[$slug]) : null,
        );
    }

    return array(
        'versionConnecteur' => WEBPILOT_CONNECTEUR_VERSION,
        'wordpress' => array('version' => get_bloginfo('version'), 'miseAJour' => webpilot_maj_wordpress()),
        'php' => array('version' => PHP_VERSION),
        'extensions' => $extensions,
        'themes' => $themes,
        'sauvegarde' => webpilot_sauvegarde(),
        'verifieLe' => gmdate('c'),
    );
}
