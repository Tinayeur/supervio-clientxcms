{{-- Ligne de service — addon Supervio. $monitor : MonitorDTO. $donnees : payload uptime ou null. --}}

@php
    /* Classes écrites en toutes lettres, et dans un .blade.php : Tailwind ne
       scanne que les fichiers Blade des modules et ne résout aucune classe
       construite par concaténation. « bg-{{ $c }}-500 » ne serait jamais
       compilé et la page sortirait sans couleurs d'état.

       Ce bloc est répété dans chaque partiel qui en a besoin, volontairement :
       un @include reçoit une copie des données et les variables qu'il définit
       ne remontent pas à la vue appelante. */
    $palettePastille = [
        'emerald' => 'bg-emerald-500',
        'rose' => 'bg-rose-500',
        'amber' => 'bg-amber-500',
        'orange' => 'bg-orange-500',
        'slate' => 'bg-slate-400',
    ];

    $paletteBadge = [
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'orange' => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-400',
    ];
@endphp

<div class="flex items-center justify-between gap-4 border-b border-gray-100 py-4 last:border-0 dark:border-neutral-800">
    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 flex-none rounded-full {{ $palettePastille[$monitor->couleur()] ?? $palettePastille['slate'] }}"></span>

            {{-- Illustration choisie par l'administrateur : emoji, icône
                 Bootstrap, ou rien. L'emoji est échappé comme du texte ; seule
                 la classe d'icône est injectée, jamais du HTML libre. --}}
            @if ($monitor->iconeType === 'emoji' && $monitor->icone !== '')
                <span class="flex-none text-base leading-none" aria-hidden="true">{{ $monitor->icone }}</span>
            @elseif ($monitor->iconeType === 'icone' && $monitor->icone !== '')
                <i class="{{ preg_replace('/[^a-z0-9 \-]/i', '', $monitor->icone) }} flex-none text-gray-400 dark:text-neutral-500" aria-hidden="true"></i>
            @endif

            <p class="truncate font-medium text-gray-900 dark:text-white">{{ $monitor->nom }}</p>
        </div>

        @if ($monitor->sslJoursRestants !== null && $monitor->sslJoursRestants <= 30)
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                {{ __('supervio::messages.page.ssl_expires', ['jours' => $monitor->sslJoursRestants]) }}
            </p>
        @endif
    </div>

    <div class="flex flex-none items-center gap-4 text-right text-sm">
        @if (isset($donnees['uptime_pct']) && $donnees['uptime_pct'] !== null)
            <span class="tabular-nums text-gray-600 dark:text-neutral-300">
                {{ number_format((float) $donnees['uptime_pct'], 2) }}%
            </span>
        @endif

        @if ($monitor->latenceMs !== null)
            <span class="hidden tabular-nums text-gray-400 sm:inline dark:text-neutral-500">
                {{ number_format($monitor->latenceMs, 0) }} ms
            </span>
        @endif

        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $paletteBadge[$monitor->couleur()] ?? $paletteBadge['slate'] }}">
            {{ __($monitor->libelleStatut()) }}
        </span>
    </div>
</div>
