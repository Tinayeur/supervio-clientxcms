<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Incident tel qu'affiché sur la page publique.
 *
 * Champs renvoyés par GET /incidents : id, monitor_id, monitor_name, started_at,
 * resolved_at, cause, status, severity. « monitor_id » n'est pas repris : un
 * identifiant technique n'apporte rien à un visiteur.
 */

namespace App\Addons\Supervio\DTO;

use Carbon\Carbon;

class IncidentDTO
{
    public function __construct(
        public string $id,
        public string $monitor,
        public ?Carbon $debut,
        public ?Carbon $fin,
        public ?string $cause,
        public string $statut,
        public string $severite,
    ) {}

    public static function depuisApi(array $i): self
    {
        return new self(
            id: (string) ($i['id'] ?? ''),
            monitor: (string) ($i['monitor_name'] ?? '—'),
            debut: isset($i['started_at']) ? Carbon::parse($i['started_at']) : null,
            fin: isset($i['resolved_at']) ? Carbon::parse($i['resolved_at']) : null,
            cause: $i['cause'] ?? null,
            statut: (string) ($i['status'] ?? 'open'),
            severite: (string) ($i['severity'] ?? 'minor'),
        );
    }

    public function estResolu(): bool
    {
        return $this->statut === 'resolved' && $this->fin !== null;
    }

    /** Durée lisible, ou null tant que l'incident est en cours. */
    public function duree(): ?string
    {
        if ($this->debut === null || $this->fin === null) {
            return null;
        }

        return $this->debut->diffForHumans($this->fin, true);
    }

    public function couleur(): string
    {
        if ($this->estResolu()) {
            return 'emerald';
        }

        return match ($this->severite) {
            'critical' => 'rose',
            'major' => 'orange',
            default => 'amber',
        };
    }
}
