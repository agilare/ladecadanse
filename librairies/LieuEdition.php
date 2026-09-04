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
        'logo'   => ['maxLargeur' => 200, 'maxHauteur' => 200, 'selon' => 'h', 'rognage' => 0],
        'photo1' => ['maxLargeur' => 300, 'maxHauteur' => 300, 'selon' => 'w', 'rognage' => 1],
    ];

    /**
     * Latitude et longitude, saisie ou relue en base. Elles ne valent que par paire —
     * le plan n'est affiché que si les deux sont connues —, d'où un objet plutôt que
     * deux entrées de $valeurs.
     */
    private Coordinates $coordonnees;

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
     * appliquerChampsReserves().
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

        $valeurs = array_fill_keys(array_keys(Lieu::FIELDS), '');
        // Colonne dérivée de la localité choisie, jamais saisie directement ; elle sert
        // à représélectionner le bon <option> quand un quartier de Genève a été retenu.
        $valeurs['quartier'] = '';

        parent::__construct(
            'lieu',
            $valeurs,
            ['logo' => [], 'photo1' => []],
            $rep_uploads_lieux,
            $pdo,
            $verif
        );

        $this->coordonnees = Coordinates::fromInput('', '');
    }

    #[\Override]
    public function verification(): bool
    {
        global $mimes_images_acceptes;

        // Longueurs et obligation viennent de Lieu::FIELDS, dont le formulaire tire aussi
        // ses maxlength et son required : ce qu'il laisse saisir est ce qui est accepté ici
        foreach (Lieu::FIELDS as $champ => $regle)
        {
            $this->verif->valider($this->valeurs[$champ], $champ, $regle['type'], $regle['min'], $regle['max'], $regle['required']);
        }

        foreach ($this->coordonnees->erreurs() as $champ => $message)
        {
            $this->verif->setErreur($champ, $message);
        }

        if ($this->categories === [])
        {
            $this->verif->setErreur('categories', "Veuillez choisir au moins une catégorie");
        }

        foreach ($this->categories as $categorie)
        {
            if (!array_key_exists($categorie, Lieu::CATEGORIES))
            {
                $this->verif->setErreur('categories', "La catégorie " . $categorie . " n'est pas valable");
            }
        }

        if (!array_key_exists($this->valeurs['statut'], Lieu::STATUTS))
        {
            $this->verif->setErreur("statut", "Ce statut n'existe pas");
        }

        foreach (array_keys(self::IMAGES) as $champ)
        {
            $this->verif->validerFichierImage($this->fichiers[$champ], $champ, $mimes_images_acceptes, 0);
        }

        $this->erreurs = array_merge($this->erreurs, $this->verif->getErreurs());

        return $this->verif->nbErreurs() === 0;
    }

    public function setIdLieu(int $idLieu): void
    {
        $this->setFicheId($idLieu);
    }

    public function getIdLieu(): int
    {
        return $this->getFicheId();
    }

    /**
     * Le niveau courant peut-il toucher au nom, à la préposition, aux catégories et aux
     * organisateurs ? Réservé aux éditeurs ; les autres voient ces champs en lecture
     * seule et sont invités à passer par le formulaire de contact.
     */
    public function setEditorFieldsEditable(bool $editable): void
    {
        $this->editorFieldsEditable = $editable;
    }

    public function isEditorFieldsEditable(): bool
    {
        return $this->editorFieldsEditable;
    }

    public function getCoordonnees(): Coordinates
    {
        return $this->coordonnees;
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
    protected function colonneId(): string
    {
        return 'idLieu';
    }

    /** La colonne s'écrit tout en minuscules, contrairement à celle d'`organisateur`. */
    #[\Override]
    protected function colonneAuteur(): string
    {
        return 'idpersonne';
    }

    #[\Override]
    protected function colonnesEnBase(): array
    {
        // preposition_nom et categories s'y trouvent parce que les non-éditeurs ne les
        // postent pas : c'est de la base qu'il faut alors les reprendre
        return ['nom', 'statut', 'logo', 'photo1', 'preposition_nom', 'categories'];
    }

    #[\Override]
    protected function champsImage(): array
    {
        return self::IMAGES;
    }

    #[\Override]
    protected function repertoireUploads(): string
    {
        return 'lieux';
    }

    /** `lieu.logo` et `lieu.photo1` acceptent NULL depuis la 3.12.0. */
    #[\Override]
    protected function valeurImageAbsente(): ?string
    {
        return null;
    }

    /**
     * @param array<string, mixed> $ligne
     */
    #[\Override]
    protected function apresChargement(array $ligne): void
    {
        $this->categories = self::eclaterCategories($ligne['categories'] ?? null);
        $this->coordonnees = Coordinates::fromDatabase($ligne['lat'] ?? null, $ligne['lng'] ?? null);
        $this->organisateurs = $this->lireOrganisateursEnBase();
    }

    /**
     * @param array<string, mixed> $post
     */
    #[\Override]
    protected function lireChampsPostes(array $post): void
    {
        parent::lireChampsPostes($post);

        /*
         * Les deux champs à valeurs multiples ne passent pas par la boucle héritée, qui
         * n'accepte que des scalaires. Ils sont lus sans condition d'existence : un
         * <select multiple> entièrement désélectionné et un groupe de cases toutes
         * décochées ne postent aucune clé, et retomber sur la valeur précédente
         * empêcherait de tout retirer.
         */
        $this->categories = array_values(array_filter(
            is_array($post['categories'] ?? null) ? $post['categories'] : [],
            'is_string'
        ));

        $this->organisateurs = self::identifiants($post['organisateurs'] ?? null);

        $this->coordonnees = Coordinates::fromInput($post['lat'] ?? '', $post['lng'] ?? '');
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
    protected function appliquerChampsReserves(): void
    {
        if ($this->editorFieldsEditable || $this->action !== 'update')
        {
            return;
        }

        $this->valeurs['nom'] = $this->valeursEnBase['nom'];
        $this->valeurs['preposition_nom'] = $this->valeursEnBase['preposition_nom'];
        $this->categories = self::eclaterCategories($this->valeursEnBase['categories']);
        $this->organisateurs = $this->lireOrganisateursEnBase();
    }

    #[\Override]
    protected function insert(): bool
    {
        $maintenant = date("Y-m-d H:i:s");
        [$localiteId, $quartier] = $this->localiteEtQuartier();

        $stmt = $this->pdo->prepare("INSERT INTO lieu
            (idpersonne, statut, nom, preposition_nom, categories, adresse, quartier, localite_id, region,
             lat, lng, horaire_general, URL, dateAjout, date_derniere_modif)
            VALUES (:idPersonne, :statut, :nom, :preposition, :categories, :adresse, :quartier, :localiteId, :region,
             :lat, :lng, :horaire, :url, :dateAjout, :dateModif)");

        if (!$stmt->execute($this->parametresCommuns($localiteId, $quartier) + [
            ':idPersonne' => $this->authorId,
            ':dateAjout' => $maintenant,
            ':dateModif' => $maintenant,
        ]))
        {
            return false;
        }

        $this->setFicheId((int) $this->pdo->lastInsertId());
        $this->message = 'Lieu ajouté';

        $this->enregistrerLesOrganisateurs();
        $this->enregistrerLesImages();

        return true;
    }

    #[\Override]
    protected function update(): bool
    {
        [$localiteId, $quartier] = $this->localiteEtQuartier();

        $stmt = $this->pdo->prepare("UPDATE lieu SET
            statut = :statut, nom = :nom, preposition_nom = :preposition, categories = :categories,
            adresse = :adresse, quartier = :quartier, localite_id = :localiteId, region = :region,
            lat = :lat, lng = :lng, horaire_general = :horaire, URL = :url, date_derniere_modif = :dateModif
            WHERE idLieu = :id");

        // idpersonne n'est pas touché : il désigne l'auteur de la fiche. L'écraser par
        // l'éditeur du moment — ce que faisait l'enregistrement générique — dépossédait
        // l'auteur au premier passage d'un administrateur.
        if (!$stmt->execute($this->parametresCommuns($localiteId, $quartier) + [
            ':dateModif' => date("Y-m-d H:i:s"),
            ':id' => $this->getFicheId(),
        ]))
        {
            return false;
        }

        $this->message = 'Lieu modifié';

        $this->enregistrerLesOrganisateurs();
        $this->enregistrerLesImages();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function parametresCommuns(int $localiteId, string $quartier): array
    {
        return [
            ':statut' => $this->valeurs['statut'],
            ':nom' => $this->valeurs['nom'],
            ':preposition' => $this->valeurs['preposition_nom'] === '' ? null : $this->valeurs['preposition_nom'],
            ':categories' => implode(',', $this->categories),
            ':adresse' => $this->valeurs['adresse'],
            ':quartier' => $quartier,
            ':localiteId' => $localiteId,
            ':region' => $this->regionDeLaLocalite($localiteId),
            ':lat' => $this->coordonnees->latPourBase(),
            ':lng' => $this->coordonnees->lngPourBase(),
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
    private function localiteEtQuartier(): array
    {
        $saisie = (string) $this->valeurs['localite_id'];

        if (str_contains($saisie, '_'))
        {
            [$id, $quartier] = explode('_', $saisie, 2);

            return [(int) $id, $quartier];
        }

        return [(int) $saisie, ''];
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
    private function regionDeLaLocalite(int $localiteId): string
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
    private function enregistrerLesOrganisateurs(): void
    {
        $suppression = $this->pdo->prepare("DELETE FROM lieu_organisateur WHERE idLieu = :id");
        $suppression->execute([':id' => $this->getFicheId()]);

        $ajout = $this->pdo->prepare("INSERT INTO lieu_organisateur (idLieu, idOrganisateur) VALUES (:idLieu, :idOrganisateur)");

        foreach (array_unique($this->organisateurs) as $idOrganisateur)
        {
            $ajout->execute([':idLieu' => $this->getFicheId(), ':idOrganisateur' => $idOrganisateur]);
        }
    }

    /**
     * @return list<int>
     */
    private function lireOrganisateursEnBase(): array
    {
        $stmt = $this->pdo->prepare("SELECT idOrganisateur FROM lieu_organisateur WHERE idLieu = :id");
        $stmt->execute([':id' => $this->getFicheId()]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Codes de catégorie portés par la colonne `categories` (SET), qui les sépare par
     * des virgules.
     *
     * @return list<string>
     */
    private static function eclaterCategories(mixed $colonne): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $colonne))));
    }

    /**
     * Identifiants postés par un <select multiple>, donc entièrement forgeables : tout
     * ce qui n'est pas un entier positif est écarté avant d'atteindre la base.
     *
     * @return list<int>
     */
    private static function identifiants(mixed $poste): array
    {
        if (!is_array($poste))
        {
            return [];
        }

        $identifiants = array_map(static fn (mixed $valeur): int => is_scalar($valeur) ? (int) $valeur : 0, $poste);

        return array_values(array_filter($identifiants, static fn (int $id): bool => $id > 0));
    }
}
