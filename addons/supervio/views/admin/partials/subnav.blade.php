{{--
    Bascule entre les deux écrans de l'addon.

    Avec exactement deux écrans frères, une bascule directe évite de repasser par
    la liste des cartes de réglages à chaque aller-retour.

    $actif : 'connexion' ou 'page'.
--}}

@php
    $ongletsSupervio = [
        'connexion' => ['route' => 'admin.settings.supervio', 'icone' => 'bi bi-key',
                        'libelle' => __('supervio::messages.admin.card.settings')],
        'page' => ['route' => 'admin.settings.supervio_page', 'icone' => 'bi bi-palette',
                   'libelle' => __('supervio::messages.admin.card.page')],
    ];
@endphp

<nav class="mb-5 flex gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800" aria-label="{{ __('supervio::messages.title') }}">
    @foreach ($ongletsSupervio as $cle => $onglet)
        <a href="{{ route($onglet['route']) }}"
           @if ($cle === $actif) aria-current="page" @endif
           class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition
                  {{ $cle === $actif
                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-900 dark:text-white'
                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
            <i class="{{ $onglet['icone'] }}" aria-hidden="true"></i>
            {{ $onglet['libelle'] }}
        </a>
    @endforeach
</nav>
