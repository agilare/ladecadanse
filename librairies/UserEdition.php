<?php

namespace Ladecadanse;

use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\PostedFields;
use Ladecadanse\Utils\Validateur;
use PDO;

/**
 * Le compte lui-même (user/edit.php, #289) : adresse email pour tous, et pour le superadmin nom
 * d'utilisateur, groupe et statut.
 * Le mot de passe se change dans user/pwd-edit.php
 * Le profil d'organisateur d'événements dans ContributorEdition ; les comptes se créent par user/register.php
 *
 * Ce que le compte ne peut pas changer garde sa valeur en base quoi que porte le POST : la
 * validation et l'enregistrement ne voient que ce qui doit être écrit.
 */
class UserEdition extends Edition
{
    private DbConnectorPdo $pdo;

    private int $idPersonne = 0;

    private bool $isSuperadmin = false;

    /**
     * Le compte tel qu'en BD : la référence de ce qui change; d
     * des valeurs héritées hors barème (groupe 10, statut « demande ») n'échappent à la modification qu'à condition d'être déjà en BD
     *
     * @var array{idPersonne: int, pseudo: string, email: string, groupe: int, statut: string}
     */
    private array $stored = ['idPersonne' => 0, 'pseudo' => '', 'email' => '', 'groupe' => 0, 'statut' => ''];

    /**
     * Les instances arrivent en paramètre pour que la classe soit exerçable hors requête HTTP ;
     * les valeurs par défaut évitent d'imposer un conteneur à la page.
     */
    public function __construct(
        ?DbConnectorPdo $pdo = null,
        private readonly Validateur $verif = new Validateur(),
    )
    {
        // Dans l'ordre du formulaire ; ce sont aussi les seules colonnes que l'UPDATE écrit
        parent::__construct([
            'pseudo' => '',
            'email' => '',
            'groupe' => 0,
            'statut' => '',
        ], []);

        // Le connecteur est un singleton, qu'un défaut de paramètre ne sait pas appeler
        $this->pdo = $pdo ?? DbConnectorPdo::getInstance();
        $this->action = 'update';
    }

    /** Le superadmin change aussi nom, groupe et statut ; les autres, leur seule adresse. */
    public function setSuperadmin(bool $isSuperadmin): void
    {
        $this->isSuperadmin = $isSuperadmin;
    }

    /**
     * @return bool false si le compte n'existe pas — à la page de répondre 404. Sans ce
     *              contrôle, l'UPDATE ne touchait aucune ligne en annonçant une réussite.
     */
    #[\Override]
    public function loadValues(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT idPersonne, pseudo, email, groupe, statut FROM personne WHERE idPersonne = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false)
        {
            return false;
        }

        $this->idPersonne = $id;
        $this->stored = [
            'idPersonne' => $id,
            'pseudo' => (string) $row['pseudo'],
            'email' => (string) $row['email'],
            'groupe' => (int) $row['groupe'],
            'statut' => (string) $row['statut'],
        ];

        $this->valeurs = array_intersect_key($this->stored, $this->valeurs);

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
        /*
         * L'adresse email pour tous ; nom, groupe et statut pour le superadmin seul. Un champ absent ou
         * forgé en tableau garde sa valeur en base — c'est le cas d'une valeur héritée hors de la
         * liste, rendue cochée mais désactivée, que le navigateur ne poste pas.
         */
        $modifiables = $this->isSuperadmin ? ['email', 'pseudo', 'groupe', 'statut'] : ['email'];
        $this->valeurs = array_merge($this->valeurs, PostedFields::scalars($postGlobal, $modifiables));

        if (!$this->validate())
        {
            return false;
        }

