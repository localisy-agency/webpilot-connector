<?php
// Mises à jour de l'extension depuis les versions publiées sur GitHub
// (localisy-agency/webpilot-connector). Chaque version est signée (Ed25519)
// par Localisy : un paquet dont la signature ou l'empreinte ne correspondent
// pas est refusé, même si le dépôt GitHub était compromis.

if (!defined('WEBPILOT_MAJ_CLE_PUBLIQUE')) {
    define('WEBPILOT_MAJ_CLE_PUBLIQUE', '6Imu/vK8Qu8mcBBArnqKNoXZnVfSd03OQz7Fg2Ejmnc=');
}
define('WEBPILOT_MAJ_DEPOT', 'https://github.com/localisy-agency/webpilot-connector');
define('WEBPILOT_MAJ_MANIFESTE', WEBPILOT_MAJ_DEPOT . '/releases/latest/download/version.json');
define('WEBPILOT_MAJ_PREFIXE_PAQUET', WEBPILOT_MAJ_DEPOT . '/releases/download/');
define('WEBPILOT_MAJ_SLUG', 'webpilot-connecteur');

function webpilot_maj_message($version, $sha256) {
    return WEBPILOT_MAJ_SLUG . "\n" . $version . "\n" . $sha256;
}

function webpilot_maj_est_paquet_officiel($url) {
    return is_string($url) && strpos($url, WEBPILOT_MAJ_PREFIXE_PAQUET) === 0;
}

// Manifeste version.json : forme attendue, paquet de cette version sur le
// dépôt officiel, signature valide pour (version, empreinte).
function webpilot_maj_manifeste_valide($m, $cle_publique_b64) {
    if (!is_array($m)) {
        return false;
    }
    foreach (array('version', 'paquet', 'sha256', 'signature') as $champ) {
        if (!isset($m[$champ]) || !is_string($m[$champ])) {
            return false;
        }
    }
    if (!preg_match('/^\d+\.\d+\.\d+$/', $m['version']) || !preg_match('/^[0-9a-f]{64}$/', $m['sha256'])) {
        return false;
    }
    if ($m['paquet'] !== WEBPILOT_MAJ_PREFIXE_PAQUET . 'v' . $m['version'] . '/' . WEBPILOT_MAJ_SLUG . '.zip') {
        return false;
    }
    $signature = base64_decode($m['signature'], true);
    $cle = base64_decode($cle_publique_b64, true);
    if ($signature === false || strlen($signature) !== 64 || $cle === false || strlen($cle) !== 32) {
        return false;
    }
    try {
        return sodium_crypto_sign_verify_detached($signature, webpilot_maj_message($m['version'], $m['sha256']), $cle);
    } catch (Exception $e) {
        return false;
    }
}

function webpilot_maj_paquet_valide($fichier, $m) {
    if (!is_string($fichier) || !is_file($fichier)) {
        return false;
    }
    $empreinte = hash_file('sha256', $fichier);
    return is_string($empreinte) && hash_equals($m['sha256'], $empreinte);
}

// --- Branchement dans WordPress (absent des tests hors WordPress) ---

if (!function_exists('add_filter')) {
    return;
}

// Manifeste vérifié de la dernière version (null si indisponible ou invalide).
// Mis en cache 6 h, 1 h en cas d'échec pour ne pas solliciter GitHub.
function webpilot_maj_manifeste($frais = false) {
    $cache = get_site_transient('webpilot_maj_manifeste');
    if (!$frais && is_array($cache)) {
        return $cache['manifeste'];
    }
    $manifeste = null;
    $reponse = wp_remote_get(WEBPILOT_MAJ_MANIFESTE, array('timeout' => 5, 'limit_response_size' => 65536));
    if (!is_wp_error($reponse) && wp_remote_retrieve_response_code($reponse) === 200) {
        $m = json_decode(wp_remote_retrieve_body($reponse), true);
        if (webpilot_maj_manifeste_valide($m, WEBPILOT_MAJ_CLE_PUBLIQUE)) {
            $manifeste = $m;
        }
    }
    set_site_transient('webpilot_maj_manifeste', array('manifeste' => $manifeste), $manifeste ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
    return $manifeste;
}

// « Update URI » de l'en-tête : WordPress demande les mises à jour à ce filtre
// et jamais à wordpress.org (une extension homonyme ne peut pas s'y substituer).
add_filter('update_plugins_github.com', function ($maj, $donnees, $fichier) {
    if ($fichier !== plugin_basename(WEBPILOT_CONNECTEUR_FICHIER)) {
        return $maj;
    }
    $m = webpilot_maj_manifeste();
    if (!$m || !version_compare($m['version'], WEBPILOT_CONNECTEUR_VERSION, '>')) {
        return $maj;
    }
    return array(
        'slug' => WEBPILOT_MAJ_SLUG,
        'version' => $m['version'],
        'url' => WEBPILOT_MAJ_DEPOT,
        'package' => $m['paquet'],
        'requires_php' => isset($m['php']) ? (string) $m['php'] : '',
        'requires' => isset($m['wordpress']) ? (string) $m['wordpress'] : '',
    );
}, 10, 3);

// Téléchargement : le paquet n'est installé que si sa signature et son
// empreinte sont valides. Tout autre paquet pour cette extension est refusé.
add_filter('upgrader_pre_download', function ($reponse, $paquet, $upgrader, $extra = array()) {
    $pour_nous = isset($extra['plugin']) && $extra['plugin'] === plugin_basename(WEBPILOT_CONNECTEUR_FICHIER);
    if (!webpilot_maj_est_paquet_officiel($paquet)) {
        return $pour_nous ? new WP_Error('webpilot_maj_source', 'WebPilot Connector : source de mise à jour non reconnue.') : $reponse;
    }
    $m = webpilot_maj_manifeste(true);
    if (!$m || $m['paquet'] !== $paquet) {
        return new WP_Error('webpilot_maj_manifeste', 'WebPilot Connector : version publiée introuvable ou non signée.');
    }
    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $fichier = download_url($paquet, 60);
    if (is_wp_error($fichier)) {
        return $fichier;
    }
    if (!webpilot_maj_paquet_valide($fichier, $m)) {
        wp_delete_file($fichier);
        return new WP_Error('webpilot_maj_signature', 'WebPilot Connector : paquet refusé (empreinte invalide).');
    }
    return $fichier;
}, 10, 4);

// Mise à jour automatique de cette extension uniquement.
add_filter('auto_update_plugin', function ($maj, $element) {
    if (isset($element->plugin) && $element->plugin === plugin_basename(WEBPILOT_CONNECTEUR_FICHIER)) {
        return true;
    }
    return $maj;
}, 10, 2);
