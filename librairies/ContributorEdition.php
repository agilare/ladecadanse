<?php

namespace Ladecadanse;

use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\PostedFields;
use Ladecadanse\Utils\Validateur;
use PDO;
use Throwable;

/**
 * Profil d'organisateur d'événements d'un compte (user/contributor-edit.php, #288) : ses
 * rattachements à un lieu et à des organisateurs, la signature de ses événements, et les valeurs
 * par défaut du formulaire d'ajout.
 *
 * Les rattachements donnent le droit de modifier les fiches et leurs événements : seul qui en a
 * le droit (setCanEditAffiliations()) les change, de même que le texte libre de l'affiliation.
 * Pour les autres, la page les rend désactivés, le navigateur ne les poste pas, et la classe
 * garde ce que dit la base quoi que porte le POST.
 */
class ContributorEdition extends Edition
{
    private DbConnectorPdo $pdo;

    private int $idPersonne = 0;

    private bool $canEditAffiliations = false;

    /**
     * Le compte tel qu'en base : ce que le formulaire n'expose pas (pseudo, groupe, réglages
     * d'un autre domaine), et la référence de ce qu'il expose.
     *
     * @var array<string, mixed>
     */
    private array $stored = [];

    /** @var array{lieu: int, organisateurs: list<int>} rattachements avant l'enregistrement */
    private array $affiliationsBefore = ['lieu' => 0, 'organisateurs' => []];

    /** @var array{lieu: int, organisateurs: list<int>} rattachements après l'enregistrement */
    private array $affiliationsAfter = ['lieu' => 0, 'organisateurs' => []];

    /** Nombre de lieux postés : la liste en accepte plusieurs, la table un seul. */
    private int $postedLieux = 0;

    /**
     * Valeurs par défaut du formulaire d'ajout d'événement, normalisées par UserSettings :
     * tenues hors de $valeurs, dont l'hydratation recopie le POST.
     *
     * @var array<string, mixed>
     */
    private array $eventDefaults;

    /**
     * Les instances arrivent en paramètre pour que la classe soit exerçable hors requête HTTP ;
     * les valeurs par défaut évitent d'imposer un conteneur à la page.
     */
    public function __construct(
        ?DbConnectorPdo $pdo = null,
        private readonly Validateur $verif = new Validateur(),
    )
    {
        parent::__construct([
            'lieu' => 0,
            'organisateurs' => [],
            'affiliation' => '',
            'signature' => 'aucune',
            'avec_affiliation' => 'non',
        ], []);

        // Le connecteur est un singleton, qu'un défaut de paramètre ne sait pas appeler
        $this->pdo = $pdo ?? DbConnectorPdo::getInstance();
        $this->eventDefaults = UserSettings::eventNewDefaults(null);
        $this->action = 'update';
    }

    public function setCanEditAffiliations(bool $canEditAffiliations): void
    {
        $this->canEditAffiliations = $canEditAffiliations;
    }

    public function canEditAffiliations(): bool
    {
        return $this->canEditAffiliations;
    }

    /**
     * @return bool false si le compte n'existe pas — à la page de répondre 404
     */
    #[\Override]
    public function loadValues(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT idPersonne, pseudo, email, groupe, affiliation, signature, avec_affiliation, settings
            FROM personne WHERE idPersonne = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false)
        {
            return false;
        }

        $this->idPersonne = $id;
        $this->stored = $row;
        $this->affiliationsBefore = $this->readAffiliations();

        $this->valeurs['affiliation'] = (string) $row['affiliation'];
        $this->valeurs['signature'] = (string) $row['signature'];
        $this->valeurs['avec_affiliation'] = (string) $row['avec_affiliation'];
        $this->valeurs['lieu'] = $this->affiliationsBefore['lieu'];
        $this->valeurs['organisateurs'] = $this->affiliationsBefore['organisateurs'];
        $this->eventDefaults = UserSettings::eventNewDefaults(is_string($row['settings']) ? $row['settings'] : null);

