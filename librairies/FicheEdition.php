<?php

namespace Ladecadanse;

use Ladecadanse\Security\CurrentUserEditing;
use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\Validateur;
use PDO;

/**
 * Socle commun aux formulaires d'ajout et de modification d'une fiche — un lieu
 * (LieuEdition), un organisateur (OrganisateurEdition).
 *
 * Les deux fiches se ressemblent assez pour que tout ce qui suit ait été écrit deux
 * fois, avec les mêmes défauts recopiés de l'une à l'autre : la relecture de l'état
 * enregistré, le garde-fou sur le statut, le cycle de vie des images, la délégation des
 * erreurs au Validateur. Ce qui les sépare — leurs colonnes, leur validation, leur SQL
 * d'écriture — reste dans les classes filles.
 *
 * Ordre des membres : propriétés, constructeur, contrat des classes filles, le cycle de
 * vie du formulaire (charger, traiter, vérifier, enregistrer), les accesseurs que la vue
 * appelle, puis les méthodes internes.
 */
abstract class FicheEdition extends Edition
{
    use HandlesImageUploads;

    /** Statut d'une fiche qui vient d'être créée. */
    protected const string INITIAL_STATUS = 'actif';

    protected DbConnectorPdo $pdo;

    /** Répertoire système des images de cette entité (app/config.php). */
    protected string $uploadsDir;

    /** Identifiant de la fiche en cours d'édition ; 0 tant qu'elle n'est pas enregistrée. */
    protected int $recordId = 0;

    /**
     * Champs image dont la case « Supprimer » a été cochée. Nommé ainsi parce que le
     * `$supprimer` d'Edition laissait croire qu'on supprimait la fiche elle-même.
     *
     * @var list<string>
     */
    protected array $imagesMarkedForDeletion = [];

    /**
     * Ce que la base dit déjà de la fiche, par opposition à $valeurs, qui porte la
     * saisie en cours. Sert à trois choses, d'où le nom générique :
     *
     * - savoir quelle image remplacer ou effacer du disque ;
     * - retrouver le nom à afficher quand un envoi rejeté a laissé la saisie à moitié
     *   faite ;
     * - rendre leur valeur aux champs que le niveau courant n'a pas le droit de modifier
     *   (statusToWrite(), fillEditorsFieldsValuesIfNotAllowed()) — c'est la seule source
     *   admise pour eux, un POST forgé n'ayant pas à décider.
     *
     * Les colonnes à y relire sont déclarées par storedColumns(). Les noms de fichiers y
     * figurent au même titre que les autres ; c'est dans $fichiers, hérité d'Edition, que
     * vivent les entrées de $_FILES — des métadonnées d'envoi, pas des valeurs de colonne.
     *
     * @var array<string, string>
     */
    protected array $storedValues = [];

    /**
     * Qui remplit le formulaire, et ce que son niveau l'autorise à y changer.
     *
     * Sans droits tant que la page n'a rien dit : un formulaire ne doit pas être plus
     * permissif faute d'avoir été renseigné. C'est la seule source admise pour les champs
     * que le POST n'a pas le droit de décider — voir statusToWrite() et
     * fillEditorsFieldsValuesIfNotAllowed().
     */
    protected CurrentUserEditing $currentUser;

    /**
     * @param array<string, mixed> $initialValues champs du formulaire, avec leur valeur initiale
     * @param array<string, array<string, mixed>> $fichiers champs de type fichier
     * @param string $uploadsDir répertoire système des images de l'entité
     */
    public function __construct(
        array $initialValues,
        array $fichiers,
        string $uploadsDir,
        ?DbConnectorPdo $pdo = null,
        protected readonly Validateur $verif = new Validateur(),
    )
    {
        $initialValues['statut'] = static::INITIAL_STATUS;

        parent::__construct($initialValues, $fichiers);

        // Le connecteur est un singleton, qu'un défaut de paramètre ne sait pas appeler
        $this->pdo = $pdo ?? DbConnectorPdo::getInstance();
        $this->uploadsDir = $uploadsDir;
        $this->currentUser = CurrentUserEditing::withoutRights();

        $this->storedValues = array_fill_keys($this->storedColumns(), '');
        $this->storedValues['statut'] = static::INITIAL_STATUS;
    }

