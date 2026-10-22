<?php
// Dernière sauvegarde faite par une extension de sauvegarde, à partir de ses
// options. Fonction pure (testable sans WordPress) ; voir webpilot_sauvegarde().

function webpilot_date_iso($horodatage) {
    return is_numeric($horodatage) && (int) $horodatage > 0 ? gmdate('c', (int) $horodatage) : null;
}

// $options : [nom d'option => valeur], $actives : fichiers des extensions actives.
function webpilot_sauvegarde_depuis_options(array $options, array $actives) {
    if (in_array('updraftplus/updraftplus.php', $actives, true)) {
        $derniere = isset($options['updraft_last_backup']) && is_array($options['updraft_last_backup'])
            ? $options['updraft_last_backup'] : array();
        $date = isset($derniere['backup_time']) ? webpilot_date_iso($derniere['backup_time']) : null;
        return array(
            'extension' => 'UpdraftPlus',
            'derniere' => $date,
            'reussie' => $date === null ? null : !empty($derniere['success']),
            'details' => webpilot_updraft_details($options),
        );
    }
    if (in_array('backwpup/backwpup.php', $actives, true)) {
        $max = 0;
        $travaux = isset($options['backwpup_jobs']) && is_array($options['backwpup_jobs']) ? $options['backwpup_jobs'] : array();
        foreach ($travaux as $travail) {
            if (is_array($travail) && isset($travail['lastrun'])) {
                $max = max($max, (int) $travail['lastrun']);
            }
        }
        return array('extension' => 'BackWPup', 'derniere' => webpilot_date_iso($max), 'reussie' => null);
    }
    $autres = array(
        'wpvivid-backuprestore/wpvivid-backuprestore.php' => 'WPvivid',
        'all-in-one-wp-migration/all-in-one-wp-migration.php' => 'All-in-One WP Migration',
        'duplicator/duplicator.php' => 'Duplicator',
    );
    foreach ($autres as $fichier => $nom) {
        if (in_array($fichier, $actives, true)) {
            return array('extension' => $nom, 'derniere' => null, 'reussie' => null);
        }
    }
    return null;
}

// Contenu d'une sauvegarde UpdraftPlus : clés de l'historique → nos noms.
function webpilot_updraft_parties() {
    return array('db' => 'base', 'plugins' => 'extensions', 'themes' => 'themes', 'uploads' => 'medias', 'others' => 'autres', 'wpcore' => 'wordpress', 'more' => 'supplementaires');
}

// Détail UpdraftPlus : nombre de sauvegardes gardées, contenu sauvegardé,
// planification, conservation et destinations distantes (cloud).
// Aucun identifiant de stockage n'est lu : seulement le nom des services.
function webpilot_updraft_details(array $options) {
    $historique = isset($options['updraft_backup_history']) && is_array($options['updraft_backup_history'])
        ? $options['updraft_backup_history'] : array();

    // Fichiers inclus par défaut (extensions, thèmes, médias, autres) quand l'option n'existe pas.
    $contenu = array('base');
    $defauts = array('plugins' => true, 'themes' => true, 'uploads' => true, 'others' => true, 'wpcore' => false, 'more' => false);
    $parties = webpilot_updraft_parties();
    foreach ($defauts as $cle => $defaut) {
        $valeur = isset($options['updraft_include_' . $cle]) ? $options['updraft_include_' . $cle] : null;
        $inclus = ($valeur === null || $valeur === false || $valeur === '') ? $defaut : !empty($valeur);
        if ($inclus) {
            $contenu[] = $parties[$cle];
        }
    }

    $services = isset($options['updraft_service']) ? $options['updraft_service'] : array();
    $destinations = array();
    foreach ((array) $services as $service) {
        if (is_string($service) && $service !== 'none' && preg_match('/^[a-z0-9_-]{1,30}$/', $service)) {
            $destinations[] = $service;
        }
    }

    $derniere = null;
    if ($historique) {
        $cles = array_filter(array_keys($historique), 'is_numeric');
        $entree = $cles ? $historique[max($cles)] : null;
        if (is_array($entree)) {
            $parties_faites = array();
            $taille = 0;
            foreach ($parties as $cle => $nom) {
                if (!empty($entree[$cle])) {
                    $parties_faites[] = $nom;
                }
                if (isset($entree[$cle . '-size']) && is_numeric($entree[$cle . '-size'])) {
                    $taille += (int) $entree[$cle . '-size'];
                }
            }
            $service = isset($entree['service']) ? (array) $entree['service'] : array();
            $distant = array_filter($service, function ($s) { return is_string($s) && $s !== '' && $s !== 'none'; });
            $derniere = array('contenu' => $parties_faites, 'taille' => $taille, 'distante' => (bool) $distant);
        }
    }

    $texte = function ($cle, $defaut) use ($options) {
        return isset($options[$cle]) && is_string($options[$cle]) && preg_match('/^[a-z0-9_]{1,30}$/', $options[$cle]) ? $options[$cle] : $defaut;
    };
    $entier = function ($cle) use ($options) {
        return isset($options[$cle]) && is_numeric($options[$cle]) ? (int) $options[$cle] : null;
    };

    return array(
        'nombre' => count($historique),
        'contenu' => $contenu,
        'planification' => array('fichiers' => $texte('updraft_interval', 'manual'), 'base' => $texte('updraft_interval_database', 'manual')),
        'conservation' => array('fichiers' => $entier('updraft_retain'), 'base' => $entier('updraft_retain_db')),
        'destinations' => array_values($destinations),
        'derniere' => $derniere,
    );
}

// Version WordPress : lit les options du site.
function webpilot_sauvegarde() {
    $actives = (array) get_option('active_plugins', array());
    $options = array(
        'updraft_last_backup' => get_option('updraft_last_backup'),
        'backwpup_jobs' => get_option('backwpup_jobs'),
    );
    foreach (array('updraft_backup_history', 'updraft_service', 'updraft_interval', 'updraft_interval_database', 'updraft_retain', 'updraft_retain_db') as $nom) {
        $options[$nom] = get_option($nom, null);
    }
    foreach (array_keys(webpilot_updraft_parties()) as $cle) {
        if ($cle !== 'db') {
            $options['updraft_include_' . $cle] = get_option('updraft_include_' . $cle, null);
        }
    }
    return webpilot_sauvegarde_depuis_options($options, $actives);
}
