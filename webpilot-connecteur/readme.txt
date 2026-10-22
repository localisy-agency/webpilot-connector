=== WebPilot Connector ===
Contributors: Localisy
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.2.0

Permet à webpilot de lire l'état technique du site : versions de WordPress et PHP,
extensions et thèmes avec leurs mises à jour, date de la dernière sauvegarde. Lecture seule.

== Installation ==
1. Extensions → Ajouter → Téléverser l'extension : choisir webpilot-connecteur.zip.
2. Activer.
3. Réglages → WebPilot Connector : coller le jeton généré dans webpilot (fiche du site, onglet Connecteur).

== Mises à jour ==
L'extension se met à jour automatiquement depuis les versions publiées par Localisy
(github.com/localisy-agency/webpilot-connector). Chaque version est signée : un paquet
dont la signature ne correspond pas est refusé.

== Changelog ==
= 1.2.0 =
* UpdraftPlus : nombre de sauvegardes, contenu, planification, conservation et stockage distant.

= 1.1.0 =
* Mises à jour automatiques et signées depuis GitHub.

= 1.0.2 =
* Première version : état technique du site en lecture seule.
