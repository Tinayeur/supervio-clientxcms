<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Écran 2 : apparence et contenu de la page de statut.
 * On y revient régulièrement, il ne mélange donc aucune configuration technique.
 *
 * Les options réservées aux abonnements payants sont grisées dans le formulaire
 * ET ignorées ici : les masquer seules aurait laissé une requête forgée les
 * activer. Ce filtrage reste cosmétique — la profondeur d'historique et le
 * nombre de services affichables sont décidés par l'API Supervio elle-même.
 */

namespace App\Addons\Supervio\Controllers\Admin;

use App\Addons\Supervio\Services\ContenuLibre;
use App\Addons\Supervio\Services\PlanGate;
use App\Addons\Supervio\Services\StatusPageAssembler;
use App\Addons\Supervio\Services\SupervioApiClient;
use App\Addons\Supervio\Services\SupervioSettings;
use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StatusPageSettingsController extends Controller
{
    public function show()
    {
        staff_aborts_permission(Permission::MANAGE_EXTENSIONS);

        $capacites = PlanGate::capacites();

        /* Sans clé ni page sélectionnée, cet écran n'a rien à configurer : on
           renvoie vers l'écran 1 avec une explication, plutôt que d'afficher un
           formulaire dont aucun réglage ne serait visible sur le site. */
        if (! SupervioSettings::estConfigure()) {
            return redirect()
                ->route('admin.settings.supervio')
                ->with('warning', __('supervio::messages.admin.page.setup_first'));
        }

        return view('supervio_admin::page', [
            'capacites' => $capacites,
            'options' => PlanGate::options($capacites),
            'slug' => SupervioSettings::slug(),
            'sondes' => $this->sondesDisponibles(),
            'categories' => SupervioSettings::categories(),
        ]);
    }

    /**
     * Téléversement ou suppression du logo.
     *
     * L'ancien fichier est supprimé du disque à chaque remplacement : sans ça,
     * chaque changement de logo laisserait un orphelin derrière lui.
     */
    private function traiterLogo(Request $request): void
    {
        $actuel = (string) setting(SupervioSettings::LOGO_FICHIER, '');

        if ($request->boolean('supprimer_logo')) {
            if ($actuel !== '') {
                Storage::disk('public')->delete($actuel);
            }
            Setting::updateSettings([SupervioSettings::LOGO_FICHIER => '']);

            return;
        }

        if (! $request->hasFile('logo_fichier')) {
            return;
        }

        if ($actuel !== '') {
            Storage::disk('public')->delete($actuel);
        }

        $chemin = $request->file('logo_fichier')->store('supervio', 'public');
        Setting::updateSettings([SupervioSettings::LOGO_FICHIER => $chemin]);
    }

    /**
     * Catégories saisies, nettoyées et repositionnées.
     *
     * Chaque catégorie garde son identifiant d'origine : le renommer casserait
     * le rattachement des sondes qui la référencent.
     *
     * @return array<int, array{id:string, nom:string, position:int}>
     */
    private function categories(Request $request): array
    {
        $categories = [];
        $position = 0;

        foreach ((array) $request->input('categories', []) as $c) {
            $nom = trim((string) ($c['nom'] ?? ''));

            if ($nom === '') {
                continue;
            }

            $categories[] = [
                'id' => (string) ($c['id'] ?? '') ?: \Str::random(8),
                'nom' => $nom,
                'position' => $position++,
            ];
        }

        return $categories;
    }

    /**
     * Configuration par sonde.
     *
     * La visibilité est déduite d'une case cochée : une sonde absente du tableau
     * « visibles » est masquée. On enregistre donc explicitement « visible » à
     * false plutôt que d'omettre l'entrée, sinon SupervioSettings::sonde() la
     * réafficherait par défaut au prochain rendu.
     *
     * @return array<string, array{visible:bool, icone_type:string, icone:string, categorie:string}>
     */
    private function sondes(Request $request): array
    {
        $visibles = (array) $request->input('visibles', []);
        $config = [];

        foreach ((array) $request->input('sondes', []) as $id => $s) {
            $id = (string) $id;

            $config[$id] = [
                'visible' => in_array($id, $visibles, true),
                'icone_type' => in_array($s['icone_type'] ?? '', SupervioSettings::ICONES, true)
                    ? $s['icone_type']
                    : 'aucune',
                'icone' => trim((string) ($s['icone'] ?? '')),
                'categorie' => trim((string) ($s['categorie'] ?? '')),
            ];
        }

        return $config;
    }

    /**
     * Sondes de la status page retenue, telles que l'API les renvoie.
     *
     * On repart toujours de l'API plutôt que d'une liste mémorisée : une sonde
     * ajoutée ou supprimée côté Supervio doit apparaître ou disparaître ici sans
     * intervention.
     *
     * @return array<int, array{id:string, nom:string, reglages:array}>
     */
    private function sondesDisponibles(): array
    {
        $client = SupervioApiClient::depuisReglages();
        $pageId = SupervioSettings::pageId();

        if ($client === null || $pageId === null) {
            return [];
        }

        $page = $client->statusPage($pageId);
        $retenues = array_values(array_filter((array) ($page['monitor_ids'] ?? [])));

        $sondes = [];
        foreach ($client->monitors() as $m) {
            $id = $m['id'] ?? '';

            if ($retenues !== [] && ! in_array($id, $retenues, true)) {
                continue;
            }

            $sondes[] = [
                'id' => $id,
                'nom' => $m['name'] ?? $id,
                'reglages' => SupervioSettings::sonde($id),
            ];
        }

        return $sondes;
    }

    public function update(Request $request)
    {
        staff_aborts_permission(Permission::MANAGE_EXTENSIONS);

        $capacites = PlanGate::capacites();
        $options = PlanGate::options($capacites);

        $donnees = $request->validate([
            'supervio_page_title' => ['nullable', 'string', 'max:120'],
            'supervio_logo_url' => ['nullable', 'url', 'max:255'],
            'supervio_accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'supervio_background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'supervio_template' => ['nullable', Rule::in(PlanGate::TEMPLATES)],
            'supervio_history_range' => ['nullable', Rule::in(array_keys(PlanGate::RANGES))],
            'supervio_refresh_seconds' => ['nullable', 'integer', 'min:0', 'max:900'],
            'supervio_cache_ttl' => ['nullable', 'integer', 'min:30', 'max:900'],

            /* Le logo est borné en taille et en type : un fichier téléversé est
               servi publiquement, un SVG y passerait du script. */
            'logo_fichier' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'supprimer_logo' => ['nullable', 'boolean'],

            'sondes' => ['nullable', 'array'],
            'sondes.*.icone_type' => ['nullable', Rule::in(SupervioSettings::ICONES)],
            'sondes.*.icone' => ['nullable', 'string', 'max:40'],
            'sondes.*.categorie' => ['nullable', 'string', 'max:32'],

            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*.id' => ['nullable', 'string', 'max:32'],
            'categories.*.nom' => ['nullable', 'string', 'max:60'],

            'supervio_footer_mode' => ['nullable', Rule::in(SupervioSettings::PIEDS)],
            'supervio_footer_text' => ['nullable', 'string', 'max:2000'],
            'supervio_custom_css' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->traiterLogo($request);

        $reglages = [
            // Toujours modifiables
            SupervioSettings::TITRE => $donnees['supervio_page_title'] ?? '',
            SupervioSettings::LOGO => $donnees['supervio_logo_url'] ?? '',
            SupervioSettings::COULEUR_ACCENT => $donnees['supervio_accent_color'] ?? '',
            SupervioSettings::AFFICHER_UPTIME => $request->boolean('supervio_show_uptime'),
            SupervioSettings::AFFICHER_INCIDENTS => $request->boolean('supervio_show_incidents'),
            SupervioSettings::RAFRAICHISSEMENT => $donnees['supervio_refresh_seconds'] ?? 0,
            SupervioSettings::CACHE_TTL => $donnees['supervio_cache_ttl'] ?? SupervioSettings::CACHE_TTL_DEFAUT,

            /* Bornée par ce que l'abonnement autorise. Une fenêtre plus large
               partirait quand même à l'API, qui la refuserait en 403 — autant
               ne pas l'enregistrer. */
            SupervioSettings::HISTORIQUE => in_array($donnees['supervio_history_range'] ?? '', $options['ranges'], true)
                ? $donnees['supervio_history_range']
                : (string) end($options['ranges']),

            // Sondes et catégories
            SupervioSettings::CATEGORIES => json_encode($this->categories($request), JSON_UNESCAPED_UNICODE),
            SupervioSettings::SONDES => json_encode($this->sondes($request), JSON_UNESCAPED_UNICODE),

            // Réservées aux abonnements payants
            SupervioSettings::TEMPLATE => $options['templates']
                ? ($donnees['supervio_template'] ?? 'aurore')
                : 'aurore',
            SupervioSettings::DARK_MODE => $options['dark_mode'] && $request->boolean('supervio_dark_mode'),
            SupervioSettings::COULEUR_FOND => $options['couleur_fond'] ? ($donnees['supervio_background_color'] ?? '') : '',

            /* Sans abonnement payant, le pied de page reste la mention Supervio :
               c'est la contrepartie de la gratuité, pas une option d'affichage. */
            SupervioSettings::PIED_MODE => $options['pied']
                ? ($donnees['supervio_footer_mode'] ?? 'supervio')
                : 'supervio',

            /* Nettoyés à l'enregistrement ET au rendu : une valeur écrite
               directement en base ne doit pas non plus pouvoir sortir de son
               contexte HTML. */
            SupervioSettings::PIED_TEXTE => $options['pied']
                ? ContenuLibre::pied((string) ($donnees['supervio_footer_text'] ?? ''))
                : '',
            SupervioSettings::CSS => $options['css']
                ? ContenuLibre::css((string) ($donnees['supervio_custom_css'] ?? ''))
                : '',
        ];

        Setting::updateSettings($reglages);
        StatusPageAssembler::oublier();

        return redirect()
            ->route('admin.settings.supervio_page')
            ->with('success', __('supervio::messages.admin.page.saved'));
    }
}
