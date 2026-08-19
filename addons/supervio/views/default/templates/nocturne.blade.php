{{-- Template « Nocturne » — addon Supervio. Voir partials/corps.blade.php pour le contenu. --}}
@extends('layouts.front')
@section('title', $titre)

@section('content')
    {{-- Nocturne : panneau sombre encadré, contrastes élevés. Le fond sombre est
         porté par le conteneur et non par <body>, pour que l'en-tête du site
         reste accordé. --}}
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <div class="rounded-3xl px-6 py-10 text-neutral-200 sm:px-10"
             style="background-color: {{ $couleurFond ?? '#0b0f14' }};">
            <header class="mb-10 flex items-center gap-4 border-b border-white/10 pb-6">
                @if ($logo)<img src="{{ $logo }}" alt="" class="h-9 w-auto">@endif
                <h1 class="text-2xl font-semibold tracking-tight text-white">{{ $titre }}</h1>
                <span class="ml-auto h-2.5 w-2.5 rounded-full" style="background-color: {{ $couleurAccent }};"></span>
            </header>

            @include('supervio::partials.corps')
        </div>
    </div>
@endsection
