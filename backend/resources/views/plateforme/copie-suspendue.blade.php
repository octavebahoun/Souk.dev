@extends('plateforme.page', ['titre' => 'Service momentanément indisponible'])

@section('contenu')
    <p class="pastille" role="status">Indisponible pour le moment</p>
    <h1>Ce service est momentanément indisponible</h1>
    <p>Il reviendra dès que son propriétaire l’aura réactivé. Ses données sont conservées.</p>
    <p class="doux">Merci de réessayer un peu plus tard.</p>
    <hr>
    <p class="doux">Vous êtes le propriétaire de ce service ? <a href="{{ $paiements }}">Réactivez-le depuis votre espace Souk.dev</a>.</p>
@endsection
