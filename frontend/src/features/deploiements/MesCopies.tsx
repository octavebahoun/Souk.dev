import { useState } from "react";
import { Link } from "react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import axios from "axios";
import { ExternalLink, RotateCcw } from "lucide-react";
import { useCanalPrive } from "../../app/tempsReelPrive";
import { useTitrePage } from "../../app/titre";
import {
  AccesRefuse,
  Alerte,
  Badge,
  Bouton,
  Carte,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Modale,
  Montant,
  Onglets,
  PastilleEtat,
  Saisie,
  Champ,
  SqueletteListe,
  formaterDate,
  formaterFcfa,
  initiales,
  presentationEtatCopie,
  useToast,
} from "../../ui";
import {
  arreterCopie,
  listerCopies,
  mettreAJourCopie,
} from "../catalogue/api";
import {
  allerPayer,
  messageErreur,
  nouvelleCle,
  type Deploiement,
  type Page,
} from "../paiements/api";
import { formules } from "../paiements/formules";

type Filtre = "toutes" | "en_ligne" | "en_cours" | "a_payer" | "arretees";

const EN_COURS = [
  "en_file",
  "construction",
  "demarrage",
  "mise_a_jour",
  "arret_en_cours",
];

function dansLeFiltre(copie: Deploiement, filtre: Filtre): boolean {
  if (filtre === "toutes") return true;
  if (filtre === "en_ligne") return copie.etat === "en_ligne";
  if (filtre === "en_cours") return EN_COURS.includes(copie.etat);
  if (filtre === "a_payer")
    return copie.facture_a_payer !== null || copie.etat === "suspendu";
  return copie.etat === "arrete" || copie.etat === "echec";
}

/**
 * « Mes copies » : chaque copie, son état en direct, son adresse et ce qu'elle coûte ; une action
 * principale par copie (payer, ouvrir ou relancer), l'arrêt confirmé à part.
 */
export function MesCopies() {
  useTitrePage("Mes copies");
  const cache = useQueryClient();
  const [filtre, setFiltre] = useState<Filtre>("toutes");
  const [cible, setCible] = useState<Deploiement | null>(null);
  const copies = useQuery({
    queryKey: ["copies"],
    queryFn: listerCopies,
    // Filet si Reverb manque : une copie qui bouge est relue toutes les 10 secondes.
    refetchInterval: (requete) =>
      requete.state.data?.data.some((copie) => EN_COURS.includes(copie.etat))
        ? 10_000
        : false,
  });

  if (copies.isPending) {
    return (
      <div className="flex flex-col gap-6">
        <EnTetePage
          titre="Mes copies"
          description="Les applis qui tournent pour vous, leur adresse et leur abonnement."
        />
        <SqueletteListe nombre={3} />
      </div>
    );
  }

  if (copies.isError || !copies.data) {
    if (
      axios.isAxiosError(copies.error) &&
      copies.error.response?.status === 401
    ) {
      return (
        <AccesRefuse
          description="Connectez-vous pour retrouver vos copies."
          action={<LienBouton to="/connexion">Se connecter</LienBouton>}
        />
      );
    }
    return <EtatErreur onReessayer={() => void copies.refetch()} />;
  }

  const toutes = copies.data.data;
  const liste = toutes.filter((copie) => dansLeFiltre(copie, filtre));
  const compte = (valeur: Filtre) =>
    toutes.filter((copie) => dansLeFiltre(copie, valeur)).length;

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage
        titre="Mes copies"
        description="Les applis qui tournent pour vous, leur adresse et leur abonnement."
        actions={
          toutes.length > 0 ? (
            <LienBouton to="/store" variante="contour">
              Commander une copie
            </LienBouton>
          ) : null
        }
      />

      {toutes.length === 0 ? (
        <EtatVide
          illustration="vide-copies"
          titre="Vous n’avez pas encore de copie"
          description="Commandez la copie d’une appli du Store : elle tourne pour vous, à votre nom, sur sa propre adresse."
          action={<LienBouton to="/store">Parcourir le Store</LienBouton>}
        />
      ) : (
        <Onglets
          libelle="Filtrer les copies"
          valeur={filtre}
          onChange={setFiltre}
          onglets={[
            { valeur: "toutes", libelle: "Toutes", compteur: toutes.length },
            {
              valeur: "en_ligne",
              libelle: "En ligne",
              compteur: compte("en_ligne"),
            },
            {
              valeur: "en_cours",
              libelle: "En cours",
              compteur: compte("en_cours"),
            },
            {
              valeur: "a_payer",
              libelle: "À payer",
              compteur: compte("a_payer"),
            },
            {
              valeur: "arretees",
              libelle: "Arrêtées",
              compteur: compte("arretees"),
            },
          ]}
        >
          {liste.length === 0 ? (
            <p className="m-0 mt-4 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">
              Aucune copie dans cette liste.
            </p>
          ) : (
            <ul className="m-0 mt-4 flex list-none flex-col gap-3 p-0">
              {liste.map((copie) => (
                <li key={copie.id}>
                  <CarteCopie
                    copie={copie}
                    onArreter={() => setCible(copie)}
                    onRelire={() =>
                      void cache.invalidateQueries({ queryKey: ["copies"] })
                    }
                    onChange={(recue) =>
                      cache.setQueryData<Page<Deploiement>>(
                        ["copies"],
                        (courant) =>
                          courant
                            ? {
                                ...courant,
                                data: courant.data.map((element) =>
                                  element.id === recue.id
                                    ? { ...element, ...recue }
                                    : element,
                                ),
                              }
                            : courant,
                      )
                    }
                  />
                </li>
              ))}
            </ul>
          )}
        </Onglets>
      )}

      {cible ? (
        <ConfirmerArret copie={cible} onFermer={() => setCible(null)} />
      ) : null}
    </div>
  );
}

