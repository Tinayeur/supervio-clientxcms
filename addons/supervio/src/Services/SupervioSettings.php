<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Accès typé aux réglages de l'addon, et chiffrement de la clé API.
 *
 * Les réglages passent par App\Models\Admin\Setting : aucune table dédiée n'est
 * nécessaire, l'addon s'installe donc sans migration. En contrepartie, Setting
 * stocke des chaînes en clair — la clé API est donc chiffrée explicitement
 * avant écriture, et déchiffrée à la lecture.
 */

namespace App\Addons\Supervio\Services;

use App\Models\Admin\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class SupervioSettings
{
    public const CLE = 'supervio_api_key';

    public const PAGE_ID = 'supervio_status_page_id';

    public const TITRE = 'supervio_page_title';

    public const LOGO = 'supervio_logo_url';

    public const COULEUR_ACCENT = 'supervio_accent_color';

    public const AFFICHER_UPTIME = 'supervio_show_uptime';

    public const AFFICHER_INCIDENTS = 'supervio_show_incidents';

    public const RAFRAICHISSEMENT = 'supervio_refresh_seconds';

    /** Chemin du logo téléversé, sur le disque « public ». */
    public const LOGO_FICHIER = 'supervio_logo_path';

    /** JSON : { "<id de sonde>": { visible, icone_type, icone, categorie } } */
    public const SONDES = 'supervio_monitors_config';

    /** JSON : [ { id, nom, position } ] */
    public const CATEGORIES = 'supervio_categories';

    public const PIED_MODE = 'supervio_footer_mode';

    public const PIED_TEXTE = 'supervio_footer_text';

    public const CSS = 'supervio_custom_css';

    /** Modes de pied de page. « supervio » est le seul disponible sans abonnement payant. */
    public const PIEDS = ['supervio', 'personnalise', 'aucun'];

    /** Façons d'illustrer une sonde. */
    public const ICONES = ['aucune', 'emoji', 'icone'];

    public const TEMPLATE = 'supervio_template';

    public const DARK_MODE = 'supervio_dark_mode';

    public const COULEUR_FOND = 'supervio_background_color';

    public const MASQUER_MARQUE = 'supervio_hide_branding';

    public const HISTORIQUE = 'supervio_history_range';

    public const CACHE_TTL = 'supervio_cache_ttl';

    /** Valeur par défaut du cache, en secondes. */
    public const CACHE_TTL_DEFAUT = 180;

    /**
     * Clé API déchiffrée, ou null si absente.
     *
     * Le déchiffrement peut échouer si APP_KEY a changé depuis l'enregistrement.
     * On renvoie null plutôt que de laisser remonter l'exception : l'addon se
     * comporte alors comme non configuré, et l'administrateur ressaisit sa clé.
     */
    public static function cle(): ?string
    {
        $stockee = (string) setting(self::CLE, '');

        if ($stockee === '') {
            return null;
        }

        try {
            $claire = Crypt::decryptString($stockee);
        } catch (DecryptException) {
            return null;
        }

        return $claire !== '' ? $claire : null;
    }

    /** Enregistre la clé chiffrée. Une chaîne vide efface la clé. */
    public static function enregistrerCle(string $claire): void
    {
        Setting::updateSettings([
            self::CLE => $claire === '' ? '' : Crypt::encryptString($claire),
        ]);
    }

    public static function estConfigure(): bool
    {
        return self::cle() !== null && self::pageId() !== null;
    }

    public static function pageId(): ?string
    {
        $id = trim((string) setting(self::PAGE_ID, ''));

        return $id !== '' ? $id : null;
    }

    /**
     * Segment d'URL de la page publique.
     *
     * Constante, et non un réglage : ce slug construit une route chargée au
     * démarrage de l'application. Une valeur fantaisiste enregistrée par erreur
     * ne cassait pas seulement cette page, elle empêchait tout le site de
     * répondre. Le gain — choisir son adresse — ne valait pas ce risque, et la
     * redirection depuis /status couvre l'usage anglophone.
     */
    public const SLUG = 'statut';

    public static function slug(): string
    {
        return self::SLUG;
    }

    public static function titre(): ?string
    {
        $t = trim((string) setting(self::TITRE, ''));

        return $t !== '' ? $t : null;
    }

    /**
     * URL du logo : fichier téléversé en priorité, sinon lien saisi.
     *
     * Les deux cohabitent volontairement — un téléversement convient à la
     * plupart des administrateurs, un lien reste utile quand le logo est déjà
     * servi par un CDN.
     */
    public static function logo(): ?string
    {
        $fichier = trim((string) setting(self::LOGO_FICHIER, ''));

        if ($fichier !== '' && \Storage::disk('public')->exists($fichier)) {
            return \Storage::disk('public')->url($fichier);
        }

        $lien = trim((string) setting(self::LOGO, ''));

        return filter_var($lien, FILTER_VALIDATE_URL) ? $lien : null;
    }

    /**
     * Configuration par sonde.
     *
     * @return array<string, array{visible:bool, icone_type:string, icone:string, categorie:string}>
     */
    public static function sondes(): array
    {
        $brut = json_decode((string) setting(self::SONDES, '{}'), true);

        return is_array($brut) ? $brut : [];
    }

    /**
     * Réglages d'une sonde, avec valeurs par défaut.
     *
     * Une sonde inconnue de la configuration est affichée : un service ajouté
     * côté Supervio doit apparaître tout seul, pas rester invisible jusqu'à ce
     * qu'on pense à revenir cocher une case.
     *
     * @return array{visible:bool, icone_type:string, icone:string, categorie:string}
     */
    public static function sonde(string $id): array
    {
        $config = self::sondes()[$id] ?? [];

        return [
            'visible' => (bool) ($config['visible'] ?? true),
            'icone_type' => in_array($config['icone_type'] ?? '', self::ICONES, true) ? $config['icone_type'] : 'aucune',
            'icone' => (string) ($config['icone'] ?? ''),
            'categorie' => (string) ($config['categorie'] ?? ''),
        ];
    }

    /**
     * Catégories déclarées, triées.
     *
     * @return array<int, array{id:string, nom:string, position:int}>
     */
    public static function categories(): array
    {
        $brut = json_decode((string) setting(self::CATEGORIES, '[]'), true);

        if (! is_array($brut)) {
            return [];
        }

        $categories = [];
        foreach ($brut as $c) {
            if (! is_array($c) || blank($c['nom'] ?? null)) {
                continue;
            }

            $categories[] = [
                'id' => (string) ($c['id'] ?? \Str::random(8)),
                'nom' => (string) $c['nom'],
                'position' => (int) ($c['position'] ?? 0),
            ];
        }

        usort($categories, fn ($a, $b) => $a['position'] <=> $b['position']);

        return $categories;
    }

    public static function piedMode(): string
    {
        $mode = (string) setting(self::PIED_MODE, 'supervio');

        return in_array($mode, self::PIEDS, true) ? $mode : 'supervio';
    }

    public static function piedTexte(): string
    {
        return (string) setting(self::PIED_TEXTE, '');
    }

    public static function css(): string
    {
        return (string) setting(self::CSS, '');
    }

    public static function couleurAccent(): ?string
    {
        $c = trim((string) setting(self::COULEUR_ACCENT, ''));

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1 ? $c : null;
    }

    public static function afficherUptime(): bool
    {
        return (bool) setting(self::AFFICHER_UPTIME, true);
    }

    public static function afficherIncidents(): bool
    {
        return (bool) setting(self::AFFICHER_INCIDENTS, true);
    }

    public static function rafraichissement(): int
    {
        $s = (int) setting(self::RAFRAICHISSEMENT, 0);

        /* 0 = pas de rechargement automatique. Sinon, jamais en dessous de
           30 s : la page serait rechargée plus vite que le cache ne se
           renouvelle, pour rien. */
        return $s === 0 ? 0 : max(30, min(900, $s));
    }

    public static function template(): string
    {
        return (string) setting(self::TEMPLATE, 'aurore');
    }

    public static function darkMode(): bool
    {
        return (bool) setting(self::DARK_MODE, false);
    }

    public static function couleurFond(): ?string
    {
        $c = trim((string) setting(self::COULEUR_FOND, ''));

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1 ? $c : null;
    }

    public static function masquerMarque(): bool
    {
        return (bool) setting(self::MASQUER_MARQUE, false);
    }

    public static function historique(): string
    {
        return (string) setting(self::HISTORIQUE, '24h');
    }

    public static function cacheTtl(): int
    {
        $ttl = (int) setting(self::CACHE_TTL, self::CACHE_TTL_DEFAUT);

        /* Bornes volontaires : en dessous de 30 s on martèle l'API pour rien,
           au-delà d'un quart d'heure la page ne mérite plus le mot « statut ». */
        return max(30, min(900, $ttl));
    }
}
