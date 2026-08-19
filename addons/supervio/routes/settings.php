<?php

/*
 * Addon Supervio pour ClientXCMS — écrans de réglages.
 *
 * Monté hors du préfixe « settings/… » : le cœur y déclare le joker
 * admin/settings/{card}/{uuid}, qui capte toute URL à deux segments et renvoie
 * 404 quand l'item n'existe pas dans la carte visée.
 *
 * Deux écrans distincts : la connexion à l'API d'un côté, l'apparence de la
 * page de l'autre. Le premier se remplit une fois, le second se retouche
 * régulièrement — les mélanger obligerait à traverser la configuration
 * technique à chaque ajustement visuel.
 */

use App\Addons\Supervio\Controllers\Admin\StatusPageSettingsController;
use App\Addons\Supervio\Controllers\Admin\SupervioSettingsController;
use Illuminate\Support\Facades\Route;

// Écran 1 — connexion et adresse de la page
Route::get('/', [SupervioSettingsController::class, 'show'])->name('supervio');
Route::put('/', [SupervioSettingsController::class, 'update'])->name('supervio.update');
Route::post('/test', [SupervioSettingsController::class, 'test'])
    ->middleware('throttle:20,1')
    ->name('supervio.test');

// Écran 2 — apparence de la page de statut
Route::get('/page', [StatusPageSettingsController::class, 'show'])->name('supervio_page');
Route::put('/page', [StatusPageSettingsController::class, 'update'])->name('supervio_page.update');
