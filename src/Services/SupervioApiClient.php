<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Client HTTP de l'API Supervio. TOUS les appels partent du serveur : la clé
 * API n'est jamais transmise au navigateur, ni dans le HTML, ni dans un
 * attribut de données, ni dans un appel JavaScript.
 *
 * Contrat vérifié en direct contre https://supervio.fr/api/v1, avec un compte
 * Free et un compte Pro :
 *
 *   GET /me                     { tenant:{name,slug,plan}, token:{scope,max_history_days} }
 *   GET /status-pages           { data:[ {id,slug,title,monitor_ids,branding,published,…} ], pagination }
 *   GET /status-pages/{id}      la même entrée, seule — PAR IDENTIFIANT, un slug renvoie 400
 *   GET /monitors               { data:[…], pagination:{ next_cursor, limit } }
 *   GET /monitors/{id}/uptime   { range, samples, uptime_pct, avg_latency_ms, series[] }
 *   GET /incidents              { data:[ {id,monitor_id,monitor_name,started_at,resolved_at,cause,status,severity} ] }
 *
 * Authentification : « Authorization: Bearer <clé> ». Vérifié : « X-Api-Key » et
 * la clé en paramètre d'URL sont refusés en 401. Ne pas « corriger » cet en-tête.
 *
 * Aucun endpoint ne renvoie l'état complet d'une status page : /status-pages/{id}
 * ne contient que de la configuration, et /status-pages/{id}/data n'existe pas
 * (404). Les données vivantes sont donc assemblées par StatusPageAssembler.
 */

namespace App\Addons\Supervio\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupervioApiClient
{
    public const URL_PAR_DEFAUT = 'https://supervio.fr/api/v1';

    /** Garde-fou : évite de boucler sur un curseur qui ne se terminerait pas. */
    private const MAX_PAGES = 25;

    private const TIMEOUT = 8;

