{{-- Template « Aurore » — addon Supervio. Voir partials/corps.blade.php pour le contenu. --}}
@extends('layouts.front')
@section('title', $titre)

@section('content')
    {{-- Aurore : clair et aéré, dégradé doux vers la couleur de la status page.
         Template par défaut, seul disponible sans abonnement payant. --}}
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6"
         @if ($couleurFond) style="background-color: {{ $couleurFond }};" @endif>
        <header class="mb-10 text-center">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="mx-auto mb-5 h-10 w-auto">
            @endif
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $titre }}</h1>
            <span class="mt-3 inline-block h-1 w-16 rounded-full" style="background-color: {{ $couleurAccent }};"></span>
        </header>

        @include('supervio::partials.corps')
    </div>
@endsection
