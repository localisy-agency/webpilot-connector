<?php
// Vérification des requêtes signées par webpilot (HMAC-SHA256 horodaté).
// Le jeton ne circule jamais : webpilot signe l'horodatage, la méthode et la route.

if (!defined('WEBPILOT_FENETRE_SECONDES')) {
    define('WEBPILOT_FENETRE_SECONDES', 300);
}
if (!defined('WEBPILOT_ROUTE_ETAT')) {
    define('WEBPILOT_ROUTE_ETAT', '/webpilot/v1/etat');
}

function webpilot_signature_valide($jeton, $horodatage, $signature, $maintenant) {
    if (!is_string($jeton) || $jeton === '' || !is_string($signature) || $signature === '') {
        return false;
    }
    if (!is_string($horodatage) || !ctype_digit($horodatage)) {
        return false;
    }
    if (abs($maintenant - (int) $horodatage) > WEBPILOT_FENETRE_SECONDES) {
        return false;
    }
    $attendue = hash_hmac('sha256', $horodatage . "\nGET\n" . WEBPILOT_ROUTE_ETAT, $jeton);
    return hash_equals($attendue, strtolower($signature));
}
