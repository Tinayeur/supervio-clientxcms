<?php

/*
 * Addon Supervio pour ClientXCMS — routes publiques.
 *
 * Le segment d'URL vient des réglages : il est donc lu ici, au chargement des
 * routes. SupervioSettings::slug() assainit la valeur et retombe sur « statut »
 * si elle est vide ou invalide — sans ce garde-fou, un slug fantaisiste
 * enregistré par erreur casserait le démarrage de l'application entière, pas
 * seulement cette page.
 *
 * La limitation de débit n'est pas décorative : la page interroge l'API Supervio
 * au travers du cache de l'addon. Sans garde-fou, une page publique est une
 * porte ouverte pour épuiser le quota d'API du compte.
 */

use App\Addons\Supervio\Controllers\Client\StatusPageController;
use App\Addons\Supervio\Services\SupervioSettings;
use Illuminate\Support\Facades\Route;

$slug = SupervioSettings::slug();

Route::get('/'.$slug, [StatusPageController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('client.supervio.status');

/* Convention ClientXCMS : l'adresse anglaise redirige en 301 vers la page.
   Enregistrée seulement si le slug n'est pas déjà « status », sinon la route
   se redirigerait vers elle-même en boucle. */
if ($slug !== 'status') {
    Route::get('/status', [StatusPageController::class, 'redirection'])
        ->name('client.supervio.status.legacy');
}
