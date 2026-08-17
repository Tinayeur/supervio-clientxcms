{{-- Écran 1 — connexion à Supervio et adresse de la page publique. --}}
@extends('admin.layouts.admin')
@section('title', __('supervio::messages.admin.card.settings'))

@section('content')
@include('supervio_admin::partials.subnav', ['actif' => 'connexion'])

<form method="POST" action="{{ route('admin.settings.supervio.update') }}">
    @csrf
    @method('PUT')

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.section_api') }}</h3>
            <p class="card-subtitle">{{ __('supervio::messages.admin.section_api_help') }}</p>
        </div>

        <div class="mb-5 rounded-lg px-4 py-3 text-sm {{ $capacites['connu']
            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300'
            : 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' }}">
            @if ($capacites['connu'])
                <strong>{{ __('supervio::messages.admin.plan.detected', ['plan' => $capacites['plan']]) }}</strong>
                — {{ __('supervio::messages.admin.plan.account', ['tenant' => $capacites['tenant'] ?? '—']) }}
                — {{ __('supervio::messages.admin.plan.history', ['jours' => $capacites['jours']]) }}
            @else
                {{ $aUneCle
                    ? __('supervio::messages.admin.plan.unverified')
                    : __('supervio::messages.admin.plan.no_key') }}
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                {{--
                    Champ de clé API construit à la main plutôt que via
                    admin/shared/password, pour deux raisons.

                    1. Le partiel partagé rend un <input type="password"> sans
                       attribut autocomplete : le navigateur propose d'enregistrer
                       la clé, puis la re-remplit à chaque visite de l'écran.

                    2. Une fois la clé configurée, aucun champ n'est rendu du
                       tout. Le gestionnaire de mots de passe n'a alors rien à
                       remplir, et la clé ne peut pas être écrasée par
                       inadvertance : il faut cliquer sur « Remplacer » pour
                       faire apparaître la saisie.
                --}}
                <label class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                    {{ __('supervio::messages.admin.fields.api_key') }}
                </label>

                @if ($aUneCle)
                    <div id="supervio-cle-etat" class="mt-2 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 px-4 py-2.5 dark:border-gray-700">
                        <span class="inline-flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400">
                            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            {{ __('supervio::messages.admin.fields.api_key_set') }}
                        </span>
                        <button type="button" id="supervio-remplacer-cle"
                                class="ml-auto text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ __('supervio::messages.admin.fields.api_key_replace') }}
                        </button>
                    </div>
                @endif

                {{-- Quand une clé existe, le champ n'est PAS rendu, même masqué :
                     un gestionnaire de mots de passe remplit aussi les champs
                     cachés, et cette valeur écraserait la clé à l'enregistrement.
                     Le champ est alors créé par le script, au clic sur
                     « Remplacer ».

                     autocomplete="new-password" est la valeur que les navigateurs
                     respectent pour ne pas proposer un identifiant enregistré ;
                     « off » est ignoré sur les champs de type mot de passe. --}}
                <div id="supervio-cle-champ" class="mt-2">
                    @unless ($aUneCle)
                        <input type="password" name="supervio_api_key" id="supervio_api_key"
                               autocomplete="new-password" spellcheck="false" placeholder="sv_…"
                               class="input-text">
                    @endunless
                </div>

                <p class="mt-2 text-sm text-gray-500">
                    {{ __('supervio::messages.admin.fields.api_key_help') }}
                </p>

                @error('supervio_api_key')
                    <span class="mt-2 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
            <div>
                @include('admin/shared/input', [
                    'name' => 'supervio_api_url',
                    'label' => __('supervio::messages.admin.fields.api_url'),
                    'value' => setting('supervio_api_url', 'https://supervio.fr/api/v1'),
                    'help' => __('supervio::messages.admin.fields.api_url_help'),
                ])
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" id="supervio-test" class="btn btn-secondary">
                {{ __('supervio::messages.admin.test.button') }}
            </button>
            <span id="supervio-test-result" class="text-sm" role="status" aria-live="polite"></span>
        </div>
    </div>

    <div class="card">
        <div class="card-heading">
            <h3 class="card-title">{{ __('supervio::messages.admin.section_page') }}</h3>
            <p class="card-subtitle">{{ __('supervio::messages.admin.section_page_help') }}</p>
        </div>

        {{-- La page publique reste en 404 tant qu'aucune status page n'est
             choisie. Sans cet avertissement, l'administrateur enregistre sa clé,
             constate que /statut ne répond pas, et n'a aucun moyen de deviner
             ce qui manque. --}}
        @if (! setting('supervio_status_page_id'))
            <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                {{ __('supervio::messages.admin.page_required') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                @if (empty($pages))
                    {{-- Liste vide : soit la clé n'est pas encore enregistrée
                         (elle n'est lue qu'après sauvegarde), soit le compte n'a
                         aucune status page. Les deux cas appellent une action
                         différente, on ne les confond pas. --}}
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $aUneCle
                            ? __('supervio::messages.admin.no_pages')
                            : __('supervio::messages.admin.save_key_first') }}
                    </p>
                @else
                    @include('admin/shared/select', [
                        'name' => 'supervio_status_page_id',
                        'label' => __('supervio::messages.admin.fields.status_page'),
                        'options' => $pages,
                        'value' => setting('supervio_status_page_id'),
                    ])
                @endif
            </div>

            {{-- L'adresse ne dépend pas de l'API : elle reste modifiable même
                 quand la liste des status pages est vide. Auparavant elle était
                 enfermée dans la même condition, et restait donc inaccessible
                 lors de la toute première configuration. --}}
            <div>
                    <label for="supervio_page_slug" class="block text-sm font-medium text-gray-900 dark:text-gray-400">
                        {{ __('supervio::messages.admin.fields.slug') }}
                    </label>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="shrink-0 text-sm text-gray-400">{{ url('/') }}/</span>
                        <input type="text" name="supervio_page_slug" id="supervio_page_slug"
                               value="{{ $slug }}" pattern="[a-z0-9\-]+"
                               class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('supervio::messages.admin.fields.slug_help') }}
                    </p>

                    @if (\Route::has('client.supervio.status'))
                        <a href="{{ route('client.supervio.status') }}" target="_blank" rel="noopener"
                           class="mt-2 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                            {{ __('supervio::messages.admin.fields.view_page') }}
                        </a>
                    @endif
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">{{ __('global.save') }}</button>
</form>
@endsection

