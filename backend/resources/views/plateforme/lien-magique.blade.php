@extends('plateforme.page', ['titre' => $etat === 'confirmer' ? 'Se connecter à Souk.dev' : 'Connexion impossible'])

@section('contenu')
    @if ($etat === 'confirmer')
        <h1>Se connecter à Souk.dev</h1>
        <p>Vous allez ouvrir la session de ce compte :</p>
        <div class="encart"><strong>{{ $adresse }}</strong></div>
        <form method="post" action="{{ $action }}">
            @csrf
            <button type="submit">Me connecter</button>
        </form>
        <hr>
        <p class="doux">Ce n’est pas votre adresse ? Ne vous connectez pas : quelqu’un vous a transmis son propre lien.</p>
    @elseif ($etat === 'suspendu')
        <p class="pastille" role="status">Compte suspendu</p>
        <h1>Ce compte est suspendu</h1>
        <p>{{ $motif }}</p>
        <p class="doux">Pour contester cette décision, répondez à l’e-mail de suspension : l’équipe le relira.</p>
    @else
        <p class="pastille" role="status">Lien expiré</p>
        <h1>Ce lien a expiré ou a déjà servi</h1>
        <p>Un lien de connexion vaut 15 minutes et ne sert qu’une fois.</p>
        <p><a href="{{ $connexion }}">Demander un nouveau lien</a></p>
    @endif
@endsection
