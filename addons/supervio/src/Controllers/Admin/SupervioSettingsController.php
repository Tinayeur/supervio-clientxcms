<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Écran 1 : clé API Supervio et choix de la status page à publier.
 * Se remplit une fois, on n'y revient plus.
 *
 * L'adresse de l'API et celle de la page publique ne sont pas configurables :
 * ce sont des constantes du code. Voir SupervioApiClient::URL et
 * SupervioSettings::SLUG pour les raisons.
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
            'supervio_status_page_id' => ['nullable', 'string', 'max:64'],
        ]);

        $cleAvant = SupervioSettings::cle();

        /* La clé suit son propre chemin : elle est chiffrée, et un champ vide
           conserve la valeur existante au lieu de l'effacer. */
        if (filled($request->input('supervio_api_key'))) {
            SupervioSettings::enregistrerCle(trim((string) $request->input('supervio_api_key')));
        }

        Setting::updateSettings([
            SupervioSettings::PAGE_ID => $donnees['supervio_status_page_id'] ?? '',
        ]);

        PlanGate::oublier($cleAvant);
        PlanGate::oublier();
        StatusPageAssembler::oublier();
        Cache::forget('supervio:pages_disponibles');

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

        if (blank($cle)) {
            return response()->json(['ok' => false, 'message' => __('supervio::messages.admin.test.no_key')]);
        }

        $resultat = (new SupervioApiClient($cle))->testerConnexion();

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
}
