# WebPilot Connector

Extension WordPress développée par [Localisy](https://localisy.be) : elle permet à webpilot de lire l'état technique d'un site (versions de WordPress et PHP, extensions, thèmes, mises à jour, dernière sauvegarde). Lecture seule.

## Sécurité

- webpilot interroge le site avec une requête signée (HMAC-SHA256) par un jeton propre à chaque site ; sans ce jeton, le site répond 401.
- Connexion rapide (facultative, désactivée par défaut) : un lien de connexion à l'admin, signé (HMAC-SHA256) avec une clé que seul le coffre de WebPilot connaît, valable 2 minutes et utilisable une seule fois, pour le compte administrateur choisi dans les réglages. Les échecs sont limités par adresse IP et chaque connexion est journalisée.
- Les versions publiées ici sont signées (Ed25519) par Localisy. L'extension vérifie la signature et l'empreinte du paquet avant toute mise à jour et refuse un paquet non signé, même s'il provient de ce dépôt.

## Installation

Télécharger `webpilot-connecteur.zip` depuis la [dernière version](https://github.com/localisy-agency/webpilot-connector/releases/latest), puis dans WordPress : Extensions → Ajouter → Téléverser. Les versions suivantes s'installent automatiquement.
