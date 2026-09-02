<?php

namespace Ladecadanse;

use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\UserHtmlSanitizer;
use Ladecadanse\Utils\Validateur;
use PDO;

/**
 * Traitement du formulaire d'ajout et de modification d'un organisateur
 * (organisateur/edit.php).
 *
 * Passée sous PDO (issue #115) sur le modèle de SalleEdition : requêtes
 * préparées et colonnes nommées, à la place de l'Element générique qui
 * construisait son SET à partir des clés du tableau de valeurs.
 *
 * Ordre des membres : constantes, propriétés, constructeur, le cycle de vie du
 * formulaire (charger, traiter, vérifier, enregistrer), les accesseurs que la vue
 * appelle, puis les méthodes internes.
 */
class OrganisateurEdition extends Edition
{
    use HandlesImageUploads;

    /** Champs image du formulaire, avec les dimensions de leur miniature « s_ ». */
    private const array IMAGES = [
        'logo'  => ['maxLargeur' => 200, 'maxHauteur' => 200, 'selon' => 'h', 'rognage' => 0],
        'photo' => ['maxLargeur' => 300, 'maxHauteur' => 300, 'selon' => 'w', 'rognage' => 1],
    ];

    private DbConnectorPdo $pdo;
    private string $repUploads;

    /** Identifiant de la fiche en cours d'édition ; 0 tant qu'elle n'est pas enregistrée. */
    private int $idOrganisateur = 0;

    /**
     * Champs image dont la case « Supprimer » a été cochée. Nommé ainsi parce que le
     * `$supprimer` d'Edition laissait croire qu'on supprimait l'organisateur lui-même.
     *
     * @var list<string>
     */
    private array $imagesASupprimer = [];

    /**
     * Ce que la base dit déjà de la fiche, par opposition à $valeurs, qui porte la
     * saisie en cours. Les deux sont nécessaires : c'est en les comparant qu'on sait
     * quelle image remplacer, et qu'on retrouve le nom à afficher quand un envoi
     * rejeté a laissé la saisie à moitié faite.
     *
     * Les noms de fichiers y figurent au même titre que les autres colonnes ; c'est
     * dans $fichiers, hérité d'Edition, que vivent les entrées de $_FILES — des
     * métadonnées d'envoi, pas des valeurs de colonne.
     *
     * @var array<string, string>
     */
    private array $valeursEnBase = ['nom' => '', 'statut' => 'actif', 'logo' => '', 'photo' => ''];

    /**
     * Auteur à inscrire sur une fiche créée.
     *
     * Entier pour l'instant, faute de mieux : la colonne `idPersonne` vaut 0 pour les
     * contenus sans auteur. Le jour où elle acceptera NULL — ce qui dirait « pas
     * d'auteur » sans se confondre avec un identifiant —, ce type deviendra ?int et
     * 0 cessera d'être une valeur possible.
     */
    private int $authorId = 0;

    /**
     * Le statut n'est proposé qu'aux administrateurs : la page le dit ici, faute de
     * quoi un POST forgé passerait la valeur de son choix. Voir statutAEcrire().
     */
    private bool $statusEditable = false;

    /**
     * Les instances arrivent en paramètre pour que la classe soit exerçable hors
     * requête HTTP ; les valeurs par défaut évitent d'imposer un conteneur aux pages,
     * qui écrivent toutes `new OrganisateurEdition()`.
     */
    public function __construct(
        ?DbConnectorPdo $pdo = null,
        private readonly Validateur $verif = new Validateur(),
        private readonly UserHtmlSanitizer $htmlSanitizer = new UserHtmlSanitizer(),
    )
    {
        global $rep_uploads_organisateurs;

        $valeurs = array_fill_keys(array_keys(Organisateur::FIELDS), '');
        $valeurs['statut'] = 'actif';

        parent::__construct('organisateur', $valeurs, ['logo' => [], 'photo' => []]);

        // Le connecteur est un singleton, qu'un défaut de paramètre ne sait pas appeler
        $this->pdo = $pdo ?? DbConnectorPdo::getInstance();
        $this->repUploads = $rep_uploads_organisateurs;
    }

    /**
     * Charge la fiche à modifier.
     *
     * @return bool false si elle n'existe pas — à charge de l'appelant de répondre 404
     *              plutôt que d'afficher un formulaire vide sous un titre sans nom
     */
    #[\Override]
    public function loadValeurs(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM organisateur WHERE idOrganisateur = :id");
        $stmt->execute([':id' => $id]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ligne === false)
        {
            return false;
        }

        foreach (array_keys($this->valeurs) as $champ)
        {
            if (array_key_exists($champ, $ligne))
            {
                $this->valeurs[$champ] = $ligne[$champ];
            }
        }

        $this->lireValeursEnBase($ligne);
        $this->idOrganisateur = $id;
        $this->authorId = (int) $ligne['idPersonne'];

        return true;
    }

