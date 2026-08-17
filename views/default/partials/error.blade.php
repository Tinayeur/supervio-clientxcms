{{--
    Écran d'erreur de la page de statut — addon Supervio.

    Une page de statut qui plante est une contradiction : c'est précisément
    l'outil qu'on consulte quand quelque chose ne va pas. On rend donc toujours
    une page valide, avec un message compréhensible et aucun détail technique.
--}}

@php
    $cle = $erreur === 'aucun_monitor' ? 'no_monitor' : 'api_unreachable';
@endphp

<div class="mx-auto max-w-lg rounded-2xl border border-gray-200 bg-white p-8 text-center dark:border-neutral-700 dark:bg-neutral-900">
    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
        <i class="bi bi-exclamation-triangle text-xl" aria-hidden="true"></i>
    </span>

    <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
        {{ __('supervio::messages.errors.'.$cle) }}
    </h2>

    <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400">
        {{ __('supervio::messages.errors.'.$cle.'_help') }}
    </p>
</div>
