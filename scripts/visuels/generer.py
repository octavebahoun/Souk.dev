"""Génère les visuels de Souk.dev avec Nano Banana Pro (gemini-3-pro-image, Vertex AI).

Usage : python3 scripts/visuels/generer.py [nom ...]   (sans nom : tout ce qui manque)
        python3 scripts/visuels/generer.py --reoptimiser  (refait les WebP depuis bruts/, sans appel payant)

- Projet Google Cloud : celui de `gcloud config get-value project`.
- Chaque image payée est inscrite dans depenses.json ; plafond strict de 10 $ (BRIEF.md, section 8).
- Les PNG bruts vont dans bruts/ (non commités), les WebP optimisés dans le dossier cible de chaque visuel.
- Reprises sur 429, 500 et 503 (quota occupé, non facturé).
"""
import base64
import io
import json
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

from PIL import Image

ICI = Path(__file__).resolve().parent
RACINE = ICI.parent.parent
JOURNAL = ICI / "depenses.json"
BRUTS = ICI / "bruts"
PLAFOND = 10.0
PRIX = {"1K": 0.134, "2K": 0.134, "4K": 0.24}
MODELE = "gemini-3-pro-image"  # Nano Banana Pro ; la préversion « -preview » répond 404 sur ce projet
PROJET = subprocess.check_output(["gcloud", "config", "get-value", "project"], text=True).strip()
_jeton = {"t": 0.0, "v": ""}


def jeton() -> str:
    if time.time() - _jeton["t"] > 240:
        _jeton["v"] = subprocess.check_output(["gcloud", "auth", "print-access-token"], text=True).strip()
        _jeton["t"] = time.time()
    return _jeton["v"]


def journal() -> dict:
    return json.loads(JOURNAL.read_text()) if JOURNAL.exists() else {"total": 0.0, "images": []}


def generer(nom: str, prompt: str, format_: str, taille: str) -> bytes:
    prix = PRIX[taille]
    j = journal()
    if j["total"] + prix > PLAFOND:
        raise SystemExit(f"STOP : {j['total']:.2f} $ + {prix:.2f} $ dépasserait le plafond de {PLAFOND} $ ({nom}).")
    url = (f"https://aiplatform.googleapis.com/v1/projects/{PROJET}/locations/global/"
           f"publishers/google/models/{MODELE}:generateContent")
    corps = {
        "contents": [{"role": "user", "parts": [{"text": prompt}]}],
        "generationConfig": {"responseModalities": ["TEXT", "IMAGE"],
                             "imageConfig": {"aspectRatio": format_, "imageSize": taille}},
    }
    for essai in range(8):
        requete = urllib.request.Request(url, json.dumps(corps).encode(), {
            "Authorization": f"Bearer {jeton()}", "Content-Type": "application/json",
            "x-goog-user-project": PROJET})
        try:
            reponse = json.load(urllib.request.urlopen(requete, timeout=300))
            break
        except urllib.error.HTTPError as e:
            message = e.read().decode()[:600]
            if e.code == 401:
                _jeton["t"] = 0.0
            if e.code not in (401, 429, 500, 503) or essai == 7:
                raise RuntimeError(f"HTTP {e.code} ({nom}) : {message}")
            time.sleep(15 * (essai + 1))
    parties = reponse.get("candidates", [{}])[0].get("content", {}).get("parts", [])
    image = next((p["inlineData"] for p in parties if "inlineData" in p), None)
    if image is None:
        raise RuntimeError(f"pas d'image ({nom}) : {json.dumps(reponse)[:400]}")
    j = journal()
    j["total"] = round(j["total"] + prix, 4)
    j["images"].append({"nom": nom, "taille": taille, "usd": prix, "le": time.strftime("%Y-%m-%d %H:%M")})
    JOURNAL.write_text(json.dumps(j, indent=1, ensure_ascii=False) + "\n")
    return base64.b64decode(image["data"])


def couleur_vers_transparence(image: Image.Image) -> Image.Image:
    """Rend transparent le fond uni (couleur du coin), anti-crénelage compris (méthode « color to alpha »)."""
    fond = image.getpixel((4, 4))[:3]
    pixels = image.convert("RGBA").load()
    largeur, hauteur = image.size
    sortie = Image.new("RGBA", image.size)
    destination = sortie.load()
    for y in range(hauteur):
        for x in range(largeur):
            r, g, b, _ = pixels[x, y]
            alpha = 0.0
            for canal, fc in zip((r, g, b), fond):
                if canal > fc:
                    alpha = max(alpha, (canal - fc) / (255 - fc) if fc < 255 else 0)
                elif canal < fc:
                    alpha = max(alpha, (fc - canal) / fc if fc > 0 else 0)
            if alpha < 0.04:
                destination[x, y] = (0, 0, 0, 0)
                continue
            couleur = tuple(round(min(255, max(0, (c - fc) / alpha + fc))) for c, fc in zip((r, g, b), fond))
            destination[x, y] = (*couleur, round(alpha * 255))
    return sortie


def optimiser(brut: bytes, cible: Path, largeur: int, recadrage: float = 1.0, transparent: bool = False) -> None:
    image = Image.open(io.BytesIO(brut)).convert("RGB")
    if recadrage < 1.0:
        l, h = image.size
        nl, nh = round(l * recadrage), round(h * recadrage)
        image = image.crop(((l - nl) // 2, (h - nh) // 2, (l - nl) // 2 + nl, (h - nh) // 2 + nh))
    if image.width > largeur:
        image = image.resize((largeur, round(image.height * largeur / image.width)), Image.LANCZOS)
    if transparent:
        image = couleur_vers_transparence(image)
    cible.parent.mkdir(parents=True, exist_ok=True)
    image.save(cible, "WEBP", quality=80, alpha_quality=80, method=6)


def main() -> None:
    visuels = json.loads((ICI / "visuels.json").read_text())
    style = visuels["styles"]
    if sys.argv[1:] == ["--reoptimiser"]:
        for v in visuels["images"]:
            brut = BRUTS / f"{v['nom']}.png"
            if brut.exists():
                optimiser(brut.read_bytes(), RACINE / v["cible"], v["largeur"], v.get("recadrage", 1.0), v.get("transparent", False))
                print(f"  refait {v['cible']}", flush=True)
        return
    demandes = set(sys.argv[1:])
    BRUTS.mkdir(exist_ok=True)
    for v in visuels["images"]:
        cible = RACINE / v["cible"]
        if demandes and v["nom"] not in demandes:
            continue
        if not demandes and cible.exists():
            continue
        prompt = v["prompt"] + " " + style.get(v.get("style", ""), "")
        print(f"→ {v['nom']} ({v['format']}, {v['taille']})", flush=True)
        brut = generer(v["nom"], prompt.strip(), v["format"], v["taille"])
        (BRUTS / f"{v['nom']}.png").write_bytes(brut)
        optimiser(brut, cible, v["largeur"], v.get("recadrage", 1.0), v.get("transparent", False))
        print(f"  ok {cible.relative_to(RACINE)} — total {journal()['total']:.2f} $", flush=True)


if __name__ == "__main__":
    main()
