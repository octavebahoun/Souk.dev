<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Paiement simulé — Souk.dev</title>
    <style>
        :root { --primaire: #1265DD; --nuit: #160C51; --texte: #364153; --doux: #5F6B7E; --bordure: #E3E9F3; --fond: #F7FAFF; --succes: #067647; --danger: #B42318; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: linear-gradient(160deg, var(--nuit), #1A2C8F 60%, var(--primaire)); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--texte); }
        main { width: 100%; max-width: 420px; background: #fff; border-radius: 12px; padding: 28px 24px; box-shadow: 0 28px 56px -16px rgb(13 7 51 / .5); }
        .bandeau { margin: -28px -24px 20px; padding: 10px 24px; border-radius: 12px 12px 0 0; background: #FFFAEB; color: #B54708; font-size: 13px; font-weight: 600; }
        h1 { margin: 0 0 4px; font-size: 20px; color: #1F1F1F; }
        p { margin: 0; line-height: 1.5; }
        .doux { color: var(--doux); font-size: 14px; }
        .montant { margin: 20px 0; padding: 16px; border: 1px solid var(--bordure); border-radius: 8px; background: var(--fond); }
        .ligne { display: flex; justify-content: space-between; font-size: 14px; padding: 4px 0; }
        .total { font-weight: 700; font-size: 18px; color: #1F1F1F; border-top: 1px solid var(--bordure); margin-top: 8px; padding-top: 10px; }
        label { display: block; font-size: 13px; font-weight: 600; margin: 16px 0 6px; color: #1F1F1F; }
        select { width: 100%; height: 44px; border: 1px solid #8290A6; border-radius: 8px; padding: 0 12px; font-size: 15px; background: #fff; }
        button { width: 100%; height: 48px; border: 0; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 12px; }
        .payer { background: var(--primaire); color: #fff; }
        .refuser { background: #fff; color: var(--danger); border: 1px solid #FECDCA; }
        .attendre { background: #fff; color: var(--texte); border: 1px solid #8290A6; }
        button:focus-visible, select:focus-visible, a:focus-visible { outline: 3px solid #0C60F7; outline-offset: 2px; }
        .resultat { margin: 16px 0 0; padding: 12px 14px; border-radius: 8px; font-size: 14px; }
        .echec { background: #FEF3F2; color: var(--danger); border: 1px solid #FECDCA; }
        .attente { background: #EFF8FF; color: #175CD3; border: 1px solid #B2DDFF; }
        a { color: var(--primaire); }
    </style>
</head>
<body>
<main>
    <div class="bandeau" role="note">Simulateur SasPay — aucun argent réel n’est débité.</div>
    <h1>Payer {{ number_format($simulation->montant + $simulation->frais, 0, ',', ' ') }} F</h1>
    <p class="doux">{{ $simulation->metadata['description'] ?? 'Souk.dev' }}</p>

    <div class="montant">
        <div class="ligne"><span>Montant</span><span>{{ number_format($simulation->montant, 0, ',', ' ') }} F</span></div>
        <div class="ligne"><span>Frais de paiement (3,5 %)</span><span>{{ number_format($simulation->frais, 0, ',', ' ') }} F</span></div>
        <div class="ligne total"><span>À payer</span><span>{{ number_format($simulation->montant + $simulation->frais, 0, ',', ' ') }} F</span></div>
    </div>

    @if ($issue === 'echec')
        <p class="resultat echec" role="alert">Paiement refusé par le réseau. Vous pouvez réessayer.</p>
    @elseif ($issue === 'attente')
        <p class="resultat attente" role="status">Paiement en attente de validation sur le téléphone.</p>
    @endif

    <form method="post" action="{{ url('/simulateur/saspay/'.$simulation->id) }}">
        @csrf
        <label for="reseau">Réseau Mobile Money</label>
        <select id="reseau" name="reseau">
            @foreach ($reseaux as $reseau)
                <option value="{{ $reseau['code'] }}">{{ $reseau['nom'] }}</option>
            @endforeach
        </select>
        <button class="payer" type="submit" name="issue" value="succes">Payer (succès simulé)</button>
        <button class="refuser" type="submit" name="issue" value="echec">Refuser (échec simulé)</button>
        <button class="attendre" type="submit" name="issue" value="attente">Laisser en attente</button>
    </form>

    @if ($issue !== null && $simulation->return_url)
        <p class="doux" style="margin-top:16px"><a href="{{ $simulation->return_url }}">Revenir sur Souk.dev</a></p>
    @endif
</main>
</body>
</html>