    /*
     * Le contrat des classes filles : de quoi bâtir les requêtes génériques ci-dessous.
     * Toutes ces valeurs sont des littéraux écrits dans le code, jamais des saisies —
     * c'est ce qui autorise leur interpolation dans le SQL.
     */

    /** Table de l'entité. */
    abstract protected function table(): string;

    /** Colonne portant la clé primaire (idLieu, idOrganisateur). */
    abstract protected function idColumn(): string;

    /**
     * Colonnes relues pour connaître l'état enregistré de la fiche : au minimum `nom`,
     * `statut` et les champs image.
     *
     * @return list<string>
     */
    abstract protected function storedColumns(): array;

    /**
     * Champs image du formulaire, avec les dimensions de leur miniature « s_ ».
     *
     * @return array<string, array{maxWidth: int, maxHeight: int, fitOn: string, crop: int}>
     */
    abstract protected function imageFields(): array;

    /** Sous-répertoire d'uploads au sens d'ImageDriver2 ("lieux", "organisateurs"). */
    abstract protected function uploadsSubdir(): string;

    /**
     * Valeur à écrire dans une colonne image quand la fiche n'en porte pas.
     *
     * La chaîne vide par défaut, faute de mieux : `organisateur.logo` et `photo` sont
     * NOT NULL. Une table dont les colonnes image acceptent NULL — c'est le cas de `lieu`
     * depuis la 3.13.0 — redéfinit ceci, sans quoi elle porterait deux écritures pour la
     * même absence : NULL sur les lignes migrées, '' sur celles dont on retire l'image.
     */
    protected function absentImageValue(): ?string
    {
        return '';
    }

    /**
     * Remplit le formulaire avec la fiche à modifier — donc écrase toute saisie en cours.
     * À n'appeler qu'au premier affichage ; à la soumission, c'est refreshStoredValues().
     *
     * @return bool false si elle n'existe pas — à charge de l'appelant de répondre 404
     *              plutôt que d'afficher un formulaire vide sous un titre sans nom
     */
    #[\Override]
    public function loadValues(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM " . $this->table() . " WHERE " . $this->idColumn() . " = :id");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false)
        {
            return false;
        }

        foreach (array_keys($this->valeurs) as $field)
        {
            if (array_key_exists($field, $row))
            {
                $this->valeurs[$field] = $row[$field];
            }
        }

        $this->fillStoredValues($row);
        $this->recordId = $id;

        // L'auteur de la fiche n'est pas relu : seul un ajout en écrit un, et une
        // modification ne le déplace pas vers celui qui la fait — c'est de lui que dépend
        // son droit de modifier la fiche.
        $this->afterLoad($row);

        return true;
    }

    /**
     * @param array<string, mixed> $postGlobal contenu de $_POST
     * @param array<string, mixed> $filesGlobal contenu de $_FILES
     */
    #[\Override]
    public function processSubmission(array $postGlobal, array $filesGlobal): bool
    {
        $this->readPostedFields($postGlobal);
        $this->readPostedFiles($filesGlobal);
        $this->readPostedDeletions($postGlobal);

        /*
         * Relire la base avant d'écrire sert deux fois : les deux valeurs que le POST
         * n'a pas le droit de décider — l'image déjà en place et le statut — se prennent
         * ici (statusToWrite(), fillEditorsFieldsValuesIfNotAllowed()), et un faux dit
         * que la fiche a disparu entre l'affichage du formulaire et son envoi.
         */
        if ($this->action === 'update' && !$this->readStoredValues())
        {
            return false;
        }

        $this->valeurs['statut'] = $this->statusToWrite();
        $this->fillEditorsFieldsValuesIfNotAllowed();

        if (!$this->validate())
        {
            return false;
        }

        return $this->upsert();
    }

