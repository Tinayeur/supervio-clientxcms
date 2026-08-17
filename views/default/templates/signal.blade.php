{{-- Template « Signal » — addon Supervio. Voir partials/corps.blade.php pour le contenu. --}}
@extends('layouts.front')
@section('title', $titre)

@section('content')
    {{-- Signal : dense, orienté supervision. Bandeau d'accent pleine largeur. --}}
    <div class="w-full px-6 py-3 text-center text-sm font-semibold text-white"
         style="background-color: {{ $couleurAccent }};">
        {{ $titre }}
    </div>

    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6"
         @if ($couleurFond) style="background-color: {{ $couleurFond }};" @endif>
        @if ($logo)<img src="{{ $logo }}" alt="" class="mb-8 h-8 w-auto">@endif

        @include('supervio::partials.corps')
    </div>
@endsection
