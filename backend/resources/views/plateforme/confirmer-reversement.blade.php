@extends('plateforme.page', ['titre' => 'Confirmer votre numéro de reversement'])

@section('contenu')
    <h1>Confirmer votre numéro de reversement</h1>
    <p>Vos revenus Souk.dev seront versés sur ce numéro Mobile Money :</p>
    <div class="encart">
        <strong>{{ $reseau }}</strong> · {{ $coordonnees->numero_masque }}
    </div>
    <form method="post" action="{{ url('/reversement/confirmer/'.$jeton) }}">
        @csrf
        <button type="submit">Confirmer ce numéro</button>
    </form>
    <hr>
    <p class="doux">Ce n’est pas vous qui avez fait ce changement ? Ne confirmez pas : le numéro reste inutilisable, et changez votre accès GitHub.</p>
@endsection
