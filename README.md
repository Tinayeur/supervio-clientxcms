# Supervio pour ClientXCMS

Publie sur votre ClientXCMS une page de statut alimentée en direct par votre compte
[Supervio](https://supervio.fr) : services supervisés, disponibilité et incidents.

Le rendu est **entièrement côté serveur**. Votre clé API ne quitte jamais votre
hébergement : elle n'apparaît ni dans le HTML, ni dans un attribut de données, ni
dans un appel JavaScript, et aucune requête vers `supervio.fr` n'est émise depuis le
navigateur de vos visiteurs.

## Prérequis

- ClientXCMS avec le système d'extensions (`addons/`)
- PHP 8.0 ou supérieur
- Un compte [Supervio](https://supervio.fr) avec au moins une status page créée
- Une clé API générée depuis votre tableau de bord Supervio

L'addon **ne crée aucune table** : ses réglages vivent dans la table `settings` de
ClientXCMS. Il s'installe donc sans migration et se désinstalle sans résidu de schéma.

## Installation

```bash
cd addons/
git clone <URL_DU_DEPOT> supervio
cd supervio
composer install --no-dev
```

Puis, depuis la racine de ClientXCMS :

```bash
php artisan optimize:clear
```

Activez ensuite l'addon dans **Réglages › Extensions**.

> Les fichiers doivent appartenir à l'utilisateur qui exécute PHP-FPM. Après un
> `git clone` lancé en root, pensez à rétablir le propriétaire, sinon l'application
> ne pourra ni lire ni écrire dans le dossier.

## Configuration

L'addon ajoute une carte **Supervio** dans les Paramètres, avec deux écrans.

### Connexion

À remplir une fois.

1. **Clé API Supervio** — collez la clé générée sur supervio.fr, puis cliquez sur
   *Tester la connexion*. Le compte et l'abonnement détectés s'affichent.
2. **Status page à publier** — la liste est récupérée depuis votre compte. Tant
   qu'aucune page n'est sélectionnée, l'adresse publique reste en 404.
3. **Adresse de la page** — le segment d'URL, `statut` par défaut.

Une fois la clé enregistrée, aucun champ de saisie n'est affiché : cliquez sur
*Remplacer la clé* pour en changer. Cela évite qu'un gestionnaire de mots de passe du
navigateur ne l'écrase par inadvertance.

### Page de statut

À ajuster à volonté.

- **Identité** — titre affiché, logo par téléversement ou par lien
- **Apparence** — template, couleur d'accent, couleur de fond, mode sombre
- **Contenu** — profondeur d'historique, rafraîchissement automatique, durée du cache,
  affichage de la disponibilité et des incidents
- **Catégories** — regroupez vos services ; sans catégorie, tout s'affiche en une liste
- **Services** — lesquels publier, et pour chacun un emoji, une icône ou rien
- **Pied de page** et **CSS personnalisé**

Le titre et la couleur d'accent saisis ici priment sur ceux de la status page Supervio ;
laissés vides, ils reprennent automatiquement ceux définis sur Supervio.

La page est servie sur **`/<adresse>`**. `/status` y redirige en 301, conformément à la
convention ClientXCMS.

## Fonctionnalités selon l'abonnement

| Fonctionnalité | Free | Payant |
|---|---|---|
| Page de statut, disponibilité, incidents | ✅ | ✅ |
| Catégories, sélection des services, emojis et icônes | ✅ | ✅ |
| Titre, logo, couleur d'accent | ✅ | ✅ |
| Template Aurore | ✅ | ✅ |
| Templates Nocturne et Signal | — | ✅ |
| Mode sombre | — | ✅ |
| Couleur de fond personnalisée | — | ✅ |
| Pied de page libre, ou masqué | — | ✅ |
| CSS personnalisé | — | ✅ |
| Profondeur d'historique | 30 jours | 90 jours |

Les options indisponibles apparaissent grisées avec un badge **Pro** plutôt que
masquées : vous voyez ce que votre abonnement vous apporterait.

La profondeur d'historique et le nombre de services affichables ne sont pas décidés
par l'addon mais par l'API Supervio elle-même, qui refuse ce que votre abonnement ne
couvre pas. Forcer un réglage en base ne donne donc jamais accès à plus de données —
la page affiche une mention explicite à la place.

## Fonctionnement

L'API ne renvoie l'état complet d'aucune status page : l'addon assemble les données
depuis quatre endpoints, chacun avec son propre cache.

```
/status-pages/{id}          configuration : titre, services retenus, couleur   cache 10 min
/monitors                   état courant des services                          cache configurable
/monitors/{id}/uptime       disponibilité, un appel par service                cache configurable
/incidents                  incidents, filtrés sur les services de la page     cache configurable
```

L'authentification se fait par `Authorization: Bearer`. L'abonnement est relu toutes
les 5 minutes : une résiliation referme les options sans intervention, et une panne
de l'API referme les options plutôt que de les ouvrir.

## Désinstallation

Désactivez l'addon dans **Réglages › Extensions**, puis supprimez `addons/supervio/`.
L'addon ne crée aucune table : ses réglages vivent dans la table `settings` de
ClientXCMS. Pour les effacer complètement :

```sql
DELETE FROM settings WHERE name LIKE 'supervio_%';
```

## Dépannage

**« Clé refusée par Supervio »** — la clé est invalide ou a été révoquée. Regénérez-en
une depuis votre tableau de bord Supervio.

**« Supervio est injoignable »** — vérifiez que votre serveur peut joindre
`https://supervio.fr` en sortie. Certains hébergements filtrent les connexions
sortantes.

**La page publique renvoie 404** — l'addon n'est pas complètement configuré : il faut
une clé API valide **et** une status page sélectionnée. Un avertissement s'affiche sur
l'écran Connexion tant qu'aucune page n'est choisie.

**La liste des status pages est vide** — si la clé vient d'être saisie, enregistrez
d'abord : la liste est récupérée avec la clé déjà stockée. Si la clé est valide et la
liste reste vide, c'est qu'aucune status page n'existe sur le compte.

**Les options Pro restent grisées malgré un abonnement payant** — l'abonnement est mis
en cache 5 minutes. Cliquez sur *Tester la connexion* pour forcer une relecture, ou
attendez l'expiration.

## Configuration requise côté serveur

Les appels sortants vers `https://supervio.fr` doivent être autorisés. Le lien
symbolique `public/storage` doit exister pour que les logos téléversés soient servis
(`php artisan storage:link` s'il est absent).

## Ressources graphiques

Le dossier `assets/` contient les visuels de l'addon, à destination du catalogue
d'extensions :

| Fichier | Dimensions | Usage |
|---|---|---|
| `assets/logo.png` | 1024 × 1024, PNG transparent | icône de l'extension |
| `assets/banniere.png` | 1920 × 1080, PNG | bannière de la fiche catalogue |

Le champ `thumbnail` de `addon.json` pointe vers le logo hébergé sur
`supervio.fr`. Les fichiers de `assets/` restent fournis pour que le catalogue
ClientXCMS puisse les héberger lui-même s'il le préfère.

La bannière n'est pas encore déclarée : aucun champ de la convention ClientXCMS ne
la prévoit, elle accompagne le dépôt à destination de la fiche catalogue.

## Licence

Développé par Securost. Utilisation gratuite et sans limitation de durée avec
ClientXCMS, y compris dans un cadre commercial et d'hébergement. Modification et
redistribution soumises à autorisation écrite préalable.

Voir le fichier [LICENSE](LICENSE) pour les conditions complètes.
