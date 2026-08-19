{{--
    Corps commun aux trois templates — addon Supervio.

    Aurore, Nocturne et Signal ne diffèrent que par leur habillage. Le contenu —
    bandeau d'état, services, incidents — est identique : le dupliquer trois fois
    garantirait qu'une correction n'en atteigne qu'un seul.

    Ces templates sont des rendus propres à ClientXCMS. L'API Supervio n'expose
    aucune définition de template : les noms sont repris pour la cohérence de
    marque, il n'y a aucune synchronisation avec la page hébergée par Supervio.
--}}

@php
    $enPanne = collect($monitors)->filter(fn ($m) => $m->statut === 'down')->count();
@endphp

@if ($cssPersonnalise !== '')
    {{-- Nettoyé par ContenuLibre::css() : les sorties de contexte (fermeture de
         la balise, script, expression()) sont neutralisées à l'enregistrement
         comme au rendu. --}}
    <style>{!! $cssPersonnalise !!}</style>
@endif

@if ($erreur !== null)
    @include('supervio::partials.error', ['erreur' => $erreur])
@else
    <div class="mb-8 rounded-2xl px-6 py-5 {{ $enPanne > 0 ? 'bg-rose-50 dark:bg-rose-500/10' : 'bg-emerald-50 dark:bg-emerald-500/10' }}">
        <p class="text-lg font-semibold {{ $enPanne > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
            {{ $enPanne > 0
                ? __('supervio::messages.page.degraded', ['count' => $enPanne])
                : __('supervio::messages.page.all_operational') }}
        </p>
        <p class="mt-1 text-sm opacity-70">
            {{ __('supervio::messages.page.updated_at', ['heure' => now()->format('H:i')]) }}
        </p>
    </div>

    {{-- Un groupe sans titre est rendu sans en-tête : c'est le cas quand aucune
         catégorie n'est déclarée, et celui des services non classés. --}}
    @foreach ($groupes as $groupe)
        @if ($groupe['titre'])
            <h2 class="{{ $loop->first ? '' : 'mt-8' }} mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-neutral-400">
                {{ $groupe['titre'] }}
            </h2>
        @endif

        <div class="{{ $loop->first || $groupe['titre'] ? '' : 'mt-4' }} rounded-2xl border border-gray-200 px-6 dark:border-neutral-700">
            @foreach ($groupe['monitors'] as $monitor)
                @include('supervio::partials.monitor', [
                    'monitor' => $monitor,
                    'donnees' => $uptime[$monitor->id] ?? null,
                ])
            @endforeach
        </div>
    @endforeach

    @if ($afficherUptime)
        <p class="mt-3 text-xs text-gray-400 dark:text-neutral-500">
            {{ __('supervio::messages.page.uptime') }} — {{ __('supervio::messages.page.range.'.$range) }}
        </p>
    @endif

    {{-- Mention honnête plutôt qu'un graphe tronqué sans explication : la
         profondeur affichée est celle que l'API a bien voulu rendre. --}}
    @if ($historiqueLimite)
        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
            {{ __('supervio::messages.page.history_limited', ['jours' => $capacites['jours']]) }}
        </p>
    @endif

    @if ($afficherIncidents)
        <div class="mt-10">
            @include('supervio::partials.incidents', ['incidents' => $incidents])
        </div>
    @endif
@endif

@if ($piedMode === 'supervio')
    <p class="mt-10 text-center text-xs text-gray-400 dark:text-neutral-600">
        <a href="https://supervio.fr" target="_blank" rel="noopener" class="hover:underline">
            {{ __('supervio::messages.page.attribution') }}
        </a>
    </p>
@elseif ($piedMode === 'personnalise' && $piedTexte !== '')
    {{-- Nettoyé par ContenuLibre::pied() : liste blanche de balises, attributs
         d'événement et URL « javascript: » retirés. --}}
    <div class="mt-10 text-center text-xs text-gray-400 dark:text-neutral-600">{!! $piedTexte !!}</div>
@endif

{{-- Rechargement automatique, si l'administrateur l'a activé.

     Page entière et non appel direct à l'API : la clé Supervio ne doit jamais
     quitter le serveur. Un rechargement repasse par le contrôleur, qui dispose
     déjà de son propre cache. --}}
@if ($rafraichissement > 0)
    <script>
        setTimeout(function () { window.location.reload(); }, {{ (int) $rafraichissement }} * 1000);
    </script>
@endif
