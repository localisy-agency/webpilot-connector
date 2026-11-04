<?php
// Connexion rapide : WebPilot (dans le navigateur, coffre ouvert) signe un lien
// avec une clé propre au site ; le connecteur vérifie la signature, l'horodatage
// et le nonce (usage unique), puis ouvre la session du compte choisi dans les
// réglages. Désactivée tant qu'aucun compte n'est choisi.

define('WEBPILOT_OPTION_CLE_CONNEXION', 'webpilot_connexion_cle');
define('WEBPILOT_OPTION_COMPTE_CONNEXION', 'webpilot_connexion_compte');
define('WEBPILOT_OPTION_JOURNAL_CONNEXION', 'webpilot_connexion_journal');
define('WEBPILOT_CONNEXION_FENETRE', 120);
define('WEBPILOT_CONNEXION_JOURNAL_MAX', 50);
define('WEBPILOT_CONNEXION_ECHECS_MAX', 5);

function webpilot_connexion_destinations() {
    return array('tableau' => '', 'mises-a-jour' => 'update-core.php');
}

// Chaîne collée dans les réglages : jeton (64 hex), clé (wpq_ + 64 hex) ou « jeton.clé ».
// Renvoie array('jeton' => ?, 'cle' => ?) ou null si invalide.
function webpilot_lire_configuration($valeur) {
    $valeur = trim((string) $valeur);
    if ($valeur === '') {
        return null;
    }
    $jeton = null;
    $cle = null;
    foreach (explode('.', $valeur) as $partie) {
        if ($jeton === null && preg_match('/^[0-9a-fA-F]{64}$/', $partie)) {
            $jeton = strtolower($partie);
        } elseif ($cle === null && preg_match('/^wpq_[0-9a-f]{64}$/', $partie)) {
            $cle = $partie;
        } else {
            return null;
        }
    }
    return array('jeton' => $jeton, 'cle' => $cle);
}

function webpilot_empreinte_cle($cle) {
    return substr(hash('sha256', (string) $cle), 0, 8);
}

// Origine (schéma://hôte[:port]) d'une adresse : celle que le site attend dans la signature.
function webpilot_origine($url) {
    $p = parse_url((string) $url);
    if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
        return '';
    }
    return strtolower($p['scheme']) . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . (int) $p['port'] : '');
}

// La signature couvre l'origine du site : un lien fabriqué pour une autre
// adresse (adresse de connexion falsifiée) est refusé ici.
function webpilot_connexion_signature_valide($cle, $origine, $h, $n, $d, $s, $maintenant) {
    foreach (array($cle, $origine, $h, $n, $d, $s) as $p) {
        if (!is_string($p) || $p === '') {
            return false;
        }
    }
    if (!ctype_digit($h) || abs($maintenant - (int) $h) > WEBPILOT_CONNEXION_FENETRE) {
        return false;
    }
    if (!preg_match('/^[0-9a-f]{32}$/', $n) || !array_key_exists($d, webpilot_connexion_destinations())) {
        return false;
    }
    $attendue = hash_hmac('sha256', "connexion\n" . $origine . "\n" . $h . "\n" . $n . "\n" . $d, $cle);
    return hash_equals($attendue, strtolower($s));
}

// Usage unique du nonce : `$inserer($nom, $valeur)` doit faire une insertion
// atomique (INSERT IGNORE) et renvoyer le nombre de lignes insérées.
function webpilot_reserver_nonce($n, $inserer) {
    return $inserer('webpilot_n_' . $n, (string) time()) === 1;
}

// --- Branchement dans WordPress (absent des tests hors WordPress) ---

if (!function_exists('add_filter')) {
    return;
}

function webpilot_connexion_active() {
    return (int) get_option(WEBPILOT_OPTION_COMPTE_CONNEXION, 0) > 0 && (string) get_option(WEBPILOT_OPTION_CLE_CONNEXION, '') !== '';
}

// Rapporté à WebPilot par la route d'état : jamais le nom du compte.
function webpilot_etat_connexion() {
    $cle = (string) get_option(WEBPILOT_OPTION_CLE_CONNEXION, '');
    return array(
        'active' => webpilot_connexion_active(),
        'empreinte' => $cle === '' ? null : webpilot_empreinte_cle($cle),
        'url' => rest_url('webpilot/v1/connexion'),
    );
}

function webpilot_enregistrer_cle_connexion($cle) {
    update_option(WEBPILOT_OPTION_CLE_CONNEXION, (string) $cle, false);
}

function webpilot_connexion_refus($ip = null) {
    if ($ip !== null) {
        $cle = 'webpilot_cx_' . md5($ip);
        set_transient($cle, (int) get_transient($cle) + 1, MINUTE_IN_SECONDS);
    }
    wp_die(
        'Ce lien de connexion n\'est pas ou plus valide. <a href="' . esc_url(wp_login_url()) . '">Se connecter normalement</a>.',
        'WebPilot',
        array('response' => 403)
    );
}

function webpilot_route_connexion(WP_REST_Request $requete) {
    nocache_headers();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if ((int) get_transient('webpilot_cx_' . md5($ip)) >= WEBPILOT_CONNEXION_ECHECS_MAX) {
        webpilot_connexion_refus();
    }
    $cle = (string) get_option(WEBPILOT_OPTION_CLE_CONNEXION, '');
    $compte = (int) get_option(WEBPILOT_OPTION_COMPTE_CONNEXION, 0);
    if ($cle === '' || $compte <= 0) {
        webpilot_connexion_refus($ip);
    }
    $p = function ($nom) use ($requete) {
        $v = $requete->get_param($nom);
        return is_string($v) ? $v : '';
    };
    $h = $p('h');
    $n = $p('n');
    $d = $p('d');
    $origine = webpilot_origine(rest_url('webpilot/v1/connexion'));
    if (!webpilot_connexion_signature_valide($cle, $origine, $h, $n, $d, $p('s'), time())) {
        webpilot_connexion_refus($ip);
    }
    // Usage unique : insertion directe et atomique (sans cache d'options), seule
    // la première requête porteuse de ce nonce l'obtient.
    global $wpdb;
    $inserer = function ($nom, $valeur) use ($wpdb) {
        $r = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $nom, $valeur));
        return $r === false ? false : (int) $r;
    };
    if (!webpilot_reserver_nonce($n, $inserer)) {
        webpilot_connexion_refus($ip);
    }
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
        $wpdb->esc_like('webpilot_n_') . '%',
        time() - 10 * MINUTE_IN_SECONDS
    ));

    $utilisateur = get_user_by('id', $compte);
    if (!$utilisateur || !user_can($utilisateur, 'manage_options')) {
        webpilot_connexion_refus($ip);
    }
    wp_set_current_user($utilisateur->ID);
    wp_set_auth_cookie($utilisateur->ID, false, is_ssl());

    $journal = get_option(WEBPILOT_OPTION_JOURNAL_CONNEXION, array());
    $journal = is_array($journal) ? $journal : array();
    array_unshift($journal, array('date' => time(), 'compte' => $utilisateur->user_login, 'ip' => $ip));
    update_option(WEBPILOT_OPTION_JOURNAL_CONNEXION, array_slice($journal, 0, WEBPILOT_CONNEXION_JOURNAL_MAX), false);

    $destinations = webpilot_connexion_destinations();
    wp_safe_redirect(admin_url($destinations[$d]));
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('webpilot/v1', '/connexion', array(
        'methods' => 'GET',
        'callback' => 'webpilot_route_connexion',
        'permission_callback' => '__return_true', // contrôles faits dans le rappel
    ));
});
