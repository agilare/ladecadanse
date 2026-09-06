<?php

namespace Ladecadanse;

use Ladecadanse\Utils\Coordinates;
use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\Validateur;
use PDO;

/**
 * Traitement du formulaire d'ajout et de modification d'un lieu (lieu/edit.php).
 *
 * Passée sous PDO (issue #117) sur le modèle d'OrganisateurEdition : requêtes préparées
 * et colonnes nommées, à la place de l'Element générique qui construisait son SET à
 * partir des clés du tableau de valeurs — donc à partir de ce que le formulaire postait.
 *
 * Ce qu'elle partage avec le formulaire d'organisateur — relecture de l'état enregistré,
 * garde-fous sur les champs réservés, cycle de vie des images, délégation des erreurs —
 * vit dans FicheEdition. Ne reste ici que ce qui est propre au lieu : ses coordonnées,
 * ses catégories, sa localité et les organisateurs qui lui sont rattachés.
 *
 * La galerie d'images et les documents ont disparu avec le formulaire qui les portait :
 * ces deux fonctionnalités sont abandonnées, les images de galerie se posent désormais
 * à la main.
 */
class LieuEdition extends FicheEdition
{
    /** Champs image de la fiche, avec les dimensions de leur miniature « s_ ». */
    private const array IMAGES = [
        'logo'   => ['maxWidth' => 200, 'maxHeight' => 200, 'fitOn' => 'h', 'crop' => 0],
        'photo1' => ['maxWidth' => 300, 'maxHeight' => 300, 'fitOn' => 'w', 'crop' => 1],
    ];

    /**
     * Latitude et longitude, saisie ou relue en base. Elles ne valent que par paire —
     * le plan n'est affiché que si les deux sont connues —, d'où un objet plutôt que
     * deux entrées de $valeurs.
     */
    private Coordinates $coordinates;

    /**
     * Catégories cochées, éclatées depuis la colonne `categories` (un SET) ou telles que
     * le formulaire les a postées.
     *
     * Propriété plutôt qu'entrée de $valeurs : la colonne porte une liste séparée par
     * des virgules là où le formulaire manipule un tableau, et les faire cohabiter sous
     * la même clé revenait à ne jamais savoir laquelle des deux formes on tenait.
     *
     * @var list<string>
     */
    private array $categories = [];

    /**
     * Organisateurs rattachés au lieu, tels que le formulaire les a postés ou tels que
     * la base les porte. Ils vivent dans leur propre table (`lieu_organisateur`), pas
     * dans une colonne de `lieu`.
     *
     * @var list<int>
     */
    private array $organisateurs = [];

    /**
     * Le nom, la préposition, les catégories et les organisateurs ne sont proposés
     * qu'aux éditeurs : un lieu est partagé par tous les événements qui s'y déroulent,
     * les renommer ou les recatégoriser se répercute donc partout. Voir
     * fillEditorsFieldsValuesIfNotAllowed().
     */
    private bool $editorFieldsEditable = false;

    /**
     * Les instances arrivent en paramètre pour que la classe soit exerçable hors requête
     * HTTP ; les valeurs par défaut évitent d'imposer un conteneur aux pages, qui
     * écrivent toutes `new LieuEdition()`.
     */
    public function __construct(
        ?DbConnectorPdo $pdo = null,
        Validateur $verif = new Validateur(),
    )
    {
        global $rep_uploads_lieux;

        $initialValues = array_fill_keys(array_keys(Lieu::FIELDS), '');
        // Colonne dérivée de la localité choisie, jamais saisie directement ; elle sert
        // à représélectionner le bon <option> quand un quartier de Genève a été retenu.
        $initialValues['quartier'] = '';

        parent::__construct(
            $initialValues,
            ['logo' => [], 'photo1' => []],
            $rep_uploads_lieux,
            $pdo,
            $verif
        );

        $this->coordinates = Coordinates::fromInput('', '');
    }