        return $this->upsert();
    }

    #[\Override]
    public function validate(): bool
    {
        // Longueurs et obligation viennent de Personne::FIELDS, dont le formulaire tire aussi ses
        // maxlength. Le pseudo est validé seulement s'il change : un pseudo ancien hors des règles bloquerait
        // sinon l'enregistrement de l'adresse.
        foreach (['pseudo', 'email'] as $field)
        {
            if ($field === 'pseudo' && !$this->pseudoChanges())
            {
                continue;
            }

            $rule = Personne::FIELDS[$field];
            $this->verif->valider($this->valeurs[$field], $field, $rule['type'], $rule['min'], $rule['max'], $rule['required']);
        }

        // Seule la valeur déjà en base (ancien compte) échappe à la liste des choix permis
        if (!in_array((string) $this->valeurs['groupe'], array_map('strval', array_merge(array_values(UserLevel::getConstants()), [$this->stored['groupe']])), true))
        {
            $this->verif->setErreur("groupe", "Choisissez un groupe parmi ceux proposés");
        }

        if (!in_array($this->valeurs['statut'], array_merge(Personne::$statuts, [$this->stored['statut']]), true))
        {
            $this->verif->setErreur("statut", "Choisissez un statut parmi ceux proposés");
        }

        /*
         * Nom d'utilisateur changé par le superadmin : contrôlé seulement s'il change. Des comptes
         * anciens portent un nom partagé ou en forme d'adresse, et les refuser tels quels les
         * empêcherait d'enregistrer quoi que ce soit.
         */
        if ($this->pseudoChanges() && $this->valeurs['pseudo'] !== '')
        {
            // vide, il est partagé par tous les comptes qui n'en ont pas : seul un nom renseigné est unique
            if (Personne::pseudoExists((string) $this->valeurs['pseudo'], $this->idPersonne))
            {
                // messages rendus tels quels par getHtmlErreur() et getErreur() : la saisie s'y échappe
                $this->verif->setErreur("pseudoIdentique", "Un membre " . sanitizeForHtml($this->valeurs['pseudo']) . " existe déjà dans la base.");
            }

            /*
             * Un nom d'utilisateur en forme d'adresse rendrait ambiguë la saisie de connexion :
             * Sentry::checkLogin() cherche alors « pseudo OU email », et deux comptes peuvent
             * répondre — dont un sans pseudo, qui n'a plus alors aucun moyen de se connecter.
             */
            if (filter_var((string) $this->valeurs['pseudo'], FILTER_VALIDATE_EMAIL) !== false)
            {
                $this->verif->setErreur("pseudo", "Le nom d'utilisateur ne peut pas être une adresse e-mail.");
            }
        }

        /*
         * Unicité de l'adresse, seulement si elle change : un compte dont l'adresse est déjà
         * partagée — il en reste des centaines en production — ne pourrait sinon plus rien
         * enregistrer.
         */
        if (mb_strtolower(trim((string) $this->valeurs['email'])) !== mb_strtolower($this->stored['email'])
            && Personne::emailExists((string) $this->valeurs['email'], $this->idPersonne))
        {
            $this->verif->setErreur("emailIdentique", "Cette adresse e-mail est déjà celle d'un autre compte.");
        }

        return $this->verif->nbErreurs() === 0;
    }

    #[\Override]
    public function upsert(): bool
    {
        $stmt = $this->pdo->prepare("UPDATE personne
            SET statut = :statut, pseudo = :pseudo, email = :email, groupe = :groupe, date_derniere_modif = :modif
            WHERE idPersonne = :idP");
        $stmt->execute([
            'statut' => (string) $this->valeurs['statut'],
            'pseudo' => (string) $this->valeurs['pseudo'],
            'email' => (string) $this->valeurs['email'],
            'groupe' => (int) $this->valeurs['groupe'],
            'modif' => date("Y-m-d H:i:s"),
            'idP' => $this->idPersonne,
        ]);

        return true;
    }

    private function pseudoChanges(): bool
    {
        return (string) $this->valeurs['pseudo'] !== $this->stored['pseudo'];
    }

    /**
     * Choix du groupe, en boutons radio : les niveaux, plus celui du compte s'il n'en fait pas
     * partie (le groupe 10 hérité), coché et désactivé, à sa place dans l'ordre des niveaux.
     *
     * @return array<int, array{label: string, description: string, locked: bool}>
     */
    public function getGroupChoices(): array
    {
        $choices = [];

        foreach (UserLevel::getConstants() as $nom => $id)
        {
            $choices[(int) $id] = ['label' => $id . ' : ' . $nom, 'description' => UserLevel::getDescription((int) $id), 'locked' => false];
        }

        if (!isset($choices[$this->stored['groupe']]))
        {
            // hors barème, il a les droits du premier niveau qu'il atteint : MEMBER pour le groupe 10
            $choices[$this->stored['groupe']] = ['label' => $this->stored['groupe'] . ' : ancien niveau', 'description' => "Ne s'attribue plus", 'locked' => true];
            ksort($choices);
        }

        return $choices;
    }

    /**
     * Choix du statut : les statuts permis, plus celui du compte s'il n'en fait pas partie (le
     * statut « demande » hérité), rendu coché et désactivé.
     *
     * @return list<string>
     */
    public function getStatusChoices(): array
    {
        $choices = Personne::$statuts;

        if (!in_array($this->stored['statut'], $choices, true))
        {
            $choices[] = $this->stored['statut'];
        }

        return $choices;
    }

    public function getIdPersonne(): int
    {
        return $this->idPersonne;
    }

    /** @return array{idPersonne: int, pseudo: string, email: string, groupe: int, statut: string} */
    public function getStored(): array
    {
        return $this->stored;
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

    /** @return string|false le message brut, sans l'enveloppe de getHtmlErreur() */
    public function getErreur(string $field): string|false
    {
        return $this->verif->getErreur($field);
    }

    public function setErreur(string $field, string $message): void
    {
        $this->verif->setErreur($field, $message);
    }
}