function CarteCopie({
  copie,
  onArreter,
  onChange,
  onRelire,
}: {
  copie: Deploiement;
  onArreter: () => void;
  onChange: (copie: Deploiement) => void;
  onRelire: () => void;
}) {
  const etat = presentationEtatCopie(copie.etat);
  const [cle] = useState(nouvelleCle);
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const actif = !["arrete", "arret_en_cours"].includes(copie.etat);
  const abonnement = copie.abonnement;
  const incident = abonnement?.statut === "incident";
  const facture = incident ? null : copie.facture_a_payer;
  const [annonce, setAnnonce] = useState("");
  const [miseAJour, setMiseAJour] = useState(false);
  const derniere = copie.mise_a_jour;
  useCanalPrive<Deploiement>(
    actif ? `deploiement.${copie.id}` : null,
    "deploiement.etat",
    (recue) => {
      onChange(recue);
      setAnnonce(
        `${recue.app_nom} : ${presentationEtatCopie(recue.etat).libelle}.`,
      );
    },
    onRelire,
  );

  async function payer() {
    if (!facture) return;
    setEnvoi(true);
    setErreur(null);
    try {
      await allerPayer(facture.id, cle);
    } catch (probleme) {
      setErreur(
        messageErreur(
          probleme,
          "Le paiement n’a pas pu s’ouvrir. Réessayez dans un instant.",
        ),
      );
      setEnvoi(false);
    }
  }

  return (
    <Carte
      as="article"
      aria-label={copie.app_nom}
      className="flex flex-col gap-4"
    >
      <p role="status" className="pour-lecteur">
        {annonce}
      </p>
      <div className="flex flex-col gap-4 tablette:flex-row tablette:items-start tablette:justify-between">
        <div className="flex min-w-0 items-start gap-3">
          <span
            aria-hidden="true"
            className="grid size-11 shrink-0 place-items-center rounded-moyen bg-surface-accent text-corps font-semibold text-sur-primaire-doux"
          >
            {initiales(copie.app_nom)}
          </span>
          <div className="flex min-w-0 flex-col gap-1">
            <div className="flex flex-wrap items-center gap-2">
              {copie.app_id ? (
                <Link
                  to={`/store/${copie.app_id}`}
                  className="text-corps-l font-medium text-titre no-underline hover:underline"
                >
                  {copie.app_nom}
                </Link>
              ) : (
                <span className="text-corps-l font-medium text-titre">
                  {copie.app_nom}
                </span>
              )}
              <PastilleEtat
                ton={etat.ton}
                libelle={etat.libelle}
                enCours={etat.enCours}
              />
              <Badge>{formules[copie.taille].nom}</Badge>
            </div>
            {copie.url &&
            ["en_ligne", "suspendu", "mise_a_jour"].includes(copie.etat) ? (
              <p className="m-0 truncate font-mono text-petit text-texte">
                {copie.url}
              </p>
            ) : null}
            <p className="m-0 text-petit text-texte-doux">
              {phraseCopie(copie)}
              {copie.version !== null ? ` Version ${copie.version}.` : ""}
            </p>
          </div>
        </div>

        <div className="flex shrink-0 flex-col gap-2 tablette:flex-row tablette:items-center">
          {facture ? (
            <Bouton chargement={envoi} onClick={() => void payer()}>
              {copie.etat === "suspendu"
                ? `Payer et réactiver · ${formaterFcfa(facture.montant_total)}`
                : `Payer ${formaterFcfa(facture.montant_total)}`}
            </Bouton>
          ) : null}
          {copie.etat === "en_ligne" && copie.url ? (
            <a
              href={copie.url}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-11 items-center justify-center gap-2 rounded-moyen border border-bordure-champ bg-surface px-4 text-corps font-medium text-titre no-underline shadow-1 transition-colors duration-150 hover:bg-surface-douce tablette:h-10"
            >
              Ouvrir
              <ExternalLink aria-hidden="true" className="size-4" />
              <span className="pour-lecteur">(nouvel onglet)</span>
            </a>
          ) : null}
          {copie.app_id &&
          !incident &&
          ["echec", "arrete"].includes(copie.etat) ? (
            <LienBouton
              to={`/store/${copie.app_id}/deployer`}
              variante="contour"
              icone={<RotateCcw aria-hidden="true" className="size-4" />}
            >
              Commander à nouveau
            </LienBouton>
          ) : null}
        </div>
      </div>

      {copie.etat === "suspendu" ? (
        <Alerte ton="danger" titre="Copie suspendue pour impayé">
          Ses données sont gardées. Elle repart dès le paiement ; sans paiement,
          elle sera arrêtée définitivement 30 jours après la suspension.
        </Alerte>
      ) : facture && copie.etat !== "attente_paiement" ? (
        <Alerte ton="alerte" titre={`Facture ${facture.numero} à payer`}>
          {formaterFcfa(facture.montant_total)}, échéance le{" "}
          {formaterDate(facture.echeance_le)}. Sans paiement, la copie est
          suspendue 7 jours après l’échéance.
        </Alerte>
      ) : null}
      {incident ? (
        <Alerte ton="danger" titre="La copie n’a pas pu démarrer">
          Votre paiement est bien enregistré. L’équipe est prévenue et revient
          vers vous ; aucune nouvelle facture ne sera émise d’ici là.
        </Alerte>
      ) : copie.etat === "echec" && copie.erreur ? (
        <Alerte ton="danger" titre="La copie n’a pas pu démarrer">
          {copie.erreur}
        </Alerte>
      ) : null}
      {copie.version_disponible && copie.etat === "en_ligne" ? (
        <Alerte
          ton="info"
          titre={`Version ${copie.version_disponible.numero} disponible`}
          action={
            <Bouton taille="sm" onClick={() => setMiseAJour(true)}>
              Mettre à jour
            </Bouton>
          }
        >
          {copie.version_disponible.notes
            ? `${copie.version_disponible.notes} `
            : ""}
          Votre copie reste sur la version {copie.version} tant que vous ne
          l’appliquez pas.
        </Alerte>
      ) : null}
      {copie.etat === "mise_a_jour" && derniere ? (
        <Alerte
          ton="info"
          titre={`Passage à la version ${derniere.vers_version} en cours`}
        >
          Les données sont sauvegardées d’abord ; la copie est indisponible
          quelques minutes. Si la nouvelle version ne démarre pas, elle revient
          d’elle-même à la version {derniere.de_version}.
        </Alerte>
      ) : null}
      {derniere?.statut === "retablie" &&
      copie.etat === "en_ligne" &&
      copie.version !== derniere.vers_version ? (
        <Alerte
          ton="alerte"
          titre={`La version ${derniere.vers_version} n’a pas été appliquée`}
        >
          {derniere.erreur ? `${derniere.erreur} ` : ""}
          Votre copie tourne sur la version {copie.version}, avec ses données.
        </Alerte>
      ) : null}
      {erreur ? (
        <Alerte ton="danger" urgente>
          {erreur}
        </Alerte>
      ) : null}
      {miseAJour && copie.version_disponible ? (
        <ConfirmerMiseAJour
          copie={copie}
          onFermer={() => setMiseAJour(false)}
          onLancee={(recue) => {
            onChange(recue);
            setMiseAJour(false);
          }}
        />
      ) : null}

      {(abonnement &&
        ["actif", "en_retard", "suspendu"].includes(abonnement.statut)) ||
      (actif && copie.etat !== "attente_paiement") ? (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-bordure pt-3">
          {abonnement &&
          ["actif", "en_retard", "suspendu"].includes(abonnement.statut) ? (
            <dl className="m-0 flex flex-wrap gap-x-8 gap-y-2 text-petit">
              <div className="flex gap-1.5">
                <dt className="text-texte-doux">Chaque mois</dt>
                <dd className="m-0 text-titre">
                  <Montant
                    valeur={
                      abonnement.montant_hebergement +
                      (abonnement.type_prix === "mensuel"
                        ? abonnement.prix_appli
                        : 0)
                    }
                  />
                </dd>
              </div>
              {abonnement.prochaine_echeance ? (
                <div className="flex gap-1.5">
                  <dt className="text-texte-doux">Prochaine facture</dt>
                  <dd className="m-0 text-titre">
                    {formaterDate(abonnement.prochaine_echeance)}
                  </dd>
                </div>
              ) : null}
            </dl>
          ) : (
            <span />
          )}
          {actif && copie.etat !== "attente_paiement" ? (
            <Bouton variante="fantome" taille="sm" onClick={onArreter}>
              Arrêter la copie
            </Bouton>
          ) : null}
        </div>
      ) : null}
    </Carte>
  );
}

