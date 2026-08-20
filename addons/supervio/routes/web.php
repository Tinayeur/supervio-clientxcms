<?php

/*
 * Addon Supervio pour ClientXCMS — routes publiques.
 *
 * Le segment d'URL est une constante (SupervioSettings::SLUG). Il l'a été après
 * coup : quand il venait des réglages, une valeur fantaisiste enregistrée par
 * erreur cassait le démarrage de l'application entière, ces routes étant
 * chargées à chaque requête et non seulement pour cette page.
 *
 * La limitation de débit n'est pas décorative : la page interroge l'API Supervio
 * au travers du cache de l'addon. Sans garde-fou, une page publique est une
 * porte ouverte pour épuiser le quota d'API du compte.
 */

use App\Addons\Supervio\Controllers\Client\StatusPageController;
use App\Addons\Supervio\Services\SupervioSettings;
use Illuminate\Support\Facades\Route;

Route::get('/'.SupervioSettings::SLUG, [StatusPageController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('client.supervio.status');

/* Convention ClientXCMS : l'adresse anglaise redirige en 301 vers la page. */
Route::get('/status', [StatusPageController::class, 'redirection'])
    ->name('client.supervio.status.legacy');
