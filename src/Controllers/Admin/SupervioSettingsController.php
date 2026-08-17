<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Écran 1 : connexion à Supervio et adresse de la page publique.
 * Se remplit une fois, on n'y revient plus.
 *
 * La clé API n'est jamais renvoyée au navigateur. Le champ du formulaire part
 * toujours vide ; un envoi vide signifie « conserver la clé actuelle ».
 */

namespace App\Addons\Supervio\Controllers\Admin;

use App\Addons\Supervio\Services\PlanGate;
use App\Addons\Supervio\Services\StatusPageAssembler;
use App\Addons\Supervio\Services\SupervioApiClient;
use App\Addons\Supervio\Services\SupervioSettings;
use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SupervioSettingsController extends Controller
{
    public function show()
    {
        staff_aborts_permission(Permission::MANAGE_EXTENSIONS);

        $capacites = PlanGate::capacites();

        return view('supervio_admin::settings', [
            'capacites' => $capacites,
            'pages' => $this->pagesDisponibles(),
            'aUneCle' => SupervioSettings::cle() !== null,
            'slug' => SupervioSettings::slug(),
        ]);
    }

    public function update(Request $request)
    {
        staff_aborts_permission(Permission::MANAGE_EXTENSIONS);

        $donnees = $request->validate([
            'supervio_api_key' => ['nullable', 'string', 'max:255'],
            'supervio_api_url' => ['nullable', 'url', 'max:255'],
            'supervio_status_page_id' => ['nullable', 'string', 'max:64'],
            'supervio_page_slug' => ['nullable', 'string', 'max:60'],
        ]);

        $cleAvant = SupervioSettings::cle();

        /* La clé suit son propre chemin : elle est chiffrée, et un champ vide
           conserve la valeur existante au lieu de l'effacer. */
        if (filled($request->input('supervio_api_key'))) {
            SupervioSettings::enregistrerCle(trim((string) $request->input('supervio_api_key')));
        }

        /* Le slug est assaini avant écriture, pas seulement à la lecture : une
           valeur invalide en base reste une bombe à retardement pour quiconque
           lit ce réglage sans passer par l'accesseur. */
        $slug = SupervioSettings::assainirSlug((string) ($donnees['supervio_page_slug'] ?? ''));

        Setting::updateSettings([
            SupervioSettings::BASE_URL => $donnees['supervio_api_url'] ?? SupervioApiClient::URL_PAR_DEFAUT,
            SupervioSettings::PAGE_ID => $donnees['supervio_status_page_id'] ?? '',
            SupervioSettings::SLUG => $slug !== '' ? $slug : SupervioSettings::SLUG_DEFAUT,
        ]);

        PlanGate::oublier($cleAvant);
        PlanGate::oublier();
        StatusPageAssembler::oublier();
        Cache::forget('supervio:pages_disponibles');

        /* Le slug construit une route : elle n'existera sous sa nouvelle forme
           qu'après vidage du cache de routes. Sans ça, l'administrateur
           enregistre, clique sur le lien, et tombe sur un 404. */
        $this->viderCacheDeRoutes();

        return redirect()
            ->route('admin.settings.supervio')
            ->with('success', __('supervio::messages.admin.saved'));
    }

    /**
     * Test de connexion, appelé par le bouton du formulaire.
     *
     * Teste la clé saisie si elle est présente, sinon celle enregistrée : sans
     * cela, on ne pourrait pas revérifier une configuration existante, la clé
     * n'étant jamais réaffichée.
     */
    public function test(Request $request)
    {
        staff_aborts_permission(Permission::MANAGE_EXTENSIONS);

        $cle = trim((string) $request->input('supervio_api_key')) ?: SupervioSettings::cle();
        $base = trim((string) $request->input('supervio_api_url')) ?: SupervioSettings::baseUrl();

        if (blank($cle)) {
            return response()->json(['ok' => false, 'message' => __('supervio::messages.admin.test.no_key')]);
        }

        $resultat = (new SupervioApiClient($cle, $base))->testerConnexion();

        if (! $resultat['ok']) {
            return response()->json([
                'ok' => false,
                'message' => __('supervio::messages.admin.test.'.$resultat['raison']),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => __('supervio::messages.admin.test.ok', [
                'tenant' => $resultat['tenant'] ?? '—',
                'plan' => $resultat['plan'] ?? '—',
                'jours' => $resultat['jours'],
            ]),
        ]);
    }

    /**
     * Status pages proposées dans le sélecteur.
     *
     * @return array<string, string>  identifiant => libellé
     */
    private function pagesDisponibles(): array
    {
        $client = SupervioApiClient::depuisReglages();

        if ($client === null) {
            return [];
        }

        $pages = Cache::remember('supervio:pages_disponibles', 300, fn () => $client->statusPages());

        $liste = [];
        foreach ($pages as $p) {
            $id = $p['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $libelle = $p['title'] ?? $p['slug'] ?? $id;
            if (isset($p['published']) && ! $p['published']) {
                $libelle .= ' — '.__('supervio::messages.admin.unpublished');
            }

            $liste[$id] = $libelle;
        }

        return $liste;
    }

    private function viderCacheDeRoutes(): void
    {
        try {
            if (file_exists(app()->getCachedRoutesPath())) {
                \Artisan::call('route:clear');
            }
        } catch (\Throwable $e) {
            \Log::warning('[supervio] vidage du cache de routes en échec', ['erreur' => $e->getMessage()]);
        }
    }
}
