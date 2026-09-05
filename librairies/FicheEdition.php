<?php

namespace Ladecadanse;

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
    protected const string STATUT_INITIAL = 'actif';

    protected DbConnectorPdo $pdo;

    /** Répertoire système des images de cette entité (app/config.php). */
    protected string $repUploads;

    /** Identifiant de la fiche en cours d'édition ; 0 tant qu'elle n'est pas enregistrée. */
    protected int $ficheId = 0;

    /**
     * Champs image dont la case « Supprimer » a été cochée. Nommé ainsi parce que le
     * `$supprimer` d'Edition laissait croire qu'on supprimait la fiche elle-même.
     *
     * @var list<string>
     */
    protected array $imagesASupprimer = [];

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
    protected array $valeursEnBase = [];

    /**
     * Auteur à inscrire sur une fiche créée.
     *
     * Entier pour l'instant, faute de mieux : la colonne vaut 0 pour les contenus sans
     * auteur. Le jour où elle acceptera NULL — ce qui dirait « pas d'auteur » sans se
     * confondre avec un identifiant —, ce type deviendra ?int et 0 cessera d'être une
     * valeur possible.
     */
    protected int $authorId = 0;

    /**
     * Le statut n'est proposé qu'aux modérateurs : la page le dit ici, faute de quoi un
     * POST forgé passerait la valeur de son choix. Voir statutAEcrire().
     */
    protected bool $statusEditable = false;

    /**
     * @param array<string, mixed> $valeurs champs du formulaire, avec leur valeur initiale
     * @param array<string, array<string, mixed>> $fichiers champs de type fichier
     * @param string $repUploads répertoire système des images de l'entité
     */
    public function __construct(
        string $nom,
        array $valeurs,
        array $fichiers,
        string $repUploads,
        ?DbConnectorPdo $pdo = null,
        protected readonly Validateur $verif = new Validateur(),
    )
    {
        $valeurs['statut'] = static::STATUT_INITIAL;

        parent::__construct($nom, $valeurs, $fichiers);

        // Le connecteur est un singleton, qu'un défaut de paramètre ne sait pas appeler
        $this->pdo = $pdo ?? DbConnectorPdo::getInstance();
        $this->repUploads = $repUploads;

        $this->valeursEnBase = array_fill_keys($this->colonnesEnBase(), '');
        $this->valeursEnBase['statut'] = static::STATUT_INITIAL;
    }

    /*
     * Le contrat des classes filles : de quoi bâtir les requêtes génériques ci-dessous.
     * Toutes ces valeurs sont des littéraux écrits dans le code, jamais des saisies —
     * c'est ce qui autorise leur interpolation dans le SQL.
     */

    /** Table de l'entité. */
    abstract protected function table(): string;

    /** Colonne portant la clé primaire (idLieu, idOrganisateur). */
    abstract protected function colonneId(): string;

    /**
     * Colonne portant l'auteur de la fiche. `lieu` et `organisateur` ne l'écrivent pas
     * de la même façon (`idpersonne` contre `idPersonne`), et les clés que rend un
     * SELECT * suivent la déclaration de la table.
     */
    abstract protected function colonneAuteur(): string;

    /**
     * Colonnes relues pour connaître l'état enregistré de la fiche : au minimum `nom`,
     * `statut` et les champs image.
     *
     * @return list<string>
     */
    abstract protected function colonnesEnBase(): array;

    /**
     * Champs image du formulaire, avec les dimensions de leur miniature « s_ ».
     *
     * @return array<string, array{maxLargeur: int, maxHauteur: int, selon: string, rognage: int}>
     */
    abstract protected function champsImage(): array;

    /** Sous-répertoire d'uploads au sens d'ImageDriver2 ("lieux", "organisateurs"). */
    abstract protected function repertoireUploads(): string;

    /**
     * Valeur à écrire dans une colonne image quand la fiche n'en porte pas.
     *
     * La chaîne vide par défaut, faute de mieux : `organisateur.logo` et `photo` sont
     * NOT NULL. Une table dont les colonnes image acceptent NULL — c'est le cas de `lieu`
     * depuis la 3.13.0 — redéfinit ceci, sans quoi elle porterait deux écritures pour la
     * même absence : NULL sur les lignes migrées, '' sur celles dont on retire l'image.
     */
    protected function valeurImageAbsente(): ?string
    {
        return '';
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
        $stmt = $this->pdo->prepare("SELECT * FROM " . $this->table() . " WHERE " . $this->colonneId() . " = :id");
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
        $this->ficheId = $id;
        $this->authorId = (int) $ligne[$this->colonneAuteur()];

        $this->apresChargement($ligne);

        return true;
    }

    #[\Override]
    public function traitement(array $post, array $files): bool
    {
        $this->lireChampsPostes($post);
        $this->lireFichiersPostes($files);
        $this->lireSuppressionsPostees($post);

        // L'image à remplacer et le statut à conserver sont ceux de la base, pas ceux
        // que le POST annonce. La page a déjà répondu 404 si la fiche n'existe pas :
        // ce retour ne couvre que la suppression concurrente d'une fiche en cours d'édition.
        if ($this->action === 'update' && !$this->chargerValeursEnBase())
        {
            return false;
        }

        $this->valeurs['statut'] = $this->statutAEcrire();
        $this->appliquerChampsReserves();

        if (!$this->verification())
        {
            return false;
        }

        return $this->enregistrer();
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

    abstract protected function insert(): bool;

    abstract protected function update(): bool;

    public function setFicheId(int $id): void
    {
        $this->ficheId = $id;
    }

    public function getFicheId(): int
    {
        return $this->ficheId;
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
        return $this->valeursEnBase['nom'] ?? '';
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

    /**
     * Champs supplémentaires à relire au chargement d'une fiche : ceux qui ne sont pas
     * une colonne de la table (catégories à éclater, entités liées).
     *
     * @param array<string, mixed> $ligne
     */
    protected function apresChargement(array $ligne): void
    {
    }

    /**
     * Rend leur valeur enregistrée aux champs que le niveau courant n'a pas le droit de
     * modifier, sur le modèle de statutAEcrire().
     *
     * Un formulaire qui n'affiche pas un champ le repostait en champ caché : la valeur
     * arrivait donc du client, et rien n'empêchait de la forger.
     */
    protected function appliquerChampsReserves(): void
    {
    }

    /**
     * @param array<string, mixed> $post
     */
    protected function lireChampsPostes(array $post): void
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
    }

    /**
     * @param array<string, mixed> $files
     */
    protected function lireFichiersPostes(array $files): void
    {
        foreach (array_keys($this->champsImage()) as $champ)
        {
            $this->fichiers[$champ] = $files[$champ] ?? ['name' => '', 'tmp_name' => '', 'size' => 0];
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    protected function lireSuppressionsPostees(array $post): void
    {
        $this->imagesASupprimer = (isset($post['supprimer']) && is_array($post['supprimer'])) ? $post['supprimer'] : [];
    }

    /**
     * Statut à écrire : celui que le formulaire a posté quand l'utilisateur a le droit
     * d'en changer, sinon celui que la fiche porte déjà — le statut initial pour une
     * création.
     *
     * Sans cela, le champ caché que le formulaire donnait aux autres niveaux annonçait
     * « actif » : un acteur qui modifiait une fiche dépubliée la republiait sans le savoir.
     */
    protected function statutAEcrire(): string
    {
        if ($this->statusEditable)
        {
            return (string) $this->valeurs['statut'];
        }

        return $this->action === 'update' ? $this->valeursEnBase['statut'] : static::STATUT_INITIAL;
    }

    /**
     * Relit ce que la base dit de la fiche, sans toucher à la saisie en cours.
     *
     * @return bool false si la fiche n'existe pas — un UPDATE sur un identifiant
     *              inconnu ne touche aucune ligne et réussit en silence
     */
    protected function chargerValeursEnBase(): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT " . implode(', ', $this->colonnesEnBase())
            . " FROM " . $this->table()
            . " WHERE " . $this->colonneId() . " = :id"
        );
        $stmt->execute([':id' => $this->ficheId]);

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
    protected function lireValeursEnBase(array $ligne): void
    {
        foreach (array_keys($this->valeursEnBase) as $colonne)
        {
            $this->valeursEnBase[$colonne] = (string) ($ligne[$colonne] ?? '');
        }
    }

    /**
     * Nomme, écrit et enregistre les images.
     *
     * Le nom d'un fichier encode l'identifiant de la fiche, qui à l'ajout n'est connu
     * qu'une fois l'INSERT fait : d'où ce second passage en base plutôt qu'un nom deviné
     * avant coup à partir de MAX(id) + 1.
     */
    protected function enregistrerLesImages(): void
    {
        $nomsFichiers = [];

        foreach ($this->champsImage() as $champ => $miniature)
        {
            $nom = $this->nomImageApresEdition(
                $champ,
                $this->fichierEnvoye($champ),
                $this->valeursEnBase[$champ],
                $this->isImageMarkedForDeletion($champ),
                $this->ficheId,
                $this->repUploads
            );

            if ($nom === $this->valeursEnBase[$champ])
            {
                continue;
            }

            if (!$this->ecrireImageEtMiniature($this->fichierEnvoye($champ), $nom, $this->repertoireUploads(), $miniature))
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

        // Les noms de colonnes viennent de champsImage(), jamais d'une saisie
        $affectations = [];
        $params = [':id' => $this->ficheId];
        foreach ($nomsFichiers as $champ => $nom)
        {
            $affectations[] = $champ . " = :" . $champ;
            $params[':' . $champ] = ($nom === '') ? $this->valeurImageAbsente() : $nom;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE " . $this->table() . " SET " . implode(', ', $affectations)
            . " WHERE " . $this->colonneId() . " = :id"
        );
        $stmt->execute($params);

        $this->valeursEnBase = array_merge($this->valeursEnBase, $nomsFichiers);
    }
}