function phraseCopie(copie: Deploiement): string {
  switch (copie.etat) {
    case "attente_paiement":
      return (
        "Commandée le " +
        formaterDate(copie.cree_le) +
        " · elle démarre dès le paiement."
      );
    case "en_file":
    case "construction":
    case "demarrage":
      return "Démarrage en cours : quelques minutes.";
    case "en_ligne":
      return "En ligne · commandée le " + formaterDate(copie.cree_le) + ".";
    case "suspendu":
      return "Son adresse affiche « service momentanément indisponible ».";
    case "arret_en_cours":
      return "Arrêt en cours.";
    case "arrete":
      return "Arrêtée. Ses données ont été supprimées.";
    default:
      return "Commandée le " + formaterDate(copie.cree_le) + ".";
  }
}

function ConfirmerArret({
  copie,
  onFermer,
}: {
  copie: Deploiement;
  onFermer: () => void;
}) {
  const cache = useQueryClient();
  const toast = useToast();
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  async function arreter() {
    setEnvoi(true);
    setErreur(null);
    try {
      await arreterCopie(copie.id);
      toast.info(`Arrêt de ${copie.app_nom} en cours.`);
      await cache.invalidateQueries({ queryKey: ["copies"] });
      onFermer();
    } catch (probleme) {
      setErreur(
        messageErreur(
          probleme,
          "L’arrêt n’a pas pu être demandé. Réessayez dans un instant.",
        ),
      );
      setEnvoi(false);
    }
  }

  return (
    <Modale
      ouverte
      onFermer={onFermer}
      taille="petite"
      titre={`Arrêter la copie de ${copie.app_nom} ?`}
      description="Elle sera arrêtée et ses données supprimées : cette action est définitive. L’abonnement s’arrête avec elle, plus aucune facture ne sera émise."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Garder la copie
          </Bouton>
          <Bouton
            variante="danger"
            chargement={envoi}
            onClick={() => void arreter()}
          >
            Arrêter la copie
          </Bouton>
        </>
      }
    >
      {erreur ? (
        <Alerte ton="danger" urgente>
          {erreur}
        </Alerte>
      ) : null}
    </Modale>
  );
}

