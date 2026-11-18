<?php
// Journaux lus pour webpilot (lecture seule) : erreurs PHP des 7 derniers jours
// et erreurs 404 enregistrées par Redirection ou Rank Math. Rien n'est modifié :
// ni wp-config.php, ni le journal, ni les tables des extensions.

const WEBPILOT_JOURNAL_OCTETS = 524288; // fin du journal lue : 512 Ko
const WEBPILOT_JOURNAL_JOURS = 7;

// Réglages de débogage de WordPress. WP_DEBUG_DISPLAY absent vaut « affiché ».
function webpilot_reglages_debug($debug, $debug_log, $debug_display) {
    $debug = (bool) $debug;
    return array(
        'debug' => $debug,
        'journalWordPress' => $debug && !empty($debug_log),
        'affichage' => $debug && $debug_display !== false,
    );
}

// Compte les erreurs récentes d'un journal PHP. Les messages fatals gardés
// (3 au plus, distincts, les plus récents d'abord) sont tronqués à 200
// caractères et débarrassés du chemin du site.
function webpilot_analyser_journal_php($texte, $maintenant, $racine) {
    $depuis = $maintenant - WEBPILOT_JOURNAL_JOURS * 86400;
    $r = array('fatales' => 0, 'avertissements' => 0, 'notices' => 0, 'dernieres' => array());
    $fatales = array();
    foreach (preg_split('/\r?\n/', (string) $texte) as $ligne) {
        if (!preg_match('/^\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})(?: ([^\]]+))?\]\s*(PHP (Fatal error|Parse error|Warning|Notice|Deprecated):.*)$/', $ligne, $m)) {
            continue;
        }
        $quand = strtotime($m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? $m[2] : 'UTC'));
        if ($quand === false || $quand < $depuis) {
            continue;
        }
        if ($m[4] === 'Fatal error' || $m[4] === 'Parse error') {
            $r['fatales']++;
            $fatales[] = substr(str_replace($racine, '', trim($m[3])), 0, 200);
        } elseif ($m[4] === 'Warning') {
            $r['avertissements']++;
        } else {
            $r['notices']++;
        }
    }
    $r['dernieres'] = array_slice(array_values(array_unique(array_reverse($fatales))), 0, 3);
    return $r;
}

function webpilot_fin_fichier($chemin, $octets) {
    $taille = @filesize($chemin);
    $f = @fopen($chemin, 'rb');
    if ($f === false || $taille === false) {
        return null;
    }
    if ($taille > $octets) {
        fseek($f, -$octets, SEEK_END);
        fgets($f); // ligne coupée
    }
    $texte = stream_get_contents($f);
    fclose($f);
    return $texte === false ? null : $texte;
}

function webpilot_journal_php() {
    $reglages = webpilot_reglages_debug(
        defined('WP_DEBUG') && WP_DEBUG,
        defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
        defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : null
    );
    $chemin = ini_get('log_errors') ? (string) ini_get('error_log') : '';
    $r = $reglages + array('journal' => null, 'lisible' => false, 'taille' => null, 'modifieLe' => null);
    if ($chemin === '' || !@is_file($chemin)) {
        return $r;
    }
    $r['journal'] = $reglages['journalWordPress'] ? 'wordpress' : 'serveur';
    $texte = @is_readable($chemin) ? webpilot_fin_fichier($chemin, WEBPILOT_JOURNAL_OCTETS) : null;
    if ($texte === null) {
        return $r;
    }
    $r['lisible'] = true;
    $r['taille'] = (int) @filesize($chemin);
    $modifie = @filemtime($chemin);
    $r['modifieLe'] = $modifie ? gmdate('c', $modifie) : null;
    return $r + webpilot_analyser_journal_php($texte, time(), trailingslashit(ABSPATH));
}

function webpilot_resume_404($source, $total, $lignes, $redirections) {
    $top = array();
    foreach ($lignes as $l) {
        $top[] = array('url' => substr((string) $l['url'], 0, 150), 'n' => (int) $l['n']);
    }
    return array('source' => $source, 'total7j' => (int) $total, 'top' => $top, 'redirections' => $redirections === null ? null : (int) $redirections);
}

function webpilot_table_existe($table) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
}

// 404 des 7 derniers jours, depuis l'extension qui les enregistre.
function webpilot_journal_404() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $depuis = gmdate('Y-m-d H:i:s', time() - WEBPILOT_JOURNAL_JOURS * 86400);

    $t = $wpdb->prefix . 'redirection_404';
    if (is_plugin_active('redirection/redirection.php') && webpilot_table_existe($t)) {
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE created >= %s", $depuis));
        $lignes = $wpdb->get_results($wpdb->prepare("SELECT url, COUNT(*) AS n FROM `$t` WHERE created >= %s GROUP BY url ORDER BY n DESC LIMIT 5", $depuis), ARRAY_A);
        $items = $wpdb->prefix . 'redirection_items';
        $redirections = webpilot_table_existe($items) ? $wpdb->get_var("SELECT COUNT(*) FROM `$items` WHERE status = 'enabled'") : null;
        return webpilot_resume_404('Redirection', $total, $lignes ?: array(), $redirections);
    }

    $t = $wpdb->prefix . 'rank_math_404_logs';
    if (is_plugin_active('seo-by-rank-math/rank-math.php') && webpilot_table_existe($t)) {
        $total = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(times_accessed), 0) FROM `$t` WHERE accessed >= %s", $depuis));
        $lignes = $wpdb->get_results($wpdb->prepare("SELECT uri AS url, times_accessed AS n FROM `$t` WHERE accessed >= %s ORDER BY times_accessed DESC LIMIT 5", $depuis), ARRAY_A);
        $items = $wpdb->prefix . 'rank_math_redirections';
        $redirections = webpilot_table_existe($items) ? $wpdb->get_var("SELECT COUNT(*) FROM `$items` WHERE status = 'active'") : null;
        return webpilot_resume_404('Rank Math', $total, $lignes ?: array(), $redirections);
    }
    return null;
}