    #[\Override]
    public function traitement(array $post, array $files): bool
    {
        foreach (array_keys($this->valeurs) as $champ)
        {
            // is_scalar() écarte un « nom[]=x » forgé, qui déclencherait sinon
            // une conversion de tableau en chaîne
            if (isset($post[$champ]) && is_scalar($post[$champ]))
            {
                $this->valeurs[$champ] = trim((string) $post[$champ]);
            }
        }

        foreach (array_keys(self::IMAGES) as $champ)
        {
            $this->fichiers[$champ] = $files[$champ] ?? ['name' => '', 'tmp_name' => '', 'size' => 0];
        }

        $this->imagesASupprimer = (isset($post['supprimer']) && is_array($post['supprimer'])) ? $post['supprimer'] : [];

        // L'image à remplacer et le statut à conserver sont ceux de la base, pas ceux
        // que le POST annonce. La page a déjà répondu 404 si la fiche n'existe pas :
        // ce retour ne couvre que la suppression concurrente d'une fiche en cours d'édition.
        if ($this->action === 'update' && !$this->chargerValeursEnBase())
        {
            return false;
        }

        $this->valeurs['statut'] = $this->statutAEcrire();

        if (!$this->verification())
        {
            return false;
        }

        return $this->enregistrer();
    }