/**
 * Appliquer une nouvelle version : la copie s'arrête quelques minutes, ses données sont
 * sauvegardées avant, et elle revient d'elle-même à la version actuelle si la nouvelle ne
 * démarre pas. Une version qui demande de nouvelles informations les demande ici.
 */
function ConfirmerMiseAJour({
  copie,
  onFermer,
  onLancee,
}: {
  copie: Deploiement;
  onFermer: () => void;
  onLancee: (copie: Deploiement) => void;
}) {
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const [demandees, setDemandees] = useState<
    { nom: string; libelle: string }[]
  >([]);
  const [valeurs, setValeurs] = useState<Record<string, string>>({});
  const numero = copie.version_disponible?.numero;

  async function lancer() {
    setEnvoi(true);
    setErreur(null);
    try {
      onLancee(
        await mettreAJourCopie(
          copie.id,
          demandees.length > 0 ? { variables: valeurs } : {},
        ),
      );
    } catch (probleme) {
      // Une nouvelle variable obligatoire : 422 sur « variables.NOM », avec son libellé entre guillemets.
      const champs = axios.isAxiosError(probleme)
        ? ((
            probleme.response?.data as
              | { errors?: Record<string, string[]> }
              | undefined
          )?.errors ?? {})
        : {};
      const variables = Object.entries(champs)
        .filter(([cle]) => cle.startsWith("variables."))
        .map(([cle, messages]) => ({
          nom: cle.slice("variables.".length),
          libelle: /«\s*(.+?)\s*»/.exec(messages[0] ?? "")?.[1] ?? cle,
        }));
      if (variables.length > 0) {
        setDemandees(variables);
        setErreur(
          `La version ${numero} demande ${variables.length > 1 ? "de nouvelles informations" : "une nouvelle information"}.`,
        );
      } else {
        setErreur(
          messageErreur(
            probleme,
            "La mise à jour n’a pas pu être demandée. Réessayez dans un instant.",
          ),
        );
      }
      setEnvoi(false);
    }
  }

  return (
    <Modale
      ouverte
      onFermer={onFermer}
      taille="moyenne"
      titre={`Passer ${copie.app_nom} à la version ${numero} ?`}
      description="La copie est indisponible quelques minutes. Ses données sont sauvegardées d’abord ; si la nouvelle version ne démarre pas, la copie revient d’elle-même à la version actuelle, avec ses données."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Plus tard
          </Bouton>
          <Bouton chargement={envoi} onClick={() => void lancer()}>
            Mettre à jour
          </Bouton>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        {copie.version_disponible?.notes ? (
          <p className="m-0 whitespace-pre-line text-corps text-texte">
            {copie.version_disponible.notes}
          </p>
        ) : null}
        {demandees.map((variable) => (
          <Champ key={variable.nom} libelle={variable.libelle}>
            {(liaison) => (
              <Saisie
                {...liaison}
                value={valeurs[variable.nom] ?? ""}
                onChange={(evenement) =>
                  setValeurs((actuelles) => ({
                    ...actuelles,
                    [variable.nom]: evenement.target.value,
                  }))
                }
              />
            )}
          </Champ>
        ))}
        {erreur ? (
          <Alerte ton={demandees.length > 0 ? "info" : "danger"} urgente>
            {erreur}
          </Alerte>
        ) : null}
      </div>
    </Modale>
  );
}