    /** Insère ou met à jour, selon l'intention que la page a passée à setAction(). */
    #[\Override]
    public function upsert(): bool
    {
        return match ($this->action) {
            'insert' => $this->insert(),
            'update' => $this->update(),
            default => false,
        };
    }

    abstract protected function insert(): bool;

    abstract protected function update(): bool;

    public function setRecordId(int $id): void
    {
        $this->recordId = $id;
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    /**
     * Relit les seules colonnes de $storedValues, sans toucher à la saisie en cours, et
     * dit du même coup si la fiche existe encore.
     *
     * C'est ce que la page appelle à la soumission, là où loadValues() écraserait ce que
     * l'utilisateur vient de taper. Les deux lisent la même ligne, mais l'une remplit le
     * formulaire et l'autre l'état de référence contre lequel il sera écrit.
     */
    public function refreshStoredValues(): bool
    {
        return $this->readStoredValues();
    }

    /**
     * Qui remplit le formulaire. À appeler avant processSubmission() : sans cela le
     * formulaire refuse tout ce qui demande un droit, et inscrirait 0 comme auteur.
     */
    public function setCurrentUser(CurrentUserEditing $currentUser): void
    {
        $this->currentUser = $currentUser;
    }

    public function getCurrentUser(): CurrentUserEditing
    {
        return $this->currentUser;
    }

    /**
     * Nom du fichier image enregistré en base, pour l'aperçu du formulaire.
     */
    public function getStoredImageName(string $field): string
    {
        return $this->storedValues[$field] ?? '';
    }

    /**
     * La case « Supprimer » de ce champ image a-t-elle été cochée ?
     */
    public function isImageMarkedForDeletion(string $field): bool
    {
        return in_array($field, $this->imagesMarkedForDeletion, true);
    }

    /**
     * Nom tel qu'il est enregistré, pour le titre de la page : celui du
     * formulaire est la saisie en cours, qu'un envoi rejeté laisse à moitié faite.
     */
    public function getStoredName(): string
    {
        return $this->storedValues['nom'] ?? '';
    }

    /*
     * Les trois méthodes qui suivent ne font que passer la question au Validateur, qui
     * porte seul la validation et ses messages. Elles restent ici parce que la vue parle
     * au formulaire et non à ses rouages : lui faire appeler getValidateur()->… la
     * coupleraient à un objet dont elle n'a que faire.
     */

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

    /**
     * Champs supplémentaires à relire au chargement d'une fiche : ceux qui ne sont pas
     * une colonne de la table (catégories à éclater, entités liées).
     *
     * @param array<string, mixed> $row
     */
    protected function afterLoad(array $row): void
    {
    }

    /**
     * Rend leur valeur enregistrée aux champs que le niveau courant n'a pas le droit de
     * modifier, sur le modèle de statusToWrite().
     *
     * Un formulaire qui n'affiche pas un champ le repostait en champ caché : la valeur
     * arrivait donc du client, et rien n'empêchait de la forger.
     */
    protected function fillEditorsFieldsValuesIfNotAllowed(): void
    {
    }

    /**
     * @param array<string, mixed> $postGlobal
     */
    protected function readPostedFields(array $postGlobal): void
    {
        foreach (array_keys($this->valeurs) as $field)
        {
            // is_scalar() écarte un « nom[]=x » forgé, qui déclencherait sinon
            // une conversion de tableau en chaîne
            if (isset($postGlobal[$field]) && is_scalar($postGlobal[$field]))
            {
                $this->valeurs[$field] = trim((string) $postGlobal[$field]);
            }
        }
    }

    /**
     * @param array<string, mixed> $filesGlobal
     */
    protected function readPostedFiles(array $filesGlobal): void
    {
        foreach (array_keys($this->imageFields()) as $field)
        {
            $this->fichiers[$field] = $filesGlobal[$field] ?? ['name' => '', 'tmp_name' => '', 'size' => 0];
        }
    }

    /**
     * @param array<string, mixed> $postGlobal
     */
    protected function readPostedDeletions(array $postGlobal): void
    {
        $this->imagesMarkedForDeletion = (isset($postGlobal['supprimer']) && is_array($postGlobal['supprimer'])) ? $postGlobal['supprimer'] : [];
    }

    /**
     * The value posted if the user has the authorization, otherwise the existing value in DB, otherwise default
     */
    protected function statusToWrite(): string
    {
        if ($this->currentUser->canChangeStatus)
        {
            return (string) $this->valeurs['statut'];
        }

        return $this->action === 'update' ? $this->storedValues['statut'] : static::INITIAL_STATUS;
    }

    /**
     * Relit ce que la base dit de la fiche, sans toucher à la saisie en cours.
     *
     * @return bool false si la fiche n'existe pas — un UPDATE sur un identifiant
     *              inconnu ne touche aucune ligne et réussit en silence
     */
    protected function readStoredValues(): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT " . implode(', ', $this->storedColumns())
            . " FROM " . $this->table()
            . " WHERE " . $this->idColumn() . " = :id"
        );
        $stmt->execute([':id' => $this->recordId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false)
        {
            return false;
        }

        $this->fillStoredValues($row);

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function fillStoredValues(array $row): void
    {
        foreach (array_keys($this->storedValues) as $column)
        {
            $this->storedValues[$column] = (string) ($row[$column] ?? '');
        }
    }

    /**
     * Nomme, écrit et enregistre les images.
     *
     * Le nom d'un fichier encode l'identifiant de la fiche, qui à l'ajout n'est connu
     * qu'une fois l'INSERT fait : d'où ce second passage en base plutôt qu'un nom deviné
     * avant coup à partir de MAX(id) + 1.
     */
    protected function saveImages(): void
    {
        $fileNames = [];

        foreach ($this->imageFields() as $field => $thumbnail)
        {
            /*
             * Ni fichier envoyé ni suppression demandée : le champ n'a pas bougé, il n'y
             * a rien à écrire. Ce passage comparait le nom calculé au nom enregistré, ce
             * qui revenait à sauter le remplacement d'une image par une autre du même
             * format : le nom, bâti sur {id}_{champ}.{extension}, est alors identique.
             * imageNameAfterEdit() venait pourtant d'effacer l'ancien fichier et sa
             * miniature, le nouveau n'était jamais écrit, et la colonne désignait un
             * fichier absent — un logo remplacé par un autre PNG disparaissait ainsi de
             * la fiche, sans le moindre message.
             */
            if (!$this->isImageFieldTouched($field, $this->isImageMarkedForDeletion($field)))
            {
                continue;
            }

            $name = $this->imageNameAfterEdit(
                $field,
                $this->uploadedFileFor($field),
                $this->storedValues[$field],
                $this->isImageMarkedForDeletion($field),
                $this->recordId,
                $this->uploadsDir
            );

            if (!$this->writeImageFiles($this->uploadedFileFor($field), $name, $this->uploadsSubdir(), $thumbnail))
            {
                // L'ancienne image a déjà été effacée du disque : la colonne doit
                // le refléter, sans quoi la fiche pointerait vers un fichier absent
                $this->message .= ", mais l'image n'a pas pu être enregistrée";
                $name = '';
            }

            $fileNames[$field] = $name;
        }

        if ($fileNames === [])
        {
            return;
        }

        // Les noms de colonnes viennent de imageFields(), jamais d'une saisie
        $assignments = [];
        $params = [':id' => $this->recordId];
        foreach ($fileNames as $field => $name)
        {
            $assignments[] = $field . " = :" . $field;
            $params[':' . $field] = ($name === '') ? $this->absentImageValue() : $name;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE " . $this->table() . " SET " . implode(', ', $assignments)
            . " WHERE " . $this->idColumn() . " = :id"
        );
        $stmt->execute($params);

        $this->storedValues = array_merge($this->storedValues, $fileNames);
    }
}
