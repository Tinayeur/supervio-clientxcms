<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Détermine ce que l'abonnement Supervio du compte autorise.
 *
 * Trois principes, à ne pas contourner :
 *
 * 1. Le plan n'est jamais stocké durablement. Il est relu auprès de GET /me et
 *    ne vit qu'en cache, quelques minutes. Un réglage « is_pro » en base serait
 *    modifiable par accès direct, donc un contournement durable.
 *
 * 2. Tout plan inconnu est traité comme « free ». En cas d'échec de l'API, de
 *    plan renommé ou de palier ajouté côté Supervio, l'addon se referme au lieu
 *    de s'ouvrir. Une panne ne doit jamais débloquer une fonctionnalité payante.
 *
 * 3. Ajouter un palier (« starter » par exemple) se fait par une entrée dans
 *    PLANS ci-dessous, sans toucher au reste du code.
 *
 * Ce portier pilote CE QUI EST PROPOSÉ dans le formulaire. Il ne protège aucune
 * donnée : la profondeur d'historique et le nombre de services affichables sont
 * décidés par l'API Supervio, qui refuse en 403 « plan_required » ce que
 * l'abonnement ne couvre pas. Une option cosmétique forcée en base ne donne donc
 * jamais accès à plus de données.
 */

namespace App\Addons\Supervio\Services;

use Illuminate\Support\Facades\Cache;

class PlanGate
{
    /** Assez court pour qu'une résiliation se voie vite, assez long pour ne pas
     *  appeler /me à chaque visite de la page publique. */
    public const TTL = 300;

    /**
     * Capacités par identifiant de plan renvoyé par l'API.
     *
     * Ajouter un palier = ajouter une ligne. Toute valeur absente de ce tableau
     * retombe sur « free ».
     */
    public const PLANS = [
        'free' => [
            'templates' => false,
            'dark_mode' => false,
            'couleur_fond' => false,
            'masquer_marque' => false,
            'pied' => false,
            'css' => false,
        ],
        'pro' => [
            'templates' => true,
            'dark_mode' => true,
            'couleur_fond' => true,
            'masquer_marque' => true,
            'pied' => true,
            'css' => true,
        ],
    ];

    /** Fenêtres d'historique et profondeur exigée, en jours. */
    public const RANGES = ['24h' => 1, '7d' => 7, '30d' => 30, '90d' => 90];

    /** Templates fournis par l'addon. Ce sont des rendus propres à ClientXCMS :
     *  l'API Supervio n'expose aucune définition de template. */
    public const TEMPLATES = ['aurore', 'nocturne', 'signal'];

    /**
     * Capacités du compte à cet instant.
     *
     * @return array{connu:bool, plan:string, jours:int, tenant:?string}
     */
    public static function capacites(): array
    {
        $cle = SupervioSettings::cle();

        if ($cle === null) {
            return self::inconnu();
        }

        $me = Cache::remember(
            'supervio:plan:'.md5($cle),
            self::TTL,
            fn () => (new SupervioApiClient($cle))->me()
        );

        if (! is_array($me)) {
            return self::inconnu();
        }

        return [
            'connu' => true,
            'plan' => (string) ($me['tenant']['plan'] ?? 'free'),
            'jours' => (int) ($me['token']['max_history_days'] ?? 30),
            'tenant' => $me['tenant']['name'] ?? null,
        ];
    }

    /** Purge immédiate : après changement de clé, le plan en cache peut
     *  appartenir à un autre compte. */
    public static function oublier(?string $cle = null): void
    {
        $cle ??= SupervioSettings::cle();

        if ($cle !== null) {
            Cache::forget('supervio:plan:'.md5($cle));
        }
    }

    /**
     * Options d'apparence ouvertes au plan courant.
     *
     * @return array{templates:bool, dark_mode:bool, couleur_fond:bool,
     *               masquer_marque:bool, pied:bool, css:bool,
     *               ranges:array<int,string>}
     */
    public static function options(?array $capacites = null): array
    {
        $capacites ??= self::capacites();

        /* Repli explicite sur « free » : plan inconnu, plan renommé, ou API
           muette aboutissent tous au jeu d'options le plus restreint. */
        $droits = self::PLANS[$capacites['plan']] ?? self::PLANS['free'];

        return $droits + ['ranges' => self::rangesAutorises($capacites['jours'])];
    }

    /**
     * Fenêtres proposables, dérivées de la profondeur réellement autorisée.
     *
     * On s'appuie sur « max_history_days », un nombre, plutôt que sur
     * l'étiquette du plan : si Supervio ajoute un palier à 365 jours, l'addon
     * l'expose sans modification.
     *
     * @return array<int, string>
     */
    public static function rangesAutorises(int $jours): array
    {
        $autorises = array_keys(array_filter(self::RANGES, fn (int $requis) => $requis <= $jours));

        return $autorises !== [] ? $autorises : ['24h'];
    }

    /** @return array{connu:bool, plan:string, jours:int, tenant:?string} */
    private static function inconnu(): array
    {
        return ['connu' => false, 'plan' => 'free', 'jours' => 30, 'tenant' => null];
    }
}
