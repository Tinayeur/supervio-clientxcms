{{-- Liste des incidents — addon Supervio. $incidents : IncidentDTO[]. --}}

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

<h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-neutral-400">
    {{ __('supervio::messages.page.incidents') }}
</h2>

@if (empty($incidents))
    <p class="text-sm text-gray-500 dark:text-neutral-400">
        {{ __('supervio::messages.page.no_incidents') }}
    </p>
@else
    <ul class="space-y-3">
        @foreach ($incidents as $incident)
            <li class="rounded-xl border border-gray-200 p-4 dark:border-neutral-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $incident->monitor }}</p>
                        @if ($incident->cause)
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-neutral-400">{{ $incident->cause }}</p>
                        @endif
                    </div>

                    <span class="flex-none rounded-full px-2.5 py-0.5 text-xs font-medium {{ $paletteBadge[$incident->couleur()] ?? $paletteBadge['slate'] }}">
                        {{ $incident->estResolu()
                            ? __('supervio::messages.page.resolved_in', ['duree' => $incident->duree()])
                            : __('supervio::messages.page.ongoing') }}
                    </span>
                </div>

                @if ($incident->debut)
                    <p class="mt-2 text-xs text-gray-400 dark:text-neutral-500">
                        {{ $incident->debut->translatedFormat('d/m/Y H:i') }}
                    </p>
                @endif
            </li>
        @endforeach
    </ul>
@endif
