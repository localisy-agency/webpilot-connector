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

// Version WordPress : lit les options du site.
function webpilot_sauvegarde() {
    $actives = (array) get_option('active_plugins', array());
    $options = array(
        'updraft_last_backup' => get_option('updraft_last_backup'),
        'backwpup_jobs' => get_option('backwpup_jobs'),
    );
    return webpilot_sauvegarde_depuis_options($options, $actives);
}
