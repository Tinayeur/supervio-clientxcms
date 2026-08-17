<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Vue réduite d'un monitor, telle que la page publique en a besoin.
 *
 * GET /monitors renvoie 29 champs par monitor, dont des données d'exploitation
 * qui n'ont rien à faire sur une page publique : identifiants des canaux
 * d'alerte, identifiants des utilisateurs notifiés, seuils, valeurs DNS
 * attendues. Ce DTO agit comme une liste blanche : ce qui n'est pas repris ici
 * ne peut pas fuiter dans une vue par inadvertance.
 */

namespace App\Addons\Supervio\DTO;

class MonitorDTO
{
    public function __construct(
        public string $id,
        public string $nom,
        public string $type,
        public string $statut,
        public ?float $latenceMs,
        public ?string $dernierControle,
        public ?int $sslJoursRestants,
        /* Illustration et regroupement : réglages locaux de l'addon, absents de
           l'API. Renseignés par l'assembleur après construction. */
        public string $iconeType = 'aucune',
        public string $icone = '',
        public string $categorie = '',
    ) {}

    public static function depuisApi(array $m): self
    {
        return new self(
            id: (string) ($m['id'] ?? ''),
            nom: (string) ($m['name'] ?? '—'),
            type: (string) ($m['type'] ?? ''),
            statut: (string) ($m['status'] ?? 'unknown'),
            latenceMs: isset($m['last_latency_ms']) ? (float) $m['last_latency_ms'] : null,
            dernierControle: $m['last_check_at'] ?? null,
            sslJoursRestants: isset($m['ssl_days_remaining']) ? (int) $m['ssl_days_remaining'] : null,
        );
    }

    /** Clé de traduction du statut, pour ne pas afficher « up » à un visiteur. */
    public function libelleStatut(): string
    {
        return 'supervio::messages.status.'.match ($this->statut) {
            'up' => 'up',
            'down' => 'down',
            'paused' => 'paused',
            default => 'unknown',
        };
    }

    /** Classe de couleur, volontairement indépendante de la couleur d'accent :
     *  un service en panne doit rester rouge même sur un thème vert. */
    public function couleur(): string
    {
        return match ($this->statut) {
            'up' => 'emerald',
            'down' => 'rose',
            'paused' => 'slate',
            default => 'amber',
        };
    }
}
