<?php

/* Addon Supervio pour ClientXCMS. */

namespace App\Addons\Supervio;

use App\Addons\Supervio\Controllers\Admin\StatusPageSettingsController;
use App\Addons\Supervio\Controllers\Admin\SupervioSettingsController;
use App\Extensions\BaseAddonServiceProvider;
use App\Models\Admin\Permission;
use Illuminate\Support\Facades\Log;

class SupervioServiceProvider extends BaseAddonServiceProvider
{
    protected string $name = 'Supervio';

    protected string $version = '1.0.0';

    protected string $uuid = 'supervio';

    public function boot(): void
    {
        $this->loadViews();
        $this->loadTranslations();

        /* Aucune migration : les réglages passent par App\Models\Admin\Setting.
           L'addon s'installe donc sans toucher au schéma. loadMigrations() est
           tout de même appelé pour rester conforme à la convention, et prendre
           en charge une éventuelle table ajoutée plus tard. */
        if (is_dir($this->addonPath('database/migrations'))) {
            $this->loadMigrations();
        }

        $this->chargerRoutes();

        /* Carte dédiée plutôt qu'une entrée dans « Extensions » : l'addon publie
           une page destinée aux visiteurs, ce n'est pas un réglage technique
           parmi d'autres. Position 6, dans la tranche des cartes métier. */
        app('settings')->addCard(
            'supervio',
            'supervio::messages.title',
            'supervio::messages.description',
            6,
            null,
            true,
            2,
            'bi bi-activity'
        );

        app('settings')->addCardItem(
            'supervio',
            'connexion',
            'supervio::messages.admin.card.settings',
            'supervio::messages.admin.card.settings_help',
            'bi bi-key',
            action([SupervioSettingsController::class, 'show']),
            Permission::MANAGE_EXTENSIONS
        );

        app('settings')->addCardItem(
            'supervio',
            'page',
            'supervio::messages.admin.card.page',
            'supervio::messages.admin.card.page_help',
            'bi bi-palette',
            action([StatusPageSettingsController::class, 'show']),
            Permission::MANAGE_EXTENSIONS
        );
    }

    /**
     * Chargement des routes.
     *
     * Enveloppé dans un try/catch : une erreur de route ne doit pas empêcher
     * l'application entière de démarrer. Un addon défaillant se signale dans les
     * journaux, il ne met pas le site à terre.
     */
    private function chargerRoutes(): void
    {
        try {
            \Route::middleware(['web'])
                ->group(function () {
                    require $this->addonPath('routes/web.php');
                });

            /* Hors du préfixe « settings/… » — voir le commentaire en tête de
               routes/settings.php. */
            \Route::middleware(['web', 'admin'])
                ->prefix(admin_prefix('supervio'))
                ->name('admin.settings.')
                ->group(function () {
                    require $this->addonPath('routes/settings.php');
                });
        } catch (\Throwable $e) {
            Log::error('[supervio] chargement des routes en échec', [
                'erreur' => \Str::limit(preg_replace('/\s+/', ' ', $e->getMessage()), 200),
            ]);
        }
    }
}