        return true;
    }

    /**
     * Traite le formulaire soumis, après loadValues().
     *
     * @param array<string, mixed> $postGlobal contenu de $_POST
     * @param array<string, mixed> $filesGlobal contenu de $_FILES ; le formulaire n'a pas de champ fichier
     */
    #[\Override]
    public function processSubmission(array $postGlobal, array $filesGlobal): bool
    {
        $this->readPostedFields($postGlobal);

        if (!$this->validate())
        {
            return false;
        }

        return $this->upsert();
    }

    /**
     * @param array<string, mixed> $postGlobal
     */
    private function readPostedFields(array $postGlobal): void
    {
        /*
         * Signature : deux cases à cocher, dont une décochée ne poste rien. La case du nom
         * d'utilisateur n'est rendue qu'aux comptes qui en ont un, ce que dit le témoin
         * signature_shown ; sans elle, la signature en base reste telle quelle. Les valeurs
         * anciennes « prenom » et « nomcomplet », sans effet, deviennent « aucune ».
         */
        $this->valeurs['avec_affiliation'] = !empty($postGlobal['avec_affiliation']) ? 'oui' : 'non';

        if (!empty($postGlobal['signature_shown']))
        {
            $this->valeurs['signature'] = !empty($postGlobal['signature']) ? 'pseudo' : 'aucune';
        }

        /*
         * Rattachements : une seule liste pour les lieux et les organisateurs (#102), dont
         * chaque valeur porte son type (Personne::parseAffiliationOption()). Sans le droit de
         * les changer, rien n'est lu : la base fait foi.
         */
        if ($this->canEditAffiliations)
        {
            $this->valeurs['lieu'] = 0;
            $this->valeurs['organisateurs'] = [];

            foreach ((array) ($postGlobal['affiliations'] ?? []) as $valeur)
            {
                $option = Personne::parseAffiliationOption($valeur);

                if ($option === null)
                {
                    continue;
                }

                if ($option['type'] === 'lieu')
                {
                    $this->postedLieux++;
                    $this->valeurs['lieu'] = $option['id'];
                }
                else
                {
                    $this->valeurs['organisateurs'][] = $option['id'];
                }
            }

            $this->valeurs['affiliation'] = PostedFields::scalars($postGlobal, ['affiliation'])['affiliation'] ?? $this->valeurs['affiliation'];
        }

        $this->eventDefaults = UserSettings::sanitizeEventNewDefaults(saisie: [
            'genre' => $postGlobal['ev_defaults_genre'] ?? '',
            'horaire_debut' => $postGlobal['ev_defaults_horaire_debut'] ?? '',
            'horaire_fin' => $postGlobal['ev_defaults_horaire_fin'] ?? '',
            'idLieu' => $postGlobal['ev_defaults_idLieu'] ?? 0,
            'idOrganisateurs' => $postGlobal['ev_defaults_organisateurs'] ?? [],
            'prix' => $postGlobal['ev_defaults_prix'] ?? '',
        ], genresAutorises: EventCategory::selectable(EventCategory::isEnabled()));

        // Les selects et les <input type="time"> n'offrent que des valeurs valides : sanitize
        // ramène silencieusement à vide ce qui ne l'est pas. Les horaires font exception et sont
        // signalés, parce qu'un navigateur sans support de type="time" laisse saisir n'importe quoi.
        foreach (['horaire_debut' => 'de début', 'horaire_fin' => 'de fin'] as $cle => $libelle)
        {
            $saisi = isset($postGlobal['ev_defaults_' . $cle]) && is_string($postGlobal['ev_defaults_' . $cle]) ? trim($postGlobal['ev_defaults_' . $cle]) : '';

            if (!UserSettings::estHoraireValide($saisi))
            {
                $this->verif->setErreur('ev_defaults_' . $cle, "L'horaire " . $libelle . " par défaut doit être au format hh:mm");
            }
        }
    }

    #[\Override]
    public function validate(): bool
    {
        $rule = Personne::FIELDS['affiliation'];
        $this->verif->valider($this->valeurs['affiliation'], 'affiliation', $rule['type'], $rule['min'], $rule['max'], $rule['required']);

        /*
         * Un seul lieu, autant d'organisateurs qu'on veut : la table `affiliation` ne porte
         * qu'une ligne « lieu » par personne, et $_SESSION['Saffiliation_lieu'] qu'un seul
         * identifiant — le second choisi écraserait le premier sans rien dire.
         */
        if ($this->postedLieux > 1)
        {
            $this->verif->setErreur("affiliations", "Vous ne pouvez rattacher le compte qu'à un seul lieu ; les organisateurs, eux, peuvent être plusieurs.");
        }

        return $this->verif->nbErreurs() === 0;
    }

    /**
     * Toutes les écritures ou aucune : l'effacement des organisateurs suivi d'une insertion
     * en échec laissait le compte sans aucun rattachement.
     */
    #[\Override]
    public function upsert(): bool
    {
        $pdo = $this->pdo->getPDO();
        $pdo->beginTransaction();

        try
        {
            // Partir des réglages en base préserve ceux d'un autre domaine, que ce formulaire n'expose pas
            $stmt = $this->pdo->prepare("UPDATE personne
                SET affiliation = :affiliation, signature = :signature, avec_affiliation = :avec_affiliation,
                    settings = :settings, date_derniere_modif = :modif
                WHERE idPersonne = :idP");
            $stmt->execute([
                'affiliation' => (string) $this->valeurs['affiliation'],
                'signature' => (string) $this->valeurs['signature'],
                'avec_affiliation' => (string) $this->valeurs['avec_affiliation'],
                'settings' => UserSettings::withEventNewDefaults(
                    is_string($this->stored['settings']) ? $this->stored['settings'] : null,
                    $this->eventDefaults
                ),
                'modif' => date("Y-m-d H:i:s"),
                'idP' => $this->idPersonne,
            ]);

            if ($this->canEditAffiliations)
            {
                $this->writeAffiliations();
            }

            $pdo->commit();
        }
        catch (Throwable $e)
        {
            $pdo->rollBack();
            throw $e;
        }

        $this->affiliationsAfter = $this->readAffiliations();
        $this->message = "Le profil d'organisateur d'événements a été modifié";

        return true;
    }

    private function writeAffiliations(): void
    {
        $lieu = (int) $this->valeurs['lieu'];

        if ($lieu > 0)
        {
            $stmt = $this->pdo->prepare($this->affiliationsBefore['lieu'] > 0
                ? "UPDATE affiliation SET idAffiliation = :lieu WHERE idPersonne = :idP AND genre = 'lieu'"
                : "INSERT INTO affiliation (idPersonne, idAffiliation, genre) VALUES (:idP, :lieu, 'lieu')");
            $stmt->execute(['lieu' => $lieu, 'idP' => $this->idPersonne]);
        }
        elseif ($this->affiliationsBefore['lieu'] > 0)
        {
            $stmt = $this->pdo->prepare("DELETE FROM affiliation WHERE idPersonne = ? AND genre = 'lieu'");
            $stmt->execute([$this->idPersonne]);
        }

        // le select a été rendu et posté : tout désélectionner ne poste rien, et l'effacement suffit
        $stmt = $this->pdo->prepare("DELETE FROM personne_organisateur WHERE idPersonne = ?");
        $stmt->execute([$this->idPersonne]);

        $stmt = $this->pdo->prepare("INSERT INTO personne_organisateur (idPersonne, idOrganisateur) VALUES (?, ?)");

        foreach ($this->valeurs['organisateurs'] as $idOrg)
        {
            $stmt->execute([$this->idPersonne, (int) $idOrg]);
        }
    }

    /**
     * Rattachements en base : le lieu (table affiliation) et les organisateurs.
     *
     * @return array{lieu: int, organisateurs: list<int>}
     */
    private function readAffiliations(): array
    {
        $stmt = $this->pdo->prepare("SELECT idAffiliation FROM affiliation WHERE idPersonne = ? AND genre = 'lieu'");
        $stmt->execute([$this->idPersonne]);
        $lieu = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT idOrganisateur FROM personne_organisateur WHERE idPersonne = ? ORDER BY idOrganisateur");
        $stmt->execute([$this->idPersonne]);

        return ['lieu' => $lieu, 'organisateurs' => array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))];
    }

    /**
     * Les rattachements ont-ils changé à l'enregistrement ? C'est cette différence, et non la
     * seule case « Prévenir », qui décide d'un e-mail d'information.
     */
    public function affiliationsChanged(): bool
    {
        return $this->affiliationsAfter !== $this->affiliationsBefore;
    }

    /**
     * Noms des rattachements après l'enregistrement, pour l'e-mail d'information.
     *
     * @return list<string>
     */
    public function getAffiliationNames(): array
    {
        $noms = [];

        if ($this->affiliationsAfter['lieu'] > 0)
        {
            $stmt = $this->pdo->prepare("SELECT nom FROM lieu WHERE idLieu = ?");
            $stmt->execute([$this->affiliationsAfter['lieu']]);
            $nom = $stmt->fetchColumn();

            if ($nom !== false)
            {
                $noms[] = (string) $nom;
            }
        }

        if ($this->affiliationsAfter['organisateurs'] !== [])
        {
            [$clause, $params] = $this->pdo->buildInClause('idOrganisateur', $this->affiliationsAfter['organisateurs']);
            $stmt = $this->pdo->prepare("SELECT nom FROM organisateur WHERE $clause ORDER BY nom");
            $stmt->execute($params);
            $noms = array_merge($noms, array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }

        return $noms;
    }

    public function getIdPersonne(): int
    {
        return $this->idPersonne;
    }

    /** @return array<string, mixed> le compte tel qu'en base */
    public function getStored(): array
    {
        return $this->stored;
    }

    /** @return array<string, mixed> */
    public function getEventDefaults(): array
    {
        return $this->eventDefaults;
    }

    public function hasErrors(): bool
    {
        return $this->verif->nbErreurs() > 0;
    }

    public function getErrorCount(): int
    {
        return $this->verif->nbErreurs();
    }

    public function getHtmlErreur(string $field): string
    {
        return $this->verif->getHtmlErreur($field);
    }

    public function setErreur(string $field, string $message): void
    {
        $this->verif->setErreur($field, $message);
    }
}
