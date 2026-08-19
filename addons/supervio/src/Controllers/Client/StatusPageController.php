<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Page de statut publique : /statut.
 *
 * Rendu entièrement côté serveur. La clé API ne quitte jamais l'hébergement :
 * aucun appel à supervio.fr n'est émis depuis le navigateur du visiteur, et la
 * clé n'apparaît ni dans le HTML, ni dans un attribut de données, ni dans une
 * variable JavaScript. Le rafraîchissement se fait par rechargement de la page.
 */

namespace App\Addons\Supervio\Controllers\Client;

use App\Addons\Supervio\Services\ContenuLibre;
use App\Addons\Supervio\Services\PlanGate;
use App\Addons\Supervio\Services\StatusPageAssembler;
use App\Addons\Supervio\Services\SupervioSettings;
use App\Http\Controllers\Controller;

class StatusPageController extends Controller
{
    public function show()
    {
        /* Tant que l'addon n'est pas configuré, la route se comporte comme
           inexistante : publier une page vide au nom de l'hébergeur serait pire
           que ne rien publier. */
        if (! SupervioSettings::estConfigure()) {
            abort(404);
        }

        $donnees = StatusPageAssembler::assembler();

        $options = PlanGate::options($donnees['capacites']);

        /* Le template n'est appliqué que si le plan y donne droit : un réglage
           resté en base après une résiliation ne doit pas continuer à servir un
           rendu payant. */
        $template = $options['templates'] ? SupervioSettings::template() : 'aurore';
        if (! in_array($template, PlanGate::TEMPLATES, true)) {
            $template = 'aurore';
        }

        /* Le pied de page et le CSS libre sont réservés aux abonnements payants.
           Un réglage resté en base après une résiliation ne doit pas continuer à
           masquer la mention Supervio ni injecter du style. */
        $piedMode = $options['pied'] ? SupervioSettings::piedMode() : 'supervio';

        return view('supervio::templates.'.$template, [
            'page' => $donnees['page'],
            'monitors' => $donnees['monitors'],
            'groupes' => $donnees['groupes'],
            'incidents' => $donnees['incidents'],
            'piedMode' => $piedMode,
            'piedTexte' => $piedMode === 'personnalise'
                ? ContenuLibre::pied(SupervioSettings::piedTexte())
                : '',
            'cssPersonnalise' => $options['css'] ? ContenuLibre::css(SupervioSettings::css()) : '',
            'uptime' => $donnees['uptime'],
            'range' => $donnees['range'],
            'erreur' => $donnees['erreur'],
            'historiqueLimite' => $donnees['historique_limite'],
            'capacites' => $donnees['capacites'],
            /* Le titre saisi dans les réglages prime sur celui de la status page
               Supervio : c'est le seul moyen d'adapter le libellé au site sans
               renommer la page côté Supervio, où elle sert peut-être ailleurs. */
            'titre' => SupervioSettings::titre() ?? $donnees['page']['title'] ?? config('app.name'),
            'logo' => SupervioSettings::logo(),
            'darkMode' => $options['dark_mode'] && SupervioSettings::darkMode(),
            'couleurFond' => $options['couleur_fond'] ? SupervioSettings::couleurFond() : null,

            /* Couleur d'accent : réglage local, sinon celle définie sur la status
               page Supervio, sinon un bleu neutre. */
            'couleurAccent' => SupervioSettings::couleurAccent()
                ?? ($donnees['page']['branding']['color'] ?? '#2563eb'),

            'afficherUptime' => SupervioSettings::afficherUptime(),
            'afficherIncidents' => SupervioSettings::afficherIncidents(),
            'rafraichissement' => SupervioSettings::rafraichissement(),
        ]);
    }

    /** Convention ClientXCMS : /status redirige en 301 vers /statut. */
    public function redirection()
    {
        return redirect()->route('client.supervio.status', [], 301);
    }
}