@section('scripts')
<script>
    /* Révèle la saisie de clé, et retire le bloc « clé configurée ». Tant qu'on
       n'a pas cliqué, aucun champ de mot de passe n'existe dans la page : le
       gestionnaire du navigateur n'a rien à proposer ni à remplir. */
    document.getElementById('supervio-remplacer-cle')?.addEventListener('click', function () {
        const zone = document.getElementById('supervio-cle-champ');
        if (!zone || document.getElementById('supervio_api_key')) { return; }

        /* Le champ est créé ici, pas simplement démasqué : tant qu'il n'existe
           pas dans le document, le gestionnaire de mots de passe n'a rien à
           remplir, et aucune valeur parasite ne peut partir à l'enregistrement. */
        const champ = document.createElement('input');
        champ.type = 'password';
        champ.name = 'supervio_api_key';
        champ.id = 'supervio_api_key';
        champ.autocomplete = 'new-password';
        champ.spellcheck = false;
        champ.placeholder = 'sv_…';
        champ.className = 'input-text';

        const aide = document.createElement('p');
        aide.className = 'mt-1 text-xs text-amber-600 dark:text-amber-400';
        aide.textContent = @json(__('supervio::messages.admin.fields.api_key_replace_warning'));

        zone.append(champ, aide);
        document.getElementById('supervio-cle-etat')?.remove();
        champ.focus();
    });

    document.getElementById('supervio-test')?.addEventListener('click', function () {
        const sortie = document.getElementById('supervio-test-result');
        sortie.textContent = '…';
        sortie.className = 'text-sm text-gray-500';

        fetch(@json(route('admin.settings.supervio.test')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json',
            },
            /* Le champ peut ne pas exister : quand une clé est déjà enregistrée,
               il n'est rendu qu'après un clic sur « Remplacer ». On envoie alors
               une chaîne vide, et le contrôleur teste la clé stockée. */
            body: JSON.stringify({
                supervio_api_key: document.getElementById('supervio_api_key')?.value ?? '',
                supervio_api_url: document.querySelector('[name="supervio_api_url"]')?.value ?? '',
            }),
        })
            .then(r => r.json())
            .then(d => {
                sortie.textContent = d.message;
                sortie.className = 'text-sm ' + (d.ok ? 'text-emerald-600' : 'text-rose-600');
            })
            .catch(() => {
                sortie.textContent = @json(__('supervio::messages.admin.test.injoignable'));
                sortie.className = 'text-sm text-rose-600';
            });
    });
</script>
@endsection
