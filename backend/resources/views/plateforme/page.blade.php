<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $titre }} — Souk.dev</title>
    <style>
        :root {
            --primaire: #1265DD; --primaire-survol: #0F56BE; --lien: #0C60F7; --nuit: #160C51;
            --titre: #1F1F1F; --texte: #364153; --doux: #5F6B7E; --bordure: #E3E9F3; --fond: #F7FAFF;
            --surface: #FFFFFF; --sur-couleur: #FFFFFF; --avertissement-fond: #FFFAEB; --avertissement: #B54708;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --primaire: #3B82F6; --primaire-survol: #2563EB; --lien: #8DB8FF; --titre: #F4F7FB; --texte: #D3DCE8;
                --doux: #A3B0C2; --bordure: #2A3550; --fond: #0B1020; --surface: #121A2E; --sur-couleur: #FFFFFF;
                --avertissement-fond: #3A2A0B; --avertissement: #FEC84B;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--fond); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--texte); }
        main { width: 100%; max-width: 480px; background: var(--surface); border: 1px solid var(--bordure); border-radius: 12px; padding: 32px 28px; box-shadow: 0 12px 32px -12px rgb(13 7 51 / .18); }
        .marque { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; font-weight: 700; color: var(--titre); font-size: 17px; text-decoration: none; }
        .marque span { color: var(--primaire); }
        .pastille { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 14px; padding: 4px 10px; border-radius: 999px; background: var(--avertissement-fond); color: var(--avertissement); font-size: 13px; font-weight: 600; }
        h1 { margin: 0 0 10px; font-size: 22px; line-height: 1.3; color: var(--titre); }
        p { margin: 0 0 12px; line-height: 1.6; }
        .doux { color: var(--doux); font-size: 14px; }
        .encart { margin: 20px 0; padding: 14px 16px; border: 1px solid var(--bordure); border-radius: 8px; background: var(--fond); }
        .encart strong { color: var(--titre); }
        button, .bouton { display: inline-flex; align-items: center; justify-content: center; width: 100%; min-height: 48px; border: 0; border-radius: 8px; font: inherit; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; background: var(--primaire); color: var(--sur-couleur); }
        button:hover, .bouton:hover { background: var(--primaire-survol); }
        a { color: var(--lien); }
        button:focus-visible, a:focus-visible { outline: 3px solid var(--lien); outline-offset: 2px; }
        hr { border: 0; border-top: 1px solid var(--bordure); margin: 24px 0 16px; }
    </style>
</head>
<body>
<main>
    <a class="marque" href="{{ $accueil }}">Souk<span>.dev</span></a>
    @yield('contenu')
</main>
</body>
</html>
