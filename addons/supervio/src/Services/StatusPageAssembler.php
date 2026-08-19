<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Assemble les données de la page de statut à partir de plusieurs endpoints,
 * aucun ne renvoyant l'état complet d'une status page.
 *
 *   /status-pages/{id}          configuration : titre, monitor_ids, branding
 *   /monitors                   état courant de tous les services
 *   /monitors/{id}/uptime       disponibilité, un appel par service retenu
 *   /incidents                  incidents du compte, filtrés sur monitor_ids
 *
 * Chaque étage a son propre cache, avec une durée adaptée à sa volatilité :
 * la configuration d'une page bouge rarement, l'état des services change à
 * chaque sonde. Un cache global unique aurait imposé la durée la plus courte à
 * tout le monde, et multiplié les appels inutiles.
 *
 * Règle fondatrice : LA PAGE NE REND QUE CE QUE L'API VIENT DE RENVOYER. Aucun
 * historique n'est reconstitué localement, aucune valeur extrapolée. C'est ce
 * qui rend inoffensif le forçage d'une option en base : un compte Free dont le
 * réglage porterait « 90d » enverra bien range=90d, recevra 403 « plan_required »,
 * et verra une mention honnête plutôt que des données auxquelles il n'a pas droit.
 */

namespace App\Addons\Supervio\Services;

use App\Addons\Supervio\DTO\IncidentDTO;
use App\Addons\Supervio\DTO\MonitorDTO;
use Illuminate\Support\Facades\Cache;

class StatusPageAssembler
{
    /** La configuration d'une status page ne bouge quasiment jamais. */
    private const TTL_CONFIG = 600;

    /**
     * @return array{
     *   page: ?array, monitors: array<int,MonitorDTO>, incidents: array<int,IncidentDTO>,
     *   uptime: array<string,?array>, range: string, erreur: ?string,
     *   historique_limite: bool, capacites: array
     * }
     */
    public static function assembler(): array
    {
        $client = SupervioApiClient::depuisReglages();
        $pageId = SupervioSettings::pageId();
        $capacites = PlanGate::capacites();

        if ($client === null || $pageId === null) {
            return self::vide($capacites, 'non_configure');
        }

        $ttl = SupervioSettings::cacheTtl();

        // ── Étage 1 : configuration de la page ────────────────────────────
        $page = Cache::remember(
            'supervio:page:'.$pageId,
            self::TTL_CONFIG,
            fn () => $client->statusPage($pageId)
        );

        if (! is_array($page)) {
            return self::vide($capacites, 'api_injoignable');
        }

        $idsRetenus = array_values(array_filter((array) ($page['monitor_ids'] ?? [])));

        // ── Étage 2 : état des services ───────────────────────────────────
        $bruts = Cache::remember('supervio:monitors', $ttl, fn () => $client->monitors());

        if ($bruts === []) {
            return self::vide($capacites, $capacites['connu'] ? 'aucun_service' : 'api_injoignable', $page);
        }

        /* Restreindre aux services de la page choisie ne fait que retirer des
           entrées déjà renvoyées par l'API : aucune limite d'abonnement n'est
           contournée. Une page sans monitor_ids affiche tout le compte. */
        $monitors = [];
        foreach ($bruts as $m) {
            $id = $m['id'] ?? '';

            /* Deux filtres successifs, de natures différentes : la status page
               Supervio définit les services concernés, puis l'administrateur
               peut en masquer certains depuis ClientXCMS. Aucun des deux ne
               fait apparaître un service que l'API n'a pas renvoyé. */
            if ($idsRetenus !== [] && ! in_array($id, $idsRetenus, true)) {
                continue;
            }

            $reglages = SupervioSettings::sonde($id);

            if (! $reglages['visible']) {
                continue;
            }

            $dto = MonitorDTO::depuisApi($m);
            $dto->iconeType = $reglages['icone_type'];
            $dto->icone = $reglages['icone'];
            $dto->categorie = $reglages['categorie'];

            $monitors[] = $dto;
        }

        // ── Étage 3 : disponibilité, un appel par service ─────────────────
        $demande = SupervioSettings::historique();
        $autorises = PlanGate::rangesAutorises($capacites['jours']);
        $range = in_array($demande, $autorises, true) ? $demande : (string) end($autorises);

        $limite = $demande !== $range;
        $uptime = [];

        foreach ($monitors as $monitor) {
            $resultat = Cache::remember(
                'supervio:uptime:'.$monitor->id.':'.$range,
                $ttl,
                fn () => $client->uptime($monitor->id, $range)
            );

            $uptime[$monitor->id] = $resultat['data'] ?? null;

            /* Un seul refus suffit : la limite vaut pour le compte, pas pour un
               service en particulier. */
            if (! empty($resultat['plan_requis'])) {
                $limite = true;
            }
        }

        // ── Étage 4 : incidents, filtrés sur les services de la page ──────
        $incidents = [];
        foreach (Cache::remember('supervio:incidents', $ttl, fn () => $client->incidents()) as $i) {
            if ($idsRetenus === [] || in_array($i['monitor_id'] ?? '', $idsRetenus, true)) {
                $incidents[] = IncidentDTO::depuisApi($i);
            }
        }

        return [
            'page' => $page,
            'monitors' => $monitors,
            'groupes' => self::grouper($monitors),
            'incidents' => $incidents,
            'uptime' => $uptime,
            'range' => $range,
            'erreur' => null,
            'historique_limite' => $limite,
            'capacites' => $capacites,
        ];
    }

