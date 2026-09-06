<?php

namespace Ladecadanse;

use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\UserHtmlSanitizer;
use Ladecadanse\Utils\Validateur;

/**
 * Traitement du formulaire d'ajout et de modification d'un organisateur
 * (organisateur/edit.php).
 *
 * Passée sous PDO (issue #115) sur le modèle de SalleEdition : requêtes préparées et
 * colonnes nommées, à la place de l'Element générique qui construisait son SET à partir
 * des clés du tableau de valeurs.
 *
 * Ce qu'elle partage avec le formulaire de lieu — relecture de l'état enregistré,
 * garde-fou sur le statut, cycle de vie des images, délégation des erreurs — vit dans
 * FicheEdition (issue #117). Ne reste ici que ce qui est propre à l'organisateur.
 */
class OrganisateurEdition extends FicheEdition
{
    /** Champs image du formulaire, avec les dimensions de leur miniature « s_ ». */
    private const array IMAGES = [
        'logo'  => ['maxWidth' => 200, 'maxHeight' => 200, 'fitOn' => 'h', 'crop' => 0],
        'photo' => ['maxWidth' => 300, 'maxHeight' => 300, 'fitOn' => 'w', 'crop' => 1],
    ];

    /**
     * Les instances arrivent en paramètre pour que la classe soit exerçable hors
     * requête HTTP ; les valeurs par défaut évitent d'imposer un conteneur aux pages,
     * qui écrivent toutes `new OrganisateurEdition()`.
     */
    public function __construct(
        ?DbConnectorPdo $pdo = null,
        Validateur $verif = new Validateur(),
        private readonly UserHtmlSanitizer $htmlSanitizer = new UserHtmlSanitizer(),
    )
    {
        global $rep_uploads_organisateurs;

        parent::__construct(
            array_fill_keys(array_keys(Organisateur::FIELDS), ''),
            ['logo' => [], 'photo' => []],
            $rep_uploads_organisateurs,
            $pdo,
            $verif
        );
    }

    #[\Override]
    public function validate(): bool
    {
        global $mimes_images_acceptes;

        // Longueurs et obligation viennent de Organisateur::FIELDS, dont le formulaire tire
        // aussi ses maxlength et son required : ce qu'il laisse saisir est ce qui est accepté ici
        foreach (Organisateur::FIELDS as $field => $rule)
        {
            $this->verif->valider($this->valeurs[$field], $field, $rule['type'], $rule['min'], $rule['max'], $rule['required']);
        }

        $this->verif->validerFichierImage($this->fichiers['logo'], "logo", $mimes_images_acceptes, 0);
        $this->verif->validerFichierImage($this->fichiers['photo'], "photo", $mimes_images_acceptes, 0);

        if (!array_key_exists($this->valeurs['statut'], Organisateur::STATUTS))
        {
            $this->verif->setErreur("statut", "Ce statut n'existe pas");
        }

        // Le nom identifie l'organisateur dans les listes des formulaires d'événement,
        // où deux fiches publiées homonymes sont indiscernables. Le contrôle existait
        // mais ne s'exécutait jamais : il attendait une action « insert » que la page
        // ne lui passait pas.
        if ($this->verif->getErreur("nom") === false && $this->isNameAlreadyTaken())
        {
            $this->verif->setErreur("nom", "Un organisateur porte déjà ce nom");
        }

        $this->erreurs = array_merge($this->erreurs, $this->verif->getErreurs());

        return $this->verif->nbErreurs() === 0;
    }

    public function setIdOrganisateur(int $idOrganisateur): void
    {
        $this->setRecordId($idOrganisateur);
    }

    public function getIdOrganisateur(): int
    {
        return $this->getRecordId();
    }

    #[\Override]
    protected function table(): string
    {
        return 'organisateur';
    }

    #[\Override]
    protected function idColumn(): string
    {
        return 'idOrganisateur';
    }

    #[\Override]
    protected function authorColumn(): string
    {
        return 'idPersonne';
    }

    #[\Override]
    protected function storedColumns(): array
    {
        return ['nom', 'statut', 'logo', 'photo'];
    }

    #[\Override]
    protected function imageFields(): array
    {
        return self::IMAGES;
    }

    #[\Override]
    protected function uploadsSubdir(): string
    {
        return 'organisateurs';
    }

    #[\Override]
    protected function insert(): bool
    {
        $now = date("Y-m-d H:i:s");

        $stmt = $this->pdo->prepare("INSERT INTO organisateur
            (idPersonne, nom, adresse, URL, email, presentation, statut, date_ajout, date_derniere_modif)
            VALUES (:idPersonne, :nom, :adresse, :url, :email, :presentation, :statut, :dateAjout, :dateModif)");

        if (!$stmt->execute($this->getSqlCommonParameters() + [
            ':idPersonne' => $this->authorId,
            ':dateAjout' => $now,
            ':dateModif' => $now,
        ]))
        {
            return false;
        }

        $this->setRecordId((int) $this->pdo->lastInsertId());
        $this->message = "Organisateur ajouté";

        $this->saveImages();

        return true;
    }

    #[\Override]
    protected function update(): bool
    {
        $stmt = $this->pdo->prepare("UPDATE organisateur SET
            nom = :nom, adresse = :adresse, URL = :url, email = :email,
            presentation = :presentation, statut = :statut, date_derniere_modif = :dateModif
            WHERE idOrganisateur = :id");

        // idPersonne n'est pas touché : il désigne l'auteur de la fiche, dont dépend
        // son droit de la modifier. L'écraser par l'éditeur du moment — ce que faisait
        // l'enregistrement générique — dépossédait l'auteur au premier passage d'un admin.
        if (!$stmt->execute($this->getSqlCommonParameters() + [
            ':dateModif' => date("Y-m-d H:i:s"),
            ':id' => $this->getRecordId(),
        ]))
        {
            return false;
        }

        $this->message = "Organisateur modifié";

        $this->saveImages();

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function getSqlCommonParameters(): array
    {
        return [
            ':nom' => $this->valeurs['nom'],
            ':adresse' => $this->valeurs['adresse'],
            ':url' => $this->valeurs['URL'],
            ':email' => $this->valeurs['email'],
            ':presentation' => $this->htmlSanitizer->sanitize($this->valeurs['presentation']),
            ':statut' => $this->valeurs['statut'],
        ];
    }

    private function isNameAlreadyTaken(): bool
    {
        $sql = "SELECT idOrganisateur FROM organisateur WHERE nom = :nom AND statut = 'actif'";
        $params = [':nom' => $this->valeurs['nom']];

        if ($this->getRecordId() > 0)
        {
            $sql .= " AND idOrganisateur <> :id";
            $params[':id'] = $this->getRecordId();
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }
}
