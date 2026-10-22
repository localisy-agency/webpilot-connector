<?php
/**
 * Plugin Name: WebPilot Connector
 * Description: Permet à webpilot de lire l'état technique du site (versions de WordPress et PHP, extensions, thèmes, mises à jour, dernière sauvegarde). Lecture seule.
 * Version: 1.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Localisy
 * Update URI: https://github.com/localisy-agency/webpilot-connector
 * Text Domain: webpilot-connecteur
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WEBPILOT_CONNECTEUR_VERSION', '1.2.0');
define('WEBPILOT_CONNECTEUR_FICHIER', __FILE__);
define('WEBPILOT_OPTION_JETON', 'webpilot_connecteur_jeton');
define('WEBPILOT_OPTION_DERNIERE', 'webpilot_connecteur_derniere_requete');

require_once __DIR__ . '/includes/signature.php';
require_once __DIR__ . '/includes/sauvegarde.php';
require_once __DIR__ . '/includes/outils.php';
require_once __DIR__ . '/includes/etat.php';
require_once __DIR__ . '/includes/reglages.php';
require_once __DIR__ . '/includes/mise-a-jour.php';

add_action('rest_api_init', function () {
    register_rest_route('webpilot/v1', '/etat', array(
        'methods' => 'GET',
        'callback' => 'webpilot_route_etat',
        'permission_callback' => 'webpilot_requete_autorisee',
    ));
});

function webpilot_requete_autorisee(WP_REST_Request $requete) {
    $jeton = (string) get_option(WEBPILOT_OPTION_JETON, '');
    $ok = webpilot_signature_valide(
        $jeton,
        (string) $requete->get_header('x_webpilot_horodatage'),
        (string) $requete->get_header('x_webpilot_signature'),
        time()
    );
    // Réponse 401 sans détail en cas d'échec.
    return $ok ? true : new WP_Error('webpilot_refuse', '', array('status' => 401));
}

function webpilot_route_etat() {
    // Jamais en cache : un cache de page pourrait sinon servir cette réponse
    // signée à n'importe quel visiteur.
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    do_action('litespeed_control_set_nocache', 'WebPilot Connector');
    update_option(WEBPILOT_OPTION_DERNIERE, time(), false);
    $reponse = rest_ensure_response(webpilot_etat());
    foreach (wp_get_nocache_headers() as $nom => $valeur) {
        $reponse->header($nom, $valeur);
    }
    $reponse->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    return $reponse;
}