    #[\Override]
    public function validate(): bool
    {
        global $mimes_images_acceptes;

        // Longueurs et obligation viennent de Lieu::FIELDS, dont le formulaire tire aussi
        // ses maxlength et son required : ce qu'il laisse saisir est ce qui est accepté ici
        foreach (Lieu::FIELDS as $field => $rule)
        {
            $this->verif->valider($this->valeurs[$field], $field, $rule['type'], $rule['min'], $rule['max'], $rule['required']);
        }

        foreach ($this->coordinates->errors() as $field => $message)
        {
            $this->verif->setErreur($field, $message);
        }

        if ($this->categories === [])
        {
            $this->verif->setErreur('categories', "Veuillez choisir au moins une catégorie");
        }

        foreach ($this->categories as $category)
        {
            if (!array_key_exists($category, Lieu::CATEGORIES))
            {
                $this->verif->setErreur('categories', "La catégorie " . $category . " n'est pas valable");
            }
        }

        if (!array_key_exists($this->valeurs['statut'], Lieu::STATUTS))
        {
            $this->verif->setErreur("statut", "Ce statut n'existe pas");
        }

        foreach (array_keys(self::IMAGES) as $field)
        {
            $this->verif->validerFichierImage($this->fichiers[$field], $field, $mimes_images_acceptes, 0);
        }

        return $this->verif->nbErreurs() === 0;
    }

    public function setIdLieu(int $idLieu): void
    {
        $this->setRecordId($idLieu);
    }

    public function getIdLieu(): int
    {
        return $this->getRecordId();
    }

    /**
     * Le niveau courant peut-il toucher au nom, à la préposition, aux catégories et aux
     * organisateurs ? Réservé aux éditeurs ; les autres voient ces champs en lecture
     * seule et sont invités à passer par le formulaire de contact.
     */
    public function setCanEditEditorFields(bool $editable): void
    {
        $this->editorFieldsEditable = $editable;
    }

    public function getCoordinates(): Coordinates
    {
        return $this->coordinates;
    }