    /**
     * Répartit les services dans les catégories déclarées.
     *
     * Les services sans catégorie, ou rattachés à une catégorie supprimée
     * depuis, forment un groupe sans titre placé en tête : ils doivent rester
     * visibles, pas disparaître parce qu'on a renommé une catégorie.
     *
     * @param  array<int, MonitorDTO>  $monitors
     * @return array<int, array{titre:?string, monitors:array<int,MonitorDTO>}>
     */
    private static function grouper(array $monitors): array
    {
        $categories = SupervioSettings::categories();

        if ($categories === []) {
            return [['titre' => null, 'monitors' => $monitors]];
        }

        $connues = array_column($categories, 'id');
        $groupes = [];
        $orphelins = [];

        foreach ($monitors as $m) {
            if ($m->categorie === '' || ! in_array($m->categorie, $connues, true)) {
                $orphelins[] = $m;

                continue;
            }

            $groupes[$m->categorie][] = $m;
        }

        $resultat = [];

        if ($orphelins !== []) {
            $resultat[] = ['titre' => null, 'monitors' => $orphelins];
        }

        foreach ($categories as $categorie) {
            if (! empty($groupes[$categorie['id']])) {
                $resultat[] = ['titre' => $categorie['nom'], 'monitors' => $groupes[$categorie['id']]];
            }
        }

        return $resultat;
    }

    /** Purge de tous les étages. Appelée après toute modification des réglages. */
    public static function oublier(): void
    {
        $pageId = SupervioSettings::pageId();

        /* Les clés d'uptime sont dérivées des identifiants de services : il faut
           donc lire la liste AVANT de l'effacer, sinon il n'y a plus rien pour
           reconstruire ces clés et elles survivent à la purge. */
        $monitors = Cache::get('supervio:monitors', []);

        foreach ($monitors as $m) {
            foreach (array_keys(PlanGate::RANGES) as $range) {
                Cache::forget('supervio:uptime:'.($m['id'] ?? '').':'.$range);
            }
        }

        Cache::forget('supervio:monitors');
        Cache::forget('supervio:incidents');
        Cache::forget('supervio:pages_disponibles');

        if ($pageId !== null) {
            Cache::forget('supervio:page:'.$pageId);
        }
    }

    /** @return array<string, mixed> */
    private static function vide(array $capacites, string $erreur, ?array $page = null): array
    {
        return [
            'page' => $page,
            'monitors' => [],
            'groupes' => [],
            'incidents' => [],
            'uptime' => [],
            'range' => '24h',
            'erreur' => $erreur,
            'historique_limite' => false,
            'capacites' => $capacites,
        ];
    }
}
