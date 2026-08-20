# Journal des versions

## 1.1

- L'adresse de l'API Supervio n'est plus configurable. Le service est hébergé par
  Supervio, il n'existe pas d'instance auto-hébergée vers laquelle pointer : ce
  champ n'offrait qu'un moyen de faire partir la clé API vers un serveur
  arbitraire.
- L'adresse de la page publique n'est plus configurable et reste `/statut`, avec
  la redirection 301 depuis `/status`. Ce segment construit une route chargée à
  chaque requête ; une valeur libre enregistrée par erreur empêchait le site
  entier de répondre.
- Les réglages `supervio_api_url` et `supervio_page_slug` ne sont plus lus. Ils
  peuvent être supprimés de la table `settings`.

## 1.0

Première version publiable.

- Page de statut publique alimentée par l'API Supervio, rendue côté serveur
- Deux écrans de réglages : connexion à l'API, puis apparence de la page
- Adresse de la page configurable, avec redirection 301 depuis `/status`
- Trois templates : Aurore, Nocturne, Signal
- Catégories de services, sélection des services affichés, emoji ou icône par service
- Logo par téléversement ou par lien
- Pied de page au choix et CSS personnalisé sur les abonnements payants
- Clé API chiffrée au repos, jamais transmise au navigateur
- Cache par étage, durée réglable
