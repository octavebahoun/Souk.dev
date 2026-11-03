-- Chaque utilisateur (connecté par code) ne voit et n'écrit que ses notes.
CREATE TABLE notes (
    id bigserial PRIMARY KEY,
    utilisateur uuid NOT NULL DEFAULT soukdev.utilisateur_id(),
    texte text NOT NULL CHECK (char_length(texte) BETWEEN 1 AND 500),
    cree_le timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE notes ENABLE ROW LEVEL SECURITY;

CREATE POLICY notes_lecture ON notes FOR SELECT TO soukdev_utilisateur
    USING (utilisateur = soukdev.utilisateur_id());

CREATE POLICY notes_ecriture ON notes FOR INSERT TO soukdev_utilisateur
    WITH CHECK (utilisateur = soukdev.utilisateur_id());
