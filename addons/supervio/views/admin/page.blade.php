{{--
    Écran 2 — apparence et contenu de la page de statut.

    Les options réservées aux abonnements payants sont grisées avec un badge
    plutôt que masquées : un administrateur doit comprendre ce qu'il gagnerait à
    changer d'abonnement, pas se demander où sont passées les options.

    Ce grisage n'est qu'une aide à la saisie : le contrôleur ignore de toute
    façon ces champs quand l'abonnement ne les autorise pas.
--}}
@extends('admin.layouts.admin')
@section('title', __('supervio::messages.admin.card.page'))

@section('content')
@include('supervio_admin::partials.subnav', ['actif' => 'page'])

<form method="POST" action="{{ route('admin.settings.supervio_page.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="card">
        <div class="card-heading flex items-start justify-between gap-4">
            <div>
                <h3 class="card-title">{{ __('supervio::messages.admin.page.section_identity') }}</h3>
                <p class="card-subtitle">{{ __('supervio::messages.admin.page.section_identity_help') }}</p>
            </div>
            @if (\Route::has('client.supervio.status'))
                <a href="{{ route('client.supervio.status') }}" target="_blank" rel="noopener" class="btn btn-secondary flex-none">
                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                    {{ __('supervio::messages.admin.fields.view_page') }}
                </a>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                @include('admin/shared/input', [
                    'name' => 'supervio_page_title',
                    'label' => __('supervio::messages.admin.fields.page_title'),
                    'value' => setting('supervio_page_title'),
                    'help' => __('supervio::messages.admin.fields.page_title_help'),
                ])
            </div>
            <div>
                <label for="logo_fichier" class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.logo_file') }}
                </label>

                @if (\App\Addons\Supervio\Services\SupervioSettings::logo())
                    <div class="mt-1 flex items-center gap-3">
                        <img src="{{ \App\Addons\Supervio\Services\SupervioSettings::logo() }}" alt="" class="h-10 w-auto rounded border border-gray-200 dark:border-gray-700">
                        <label class="flex items-center gap-2 text-sm text-rose-600 dark:text-rose-400">
                            <input type="checkbox" name="supprimer_logo" value="1" class="rounded border-gray-300 dark:border-gray-600">
                            {{ __('supervio::messages.admin.fields.logo_remove') }}
                        </label>
                    </div>
                @endif

                <input type="file" name="logo_fichier" id="logo_fichier" accept="image/png,image/jpeg,image/webp"
                       class="mt-2 w-full text-sm text-gray-600 dark:text-gray-400">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.logo_file_help') }}
                </p>

                @include('admin/shared/input', [
                    'name' => 'supervio_logo_url',
                    'label' => __('supervio::messages.admin.fields.logo'),
                    'value' => setting('supervio_logo_url'),
                    'help' => __('supervio::messages.admin.fields.logo_help'),
                ])
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.page.section_look') }}</h3>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="supervio_template" class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.template') }}
                    @unless ($options['templates']) @include('supervio_admin::partials.badge-pro') @endunless
                </label>
                <select name="supervio_template" id="supervio_template" @disabled(! $options['templates'])
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @foreach (\App\Addons\Supervio\Services\PlanGate::TEMPLATES as $t)
                        <option value="{{ $t }}" @selected(setting('supervio_template', 'aurore') === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="supervio_accent_color" class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.accent') }}
                </label>
                <input type="color" name="supervio_accent_color" id="supervio_accent_color"
                       value="{{ setting('supervio_accent_color') ?: '#2563eb' }}"
                       class="mt-1 h-10 w-full rounded-lg border-gray-300 dark:border-gray-600">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.accent_help') }}
                </p>
            </div>

            <div>
                <label for="supervio_background_color" class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.background') }}
                    @unless ($options['couleur_fond']) @include('supervio_admin::partials.badge-pro') @endunless
                </label>
                <input type="color" name="supervio_background_color" id="supervio_background_color"
                       value="{{ setting('supervio_background_color') ?: '#ffffff' }}"
                       @disabled(! $options['couleur_fond'])
                       class="mt-1 h-10 w-full rounded-lg border-gray-300 disabled:opacity-50 dark:border-gray-600">
            </div>
        </div>

        <label class="mt-4 flex items-center gap-3 {{ $options['dark_mode'] ? '' : 'opacity-50' }}">
            <input type="checkbox" name="supervio_dark_mode" value="1"
                   @checked(setting('supervio_dark_mode', false)) @disabled(! $options['dark_mode'])
                   class="rounded border-gray-300 text-indigo-600 dark:border-gray-600">
            <span class="text-sm text-gray-900 dark:text-gray-300">{{ __('supervio::messages.admin.fields.dark_mode') }}</span>
            @unless ($options['dark_mode']) @include('supervio_admin::partials.badge-pro') @endunless
        </label>
    </div>

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.page.section_content') }}</h3>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                @include('admin/shared/select', [
                    'name' => 'supervio_history_range',
                    'label' => __('supervio::messages.admin.fields.history'),
                    'options' => collect($options['ranges'])->mapWithKeys(fn ($r) => [$r => __('supervio::messages.page.range.'.$r)])->toArray(),
                    'value' => setting('supervio_history_range', '24h'),
                    'help' => __('supervio::messages.admin.fields.history_help', ['jours' => $capacites['jours']]),
                ])
            </div>
            <div>
                @include('admin/shared/input', [
                    'name' => 'supervio_refresh_seconds',
                    'type' => 'number',
                    'label' => __('supervio::messages.admin.fields.refresh'),
                    'value' => setting('supervio_refresh_seconds', 0),
                    'help' => __('supervio::messages.admin.fields.refresh_help'),
                ])
            </div>
            <div>
                @include('admin/shared/input', [
                    'name' => 'supervio_cache_ttl',
                    'type' => 'number',
                    'label' => __('supervio::messages.admin.fields.cache_ttl'),
                    'value' => setting('supervio_cache_ttl', 180),
                    'help' => __('supervio::messages.admin.fields.cache_ttl_help'),
                ])
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="supervio_show_uptime" value="1"
                       @checked(setting('supervio_show_uptime', true))
                       class="rounded border-gray-300 text-indigo-600 dark:border-gray-600">
                <span class="text-sm text-gray-900 dark:text-gray-300">{{ __('supervio::messages.admin.fields.show_uptime') }}</span>
            </label>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="supervio_show_incidents" value="1"
                       @checked(setting('supervio_show_incidents', true))
                       class="rounded border-gray-300 text-indigo-600 dark:border-gray-600">
                <span class="text-sm text-gray-900 dark:text-gray-300">{{ __('supervio::messages.admin.fields.show_incidents') }}</span>
            </label>

            <label class="flex items-center gap-3 {{ $options['masquer_marque'] ? '' : 'opacity-50' }}">
                <input type="checkbox" name="supervio_hide_branding" value="1"
                       @checked(setting('supervio_hide_branding', false)) @disabled(! $options['masquer_marque'])
                       class="rounded border-gray-300 text-indigo-600 dark:border-gray-600">
                <span class="text-sm text-gray-900 dark:text-gray-300">{{ __('supervio::messages.admin.fields.hide_branding') }}</span>
                @unless ($options['masquer_marque']) @include('supervio_admin::partials.badge-pro') @endunless
            </label>
        </div>
    </div>


    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.page.section_categories') }}</h3>
            <p class="card-subtitle">{{ __('supervio::messages.admin.page.section_categories_help') }}</p>
        </div>

        <div id="supervio-categories" class="space-y-2">
            @foreach ($categories as $i => $categorie)
                <div class="flex items-center gap-2">
                    <input type="hidden" name="categories[{{ $i }}][id]" value="{{ $categorie['id'] }}">
                    <input type="text" name="categories[{{ $i }}][nom]" value="{{ $categorie['nom'] }}" maxlength="60"
                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <button type="button" class="supervio-retirer text-rose-600 hover:underline dark:text-rose-400" title="{{ __('supervio::messages.admin.page.remove_category') }}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </div>
            @endforeach
        </div>

        <button type="button" id="supervio-ajouter-categorie" class="btn btn-secondary mt-3">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('supervio::messages.admin.page.add_category') }}
        </button>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ __('supervio::messages.admin.page.categories_note') }}
        </p>
    </div>

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.page.section_probes') }}</h3>
            <p class="card-subtitle">{{ __('supervio::messages.admin.page.section_probes_help') }}</p>
        </div>

        @if (empty($sondes))
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('supervio::messages.admin.page.no_probes') }}</p>
        @else
            <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            @foreach (['show', 'name', 'icon_type', 'icon', 'category'] as $col)
                                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-gray-800 dark:text-gray-200">
                                    {{ __('supervio::messages.admin.page.columns.'.$col) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($sondes as $sonde)
                            <tr class="bg-white dark:bg-slate-900">
                                <td class="px-4 py-2">
                                    <input type="checkbox" name="visibles[]" value="{{ $sonde['id'] }}"
                                           @checked($sonde['reglages']['visible'])
                                           class="rounded border-gray-300 text-indigo-600 dark:border-gray-600">
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $sonde['nom'] }}</td>
                                <td class="px-4 py-2">
                                    <select name="sondes[{{ $sonde['id'] }}][icone_type]"
                                            class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                        @foreach (\App\Addons\Supervio\Services\SupervioSettings::ICONES as $type)
                                            <option value="{{ $type }}" @selected($sonde['reglages']['icone_type'] === $type)>
                                                {{ __('supervio::messages.admin.page.icon_types.'.$type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" name="sondes[{{ $sonde['id'] }}][icone]" maxlength="40"
                                           value="{{ $sonde['reglages']['icone'] }}"
                                           placeholder="{{ __('supervio::messages.admin.page.icon_placeholder') }}"
                                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                </td>
                                <td class="px-4 py-2">
                                    <select name="sondes[{{ $sonde['id'] }}][categorie]"
                                            class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                        <option value="">{{ __('supervio::messages.admin.page.no_category') }}</option>
                                        @foreach ($categories as $categorie)
                                            <option value="{{ $categorie['id'] }}" @selected($sonde['reglages']['categorie'] === $categorie['id'])>
                                                {{ $categorie['nom'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.page.section_footer') }}</h3>
        </div>

        <div class="space-y-2">
            @foreach (\App\Addons\Supervio\Services\SupervioSettings::PIEDS as $mode)
                @php($verrouille = $mode !== 'supervio' && ! $options['pied'])
                <label class="flex items-center gap-3 {{ $verrouille ? 'opacity-50' : '' }}">
                    <input type="radio" name="supervio_footer_mode" value="{{ $mode }}"
                           @checked(setting('supervio_footer_mode', 'supervio') === $mode) @disabled($verrouille)
                           class="border-gray-300 text-indigo-600 dark:border-gray-600">
                    <span class="text-sm text-gray-900 dark:text-gray-300">
                        {{ __('supervio::messages.admin.page.footer_modes.'.$mode) }}
                    </span>
                    @if ($verrouille) @include('supervio_admin::partials.badge-pro') @endif
                </label>
            @endforeach
        </div>

        <div class="mt-4">
            <label for="supervio_footer_text" class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                {{ __('supervio::messages.admin.page.footer_text') }}
            </label>
            <textarea name="supervio_footer_text" id="supervio_footer_text" rows="3" maxlength="2000"
                      @disabled(! $options['pied'])
                      class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-white">{{ setting('supervio_footer_text') }}</textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('supervio::messages.admin.page.footer_text_help') }}
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">
                {{ __('supervio::messages.admin.page.section_css') }}
                @unless ($options['css']) @include('supervio_admin::partials.badge-pro') @endunless
            </h3>
            <p class="card-subtitle">{{ __('supervio::messages.admin.page.section_css_help') }}</p>
        </div>

        <textarea name="supervio_custom_css" rows="10" maxlength="20000"
                  @disabled(! $options['css'])
                  class="w-full rounded-lg border-gray-300 font-mono text-xs disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                  placeholder="/* .supervio-page { } */">{{ setting('supervio_custom_css') }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary">{{ __('global.save') }}</button>
</form>
@endsection

@section('scripts')
<script>
    /* Les catégories sont ajoutées côté navigateur : leur identifiant est laissé
       vide, le serveur en génère un à l'enregistrement. Une catégorie existante
       conserve le sien, sinon les sondes qui la référencent seraient détachées. */
    (function () {
        const liste = document.getElementById('supervio-categories');
        if (!liste) { return; }

        let index = liste.children.length;

        document.getElementById('supervio-ajouter-categorie')?.addEventListener('click', function () {
            const ligne = document.createElement('div');
            ligne.className = 'flex items-center gap-2';
            ligne.innerHTML =
                '<input type="hidden" name="categories[' + index + '][id]" value="">' +
                '<input type="text" name="categories[' + index + '][nom]" maxlength="60" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">' +
                '<button type="button" class="supervio-retirer text-rose-600 hover:underline dark:text-rose-400"><i class="bi bi-trash"></i></button>';
            liste.appendChild(ligne);
            index++;
        });

        liste.addEventListener('click', function (e) {
            const bouton = e.target.closest('.supervio-retirer');
            if (bouton) { bouton.parentElement.remove(); }
        });
    })();
</script>
@endsection