    public function __construct(
        private string $cle,
        private string $baseUrl = self::URL_PAR_DEFAUT,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /** Instance construite depuis les réglages enregistrés. */
    public static function depuisReglages(): ?self
    {
        $cle = SupervioSettings::cle();

        return $cle === null ? null : new self($cle, SupervioSettings::baseUrl());
    }

    /**
     * Identité de la clé : tenant, plan, profondeur d'historique autorisée.
     *
     * @return array{tenant:array,token:array}|null  null si la clé est refusée
     *                                               ou l'API injoignable
     */
    public function me(): ?array
    {
        $r = $this->get('/me');

        return ($r !== null && $r->successful() && is_array($r->json())) ? $r->json() : null;
    }

    /**
     * Résultat détaillé du test de connexion, pour le formulaire d'administration.
     *
     * On distingue le refus d'authentification de l'indisponibilité : « clé
     * invalide » et « Supervio ne répond pas » n'appellent pas la même action
     * de la part de l'administrateur.
     *
     * @return array{ok:bool, raison:string, tenant:?string, plan:?string, jours:?int}
     */
    public function testerConnexion(): array
    {
        try {
            $r = Http::withHeaders($this->entetes())->timeout(self::TIMEOUT)->get($this->baseUrl.'/me');
        } catch (\Throwable $e) {
            $this->journaliser('/me', $e->getMessage());

            return ['ok' => false, 'raison' => 'injoignable', 'tenant' => null, 'plan' => null, 'jours' => null];
        }

        if ($r->status() === 401 || $r->status() === 403) {
            return ['ok' => false, 'raison' => 'cle_invalide', 'tenant' => null, 'plan' => null, 'jours' => null];
        }

        if ($r->status() === 429) {
            return ['ok' => false, 'raison' => 'quota', 'tenant' => null, 'plan' => null, 'jours' => null];
        }

        if (! $r->successful() || ! is_array($r->json())) {
            return ['ok' => false, 'raison' => 'injoignable', 'tenant' => null, 'plan' => null, 'jours' => null];
        }

        $me = $r->json();

        return [
            'ok' => true,
            'raison' => 'ok',
            'tenant' => $me['tenant']['name'] ?? null,
            'plan' => $me['tenant']['plan'] ?? null,
            'jours' => (int) ($me['token']['max_history_days'] ?? 0),
        ];
    }

    /**
     * Status pages du compte, pagination suivie.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statusPages(): array
    {
        return $this->collecter('/status-pages');
    }

    /**
     * Une status page précise.
     *
     * L'API attend l'IDENTIFIANT, pas le slug : un slug renvoie 400. C'est
     * pourquoi le réglage enregistre l'id et non le slug.
     *
     * @return array<string, mixed>|null
     */
    public function statusPage(string $id): ?array
    {
        $r = $this->get('/status-pages/'.rawurlencode($id));

        return ($r !== null && $r->successful() && is_array($r->json())) ? $r->json() : null;
    }

    /**
     * Tous les monitors du compte, pagination suivie.
     *
     * Aucun plafond n'est appliqué ici. Le nombre de monitors visibles est une
     * limite d'abonnement que l'API fait respecter elle-même ; la redoubler
     * côté addon créerait une seconde source de vérité, et donnerait l'illusion
     * d'une protection qui n'en serait pas une.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monitors(): array
    {
        return $this->collecter('/monitors');
    }

    /**
     * Disponibilité d'un monitor sur une fenêtre.
     *
     * La fenêtre demandée part telle quelle : c'est l'API qui décide si
     * l'abonnement y donne droit. Trois issues distinctes, parce que l'appelant
     * doit pouvoir afficher « limité par votre abonnement » plutôt que « erreur ».
     *
     * Refus observé sur un compte Free avec range=90d :
     *   403 { "error": { "code": "plan_required", … } }
     *
     * @return array{ok:bool, data:?array, plan_requis:bool}
     */
    public function uptime(string $monitorId, string $range): array
    {
        $r = $this->get('/monitors/'.rawurlencode($monitorId).'/uptime', ['range' => $range]);

        if ($r === null) {
            return ['ok' => false, 'data' => null, 'plan_requis' => false];
        }

        if ($r->successful()) {
            return ['ok' => true, 'data' => $r->json(), 'plan_requis' => false];
        }

        return [
            'ok' => false,
            'data' => null,
            'plan_requis' => $r->status() === 403 && $r->json('error.code') === 'plan_required',
        ];
    }

    /**
     * Incidents du compte, les plus récents d'abord.
     *
     * L'endpoint est global au tenant : le filtrage sur les monitors de la
     * status page retenue se fait dans StatusPageAssembler.
     *
     * @return array<int, array<string, mixed>>
     */
    public function incidents(int $limite = 50): array
    {
        $r = $this->get('/incidents', ['limit' => $limite]);

        return ($r !== null && $r->successful()) ? ($r->json('data') ?? []) : [];
    }

    /**
     * Suit la pagination par curseur jusqu'à épuisement.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collecter(string $endpoint): array
    {
        $tout = [];
        $curseur = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => 100];
            if ($curseur !== null) {
                $query['cursor'] = $curseur;
            }

            $r = $this->get($endpoint, $query);
            if ($r === null || ! $r->successful()) {
                break;
            }

            $charge = $r->json() ?? [];
            $tout = array_merge($tout, $charge['data'] ?? []);

            $curseur = $charge['pagination']['next_cursor'] ?? null;
            if (! $curseur) {
                break;
            }
        }

        return $tout;
    }

    /** @param array<string, mixed> $query */
    private function get(string $endpoint, array $query = []): ?Response
    {
        try {
            return Http::withHeaders($this->entetes())
                ->timeout(self::TIMEOUT)
                ->get($this->baseUrl.$endpoint, $query);
        } catch (\Throwable $e) {
            $this->journaliser($endpoint, $e->getMessage());

            return null;
        }
    }

    /** @return array<string, string> */
    private function entetes(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->cle,
            'Accept' => 'application/json',
        ];
    }

    /** La clé ne doit jamais atterrir dans les journaux. */
    private function journaliser(string $endpoint, string $message): void
    {
        Log::error('[supervio] appel API en échec', [
            'endpoint' => $endpoint,
            'erreur' => \Str::limit(preg_replace('/\s+/', ' ', $message), 200),
        ]);
    }
}