    /**
     * @return list<int>
     */
    public function getOrganisateurs(): array
    {
        return $this->organisateurs;
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    #[\Override]
    protected function table(): string
    {
        return 'lieu';
    }

    #[\Override]
    protected function idColumn(): string
    {
        return 'idLieu';
    }

    /** La colonne s'écrit tout en minuscules, contrairement à celle d'`organisateur`. */
    #[\Override]
    protected function authorColumn(): string
    {
        return 'idpersonne';
    }

    /**
     * Méthode et non constante : PHP ne sait pas déclarer une constante abstraite sur une
     * classe, la base ne pourrait donc pas exiger que chaque fiche la fournisse.
     */
    #[\Override]
    protected function storedColumns(): array
    {
        // preposition_nom et categories s'y trouvent parce que les non-éditeurs ne les
        // postent pas : c'est de la base qu'il faut alors les reprendre
        return ['nom', 'statut', 'logo', 'photo1', 'preposition_nom', 'categories'];
    }

    #[\Override]
    protected function imageFields(): array
    {
        return self::IMAGES;
    }

    #[\Override]
    protected function uploadsSubdir(): string
    {
        return 'lieux';
    }

    /** `lieu.logo` et `lieu.photo1` acceptent NULL depuis la 3.13.0. */
    #[\Override]
    protected function absentImageValue(): ?string
    {
        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function afterLoad(array $row): void
    {
        $this->categories = self::splitCategories($row['categories'] ?? null);
        $this->coordinates = Coordinates::fromDatabase($row['lat'] ?? null, $row['lng'] ?? null);
        $this->organisateurs = $this->readStoredOrganisateurs();
    }

    /**
     * @param array<string, mixed> $postGlobal
     */
    #[\Override]
    protected function readPostedFields(array $postGlobal): void
    {
        parent::readPostedFields($postGlobal);

        /*
         * Les deux champs à valeurs multiples ne passent pas par la boucle héritée, qui
         * n'accepte que des scalaires. Ils sont lus sans condition d'existence : un
         * <select multiple> entièrement désélectionné et un groupe de cases toutes
         * décochées ne postent aucune clé, et retomber sur la valeur précédente
         * empêcherait de tout retirer.
         */
        $this->categories = array_values(array_filter(
            is_array($postGlobal['categories'] ?? null) ? $postGlobal['categories'] : [],
            'is_string'
        ));

        $this->organisateurs = self::toPositiveIds($postGlobal['organisateurs'] ?? null);

        $this->coordinates = Coordinates::fromInput($postGlobal['lat'] ?? '', $postGlobal['lng'] ?? '');
    }

    /**
     * Rend leur valeur enregistrée aux champs réservés aux éditeurs, quand le formulaire
     * ne les a pas proposés.
     *
     * Ils partaient jusqu'ici en champs cachés, donc modifiables par n'importe quel
     * client : renommer un lieu ou le rattacher à un organisateur ne demandait qu'un
     * POST forgé. À l'ajout la question ne se pose pas, il est réservé aux éditeurs.
     */
    #[\Override]
    protected function fillEditorsFieldsValuesIfNotAllowed(): void
    {
        if ($this->editorFieldsEditable || $this->action !== 'update')
        {
            return;
        }

        $this->valeurs['nom'] = $this->storedValues['nom'];
        $this->valeurs['preposition_nom'] = $this->storedValues['preposition_nom'];
        $this->categories = self::splitCategories($this->storedValues['categories']);
        $this->organisateurs = $this->readStoredOrganisateurs();
    }

    #[\Override]
    protected function insert(): bool
    {
        $now = date("Y-m-d H:i:s");
        [$localiteId, $quartier] = $this->getLocaliteAndQuartierFromLocaliteId();

        $stmt = $this->pdo->prepare("INSERT INTO lieu
            (idpersonne, statut, nom, preposition_nom, categories, adresse, quartier, localite_id, region,
             lat, lng, horaire_general, URL, dateAjout, date_derniere_modif)
            VALUES (:idPersonne, :statut, :nom, :preposition, :categories, :adresse, :quartier, :localiteId, :region,
             :lat, :lng, :horaire, :url, :dateAjout, :dateModif)");

        if (!$stmt->execute($this->getSqlCommonParameters($localiteId, $quartier) + [
            ':idPersonne' => $this->authorId,
            ':dateAjout' => $now,
            ':dateModif' => $now,
        ]))
        {
            return false;
        }

        $this->setRecordId((int) $this->pdo->lastInsertId());
        $this->message = 'Lieu ajouté';

        $this->saveOrganisateurs();
        $this->saveImages();

        return true;
    }

    #[\Override]
    protected function update(): bool
    {
        [$localiteId, $quartier] = $this->getLocaliteAndQuartierFromLocaliteId();

        $stmt = $this->pdo->prepare("UPDATE lieu SET
            statut = :statut, nom = :nom, preposition_nom = :preposition, categories = :categories,
            adresse = :adresse, quartier = :quartier, localite_id = :localiteId, region = :region,
            lat = :lat, lng = :lng, horaire_general = :horaire, URL = :url, date_derniere_modif = :dateModif
            WHERE idLieu = :id");

        // idpersonne n'est pas touché : il désigne l'auteur de la fiche. L'écraser par
        // l'éditeur du moment — ce que faisait l'enregistrement générique — dépossédait
        // l'auteur au premier passage d'un administrateur.
        if (!$stmt->execute($this->getSqlCommonParameters($localiteId, $quartier) + [
            ':dateModif' => date("Y-m-d H:i:s"),
            ':id' => $this->getRecordId(),
        ]))
        {
            return false;
        }

        $this->message = 'Lieu modifié';

        $this->saveOrganisateurs();
        $this->saveImages();

        return true;
    }

    /**
     * Marqueurs communs à l'INSERT et à l'UPDATE.
     *
     * @return array<string, mixed>
     */
    private function getSqlCommonParameters(int $localiteId, string $quartier): array
    {
        return [
            ':statut' => $this->valeurs['statut'],
            ':nom' => $this->valeurs['nom'],
            ':preposition' => $this->valeurs['preposition_nom'] === '' ? null : $this->valeurs['preposition_nom'],
            ':categories' => implode(',', $this->categories),
            ':adresse' => $this->valeurs['adresse'],
            ':quartier' => $quartier,
            ':localiteId' => $localiteId,
            ':region' => $this->regionOfLocalite($localiteId),
            ':lat' => $this->coordinates->latForDatabase(),
            ':lng' => $this->coordinates->lngForDatabase(),
            ':horaire' => $this->valeurs['horaire_general'] === '' ? null : $this->valeurs['horaire_general'],
            ':url' => $this->valeurs['URL'] === '' ? null : $this->valeurs['URL'],
        ];
    }

    /**
     * Localité et quartier tels que le <select> les a postés.
     *
     * Genève est la seule localité à se subdiviser, et ses quartiers voyagent dans la
     * même valeur composée « 44_Pâquis » — voir Localite::renderOptions().
     *
     * @return array{int, string}
     */
    private function getLocaliteAndQuartierFromLocaliteId(): array
    {
        $input = (string) $this->valeurs['localite_id'];

        if (str_contains($input, '_'))
        {
            [$id, $quartier] = explode('_', $input, 2);

            return [(int) $id, $quartier];
        }

        return [(int) $input, ''];
    }

    /**
     * Région du lieu : le canton de sa localité.
     *
     * Un cas particulier codé en dur y rattachait auparavant la localité 529 à Genève,
     * « Nyon, vaudoise mais rattachée à Genève ». Nyon porte l'identifiant 513 ; 529 est
     * Oulens-sur-Lucens, à soixante kilomètres de là. La règle ne s'appliquait donc pas
     * là où elle était voulue, et s'appliquait là où elle n'a pas de sens.
     *
     * Ce que ce cas cherchait à dire est déjà en base : `localite.regions_covered` porte
     * « ge,vd » pour tout le district de Nyon depuis la 3.6.3. C'est là-dessus qu'il
     * faudra s'appuyer le jour où les listes de lieux en tiendront compte — la clause
     * qui l'exploiterait est en commentaire dans Lieu::getLieux().
     */
    private function regionOfLocalite(int $localiteId): string
    {
        $stmt = $this->pdo->prepare("SELECT canton FROM localite WHERE id = :id");
        $stmt->execute([':id' => $localiteId]);

        $canton = $stmt->fetchColumn();

        return $canton === false ? '' : (string) $canton;
    }

    /**
     * Réécrit les liens vers les organisateurs : la table de liaison n'a pas de colonne
     * à mettre à jour, seulement des lignes à poser ou à retirer.
     */
    private function saveOrganisateurs(): void
    {
        $deleteLinks = $this->pdo->prepare("DELETE FROM lieu_organisateur WHERE idLieu = :id");
        $deleteLinks->execute([':id' => $this->getRecordId()]);

        $insertLink = $this->pdo->prepare("INSERT INTO lieu_organisateur (idLieu, idOrganisateur) VALUES (:idLieu, :idOrganisateur)");

        foreach (array_unique($this->organisateurs) as $idOrganisateur)
        {
            $insertLink->execute([':idLieu' => $this->getRecordId(), ':idOrganisateur' => $idOrganisateur]);
        }
    }

    /**
     * @return list<int>
     */
    private function readStoredOrganisateurs(): array
    {
        $stmt = $this->pdo->prepare("SELECT idOrganisateur FROM lieu_organisateur WHERE idLieu = :id");
        $stmt->execute([':id' => $this->getRecordId()]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Codes de catégorie portés par la colonne `categories` (SET), qui les sépare par
     * des virgules.
     *
     * @return list<string>
     */
    private static function splitCategories(mixed $column): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $column))));
    }

    /**
     * Identifiants postés par un <select multiple>, donc entièrement forgeables : tout
     * ce qui n'est pas un entier positif est écarté avant d'atteindre la base.
     *
     * @return list<int>
     */
    private static function toPositiveIds(mixed $posted): array
    {
        if (!is_array($posted))
        {
            return [];
        }

        $ids = array_map(static fn (mixed $value): int => is_scalar($value) ? (int) $value : 0, $posted);

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}