    #[\Override]
    public function verification(): bool
    {
        global $mimes_images_acceptes;

        // Longueurs et obligation viennent de Organisateur::FIELDS, dont le formulaire tire
        // aussi ses maxlength et son required : ce qu'il laisse saisir est ce qui est accepté ici
        foreach (Organisateur::FIELDS as $champ => $regle)
        {
            $this->verif->valider($this->valeurs[$champ], $champ, $regle['type'], $regle['min'], $regle['max'], $regle['required']);
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
        if ($this->verif->getErreur("nom") === false && $this->nomDejaPris())
        {
            $this->verif->setErreur("nom", "Un organisateur porte déjà ce nom");
        }

        $this->erreurs = array_merge($this->erreurs, $this->verif->getErreurs());

        return $this->verif->nbErreurs() === 0;
    }

    #[\Override]
    public function enregistrer(): bool
    {
        return match ($this->action) {
            'insert' => $this->insert(),
            'update' => $this->update(),
            default => false,
        };
    }

    public function setIdOrganisateur(int $idOrganisateur): void
    {
        $this->idOrganisateur = $idOrganisateur;
    }

    public function getIdOrganisateur(): int
    {
        return $this->idOrganisateur;
    }

    /**
     * La fiche désignée existe-t-elle ?
     *
     * À la soumission, la page a besoin de le savoir sans recharger le formulaire :
     * loadValeurs() écraserait la saisie en cours.
     */
    public function ficheExiste(): bool
    {
        return $this->chargerValeursEnBase();
    }

    /**
     * Auteur à inscrire sur une fiche créée : la personne qui la saisit. Une
     * modification ne le déplace pas vers celui qui la fait — c'est de lui que
     * dépend son droit de modifier la fiche.
     */
    public function setAuthorId(int $authorId): void
    {
        $this->authorId = $authorId;
    }

    public function setStatusEditable(bool $statusEditable): void
    {
        $this->statusEditable = $statusEditable;
    }

    /**
     * Nom du fichier image enregistré en base, pour l'aperçu du formulaire.
     */
    public function getStoredImageName(string $champ): string
    {
        return $this->valeursEnBase[$champ] ?? '';
    }

    /**
     * La case « Supprimer » de ce champ image a-t-elle été cochée ?
     */
    public function isImageMarkedForDeletion(string $champ): bool
    {
        return in_array($champ, $this->imagesASupprimer, true);
    }

    /**
     * Nom tel qu'il est enregistré, pour le titre de la page : celui du
     * formulaire est la saisie en cours, qu'un envoi rejeté laisse à moitié faite.
     */
    public function getStoredName(): string
    {
        return $this->valeursEnBase['nom'];
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

    #[\Override]
    public function getHtmlErreur(string $champ): string
    {
        return $this->verif->getHtmlErreur($champ);
    }

    private function insert(): bool
    {
        $maintenant = date("Y-m-d H:i:s");

        $stmt = $this->pdo->prepare("INSERT INTO organisateur
            (idPersonne, nom, adresse, URL, email, presentation, statut, date_ajout, date_derniere_modif)
            VALUES (:idPersonne, :nom, :adresse, :url, :email, :presentation, :statut, :dateAjout, :dateModif)");

        if (!$stmt->execute($this->parametresCommuns() + [
            ':idPersonne' => $this->authorId,
            ':dateAjout' => $maintenant,
            ':dateModif' => $maintenant,
        ]))
        {
            return false;
        }

        $this->idOrganisateur = (int) $this->pdo->lastInsertId();
        $this->message = "Organisateur ajouté";

        $this->enregistrerLesImages();

        return true;
    }

    private function update(): bool
    {
        $stmt = $this->pdo->prepare("UPDATE organisateur SET
            nom = :nom, adresse = :adresse, URL = :url, email = :email,
            presentation = :presentation, statut = :statut, date_derniere_modif = :dateModif
            WHERE idOrganisateur = :id");

        // idPersonne n'est pas touché : il désigne l'auteur de la fiche, dont dépend
        // son droit de la modifier. L'écraser par l'éditeur du moment — ce que faisait
        // l'enregistrement générique — dépossédait l'auteur au premier passage d'un admin.
        if (!$stmt->execute($this->parametresCommuns() + [
            ':dateModif' => date("Y-m-d H:i:s"),
            ':id' => $this->idOrganisateur,
        ]))
        {
            return false;
        }

        $this->message = "Organisateur modifié";

        $this->enregistrerLesImages();

        return true;
    }

    /**
     * Statut à écrire : celui que le formulaire a posté quand l'utilisateur a le droit
     * d'en changer, sinon celui que la fiche porte déjà — « actif » pour une création.
     *
     * Sans cela, le champ caché que le formulaire donnait aux non-administrateurs
     * annonçait « actif » : un acteur qui modifiait une fiche dépubliée la republiait
     * sans le savoir.
     */
    private function statutAEcrire(): string
    {
        if ($this->statusEditable)
        {
            return $this->valeurs['statut'];
        }

        return $this->action === 'update' ? $this->valeursEnBase['statut'] : 'actif';
    }

    /**
     * @return array<string, string>
     */
    private function parametresCommuns(): array
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

    private function nomDejaPris(): bool
    {
        $sql = "SELECT idOrganisateur FROM organisateur WHERE nom = :nom AND statut = 'actif'";
        $params = [':nom' => $this->valeurs['nom']];

        if ($this->getIdOrganisateur() > 0)
        {
            $sql .= " AND idOrganisateur <> :id";
            $params[':id'] = $this->idOrganisateur;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Relit ce que la base dit de la fiche, sans toucher à la saisie en cours.
     *
     * @return bool false si la fiche n'existe pas — un UPDATE sur un identifiant
     *              inconnu ne touche aucune ligne et réussit en silence
     */
    private function chargerValeursEnBase(): bool
    {
        $stmt = $this->pdo->prepare("SELECT nom, statut, logo, photo FROM organisateur WHERE idOrganisateur = :id");
        $stmt->execute([':id' => $this->idOrganisateur]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ligne === false)
        {
            return false;
        }

        $this->lireValeursEnBase($ligne);

        return true;
    }

    /**
     * @param array<string, mixed> $ligne
     */
    private function lireValeursEnBase(array $ligne): void
    {
        foreach (array_keys($this->valeursEnBase) as $colonne)
        {
            $this->valeursEnBase[$colonne] = (string) $ligne[$colonne];
        }
    }

    /**
     * Nomme, écrit et enregistre les images.
     *
     * Le nom d'un fichier encode l'identifiant de l'organisateur, qui à l'ajout
     * n'est connu qu'une fois l'INSERT fait : d'où ce second passage en base
     * plutôt qu'un nom deviné avant coup à partir de MAX(id) + 1.
     */
    private function enregistrerLesImages(): void
    {
        $nomsFichiers = [];

        foreach (self::IMAGES as $champ => $miniature)
        {
            $nom = $this->nomImageApresEdition(
                $champ,
                $this->fichiers[$champ],
                $this->valeursEnBase[$champ],
                $this->isImageMarkedForDeletion($champ),
                $this->getIdOrganisateur(),
                $this->repUploads
            );

            if ($nom === $this->valeursEnBase[$champ])
            {
                continue;
            }

            if (!$this->ecrireImageEtMiniature($this->fichiers[$champ], $nom, "organisateurs", $miniature))
            {
                // L'ancienne image a déjà été effacée du disque : la colonne doit
                // le refléter, sans quoi la fiche pointerait vers un fichier absent
                $this->message .= ", mais l'image n'a pas pu être enregistrée";
                $nom = '';
            }

            $nomsFichiers[$champ] = $nom;
        }

        if ($nomsFichiers === [])
        {
            return;
        }

        // Les noms de colonnes viennent de self::IMAGES, jamais d'une saisie
        $affectations = [];
        $params = [':id' => $this->idOrganisateur];
        foreach ($nomsFichiers as $champ => $nom)
        {
            $affectations[] = $champ . " = :" . $champ;
            $params[':' . $champ] = $nom;
        }

        $stmt = $this->pdo->prepare("UPDATE organisateur SET " . implode(', ', $affectations) . " WHERE idOrganisateur = :id");
        $stmt->execute($params);

        $this->valeursEnBase = array_merge($this->valeursEnBase, $nomsFichiers);
    }
}
