<?php
// Petites fonctions pures (testables sans WordPress).

// Version proposée par une entrée de mise à jour : objet (WordPress) ou
// tableau (certains systèmes de mise à jour d'extensions premium).
function webpilot_nouvelle_version($entree) {
    if (is_object($entree) && isset($entree->new_version)) {
        return (string) $entree->new_version;
    }
    if (is_array($entree) && isset($entree['new_version'])) {
        return (string) $entree['new_version'];
    }
    return null;
}
