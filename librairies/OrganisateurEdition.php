<?php

namespace Ladecadanse;

use Ladecadanse\Utils\DbConnectorPdo;
use Ladecadanse\Utils\Validateur;
use PDO;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Traitement du formulaire d'ajout et de modification d'un organisateur
 * (organisateur/edit.php).
 *
 * Passée sous PDO (issue #115) sur le modèle de SalleEdition : requêtes
 * préparées et colonnes nommées, à la place de l'Element générique qui
 * construisait son SET à partir des clés du tableau de valeurs.
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
    private Validateur $verif;
    private HtmlSanitizer $htmlSanitizer;
    private string $repUploads;

    /**
     * Noms des images enregistrées en base. Ils sont lus là et nulle part
     * ailleurs : le formulaire les postait dans des champs cachés, où n'importe
     * quelle valeur pouvait être glissée.
     *
     * @var array<string, string>
     */
    private array $imagesEnBase = ['logo' => '', 'photo' => ''];

    private string $nomEnBase = '';
    private string $statutEnBase = 'actif';

    private int $idPersonne = 0;

    /**
     * Le statut n'est proposé qu'aux administrateurs. Pour les autres, la valeur
     * postée est ignorée au profit de celle déjà en base : le champ caché du
     * formulaire annonçait « actif », si bien qu'un acteur modifiant une fiche
     * dépubliée la republiait sans le savoir.
     */
    private bool $statutModifiable = false;

    public function __construct()
    {
        global $rep_uploads_organisateurs;

        $champs = [
            'nom' => '',
            'adresse' => '',
            'URL' => '',
            'email' => '',
            'presentation' => '',
            'statut' => 'actif',
        ];

        parent::__construct('organisateur', $champs, ['logo' => [], 'photo' => []]);

        $this->pdo = DbConnectorPdo::getInstance();
        $this->verif = new Validateur();
        $this->repUploads = $rep_uploads_organisateurs;

        $this->htmlSanitizer = new HtmlSanitizer((new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('h3')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'title', 'target'])
            // TinyMCE (remove_script_host) écrit les liens internes en relatif (/lieu/lieu.php?idL=1),
            // sans ceci le href serait supprimé
            ->allowRelativeLinks(true)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer'));
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setIdPersonne(int $idPersonne): void
    {
        $this->idPersonne = $idPersonne;
    }

    public function setStatutModifiable(bool $statutModifiable): void
    {
        $this->statutModifiable = $statutModifiable;
    }

    /**
     * Nom de l'image enregistrée en base, pour l'aperçu du formulaire.
     */
    public function getImageEnBase(string $champ): string
    {
        return $this->imagesEnBase[$champ] ?? '';
    }

    /**
     * Nom tel qu'il est enregistré, pour le titre de la page : celui du
     * formulaire est la saisie en cours, qu'un envoi rejeté laisse à moitié faite.
     */
    public function getNomEnBase(): string
    {
        return $this->nomEnBase;
    }

    #[\Override]
    public function loadValeurs(int $id): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM organisateur WHERE idOrganisateur = :id");
        $stmt->execute([':id' => $id]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ligne === false)
        {
            return;
        }

        foreach (array_keys($this->valeurs) as $champ)
        {
            if (array_key_exists($champ, $ligne))
            {
                $this->valeurs[$champ] = $ligne[$champ];
            }
        }

        foreach (array_keys(self::IMAGES) as $champ)
        {
            $this->imagesEnBase[$champ] = (string) $ligne[$champ];
        }

        $this->nomEnBase = (string) $ligne['nom'];
        $this->statutEnBase = (string) $ligne['statut'];
        $this->id = $id;
        $this->idPersonne = (int) $ligne['idPersonne'];
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

        $this->supprimer = (isset($post['supprimer']) && is_array($post['supprimer'])) ? $post['supprimer'] : [];

        // Les images à remplacer sont celles de la base, pas celles que le POST annonce
        if ($this->action === 'update' && !$this->chargerEtatEnBase())
        {
            $this->verif->setErreur("nom", "Cet organisateur n'existe pas");
            return false;
        }

        if (!$this->statutModifiable)
        {
            $this->valeurs['statut'] = $this->action === 'update' ? $this->statutEnBase : 'actif';
        }

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

        $this->verif = new Validateur();

        $this->verif->valider($this->valeurs['nom'], "nom", "texte", 1, 80, 1);
        $this->verif->valider($this->valeurs['adresse'], "adresse", "texte", 1, 80, 0);
        $this->verif->valider($this->valeurs['URL'], "URL", "url", 2, 100, 0);
        $this->verif->valider($this->valeurs['email'], "email", "email", 4, 100, 0);
        $this->verif->valider($this->valeurs['presentation'], "presentation", "texte", 20, 10000, 0);
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
            ':idPersonne' => $this->idPersonne,
            ':dateAjout' => $maintenant,
            ':dateModif' => $maintenant,
        ]))
        {
            return false;
        }

        $this->id = (int) $this->pdo->lastInsertId();
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
            ':id' => $this->id,
        ]))
        {
            return false;
        }

        $this->message = "Organisateur modifié";

        $this->enregistrerLesImages();

        return true;
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

        if ((int) $this->id > 0)
        {
            $sql .= " AND idOrganisateur <> :id";
            $params[':id'] = $this->id;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Ce que la base dit déjà de la fiche : ses images, son nom, son statut.
     *
     * @return bool false si la fiche n'existe pas — un UPDATE sur un identifiant
     *              inconnu ne touche aucune ligne et réussit en silence
     */
    private function chargerEtatEnBase(): bool
    {
        $stmt = $this->pdo->prepare("SELECT nom, statut, logo, photo FROM organisateur WHERE idOrganisateur = :id");
        $stmt->execute([':id' => $this->id]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ligne === false)
        {
            return false;
        }

        $this->imagesEnBase = ['logo' => (string) $ligne['logo'], 'photo' => (string) $ligne['photo']];
        $this->nomEnBase = (string) $ligne['nom'];
        $this->statutEnBase = (string) $ligne['statut'];

        return true;
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
                $this->imagesEnBase[$champ],
                in_array($champ, $this->supprimer, true),
                (int) $this->id,
                $this->repUploads
            );

            if ($nom === $this->imagesEnBase[$champ])
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
        $params = [':id' => $this->id];
        foreach ($nomsFichiers as $champ => $nom)
        {
            $affectations[] = $champ . " = :" . $champ;
            $params[':' . $champ] = $nom;
        }

        $stmt = $this->pdo->prepare("UPDATE organisateur SET " . implode(', ', $affectations) . " WHERE idOrganisateur = :id");
        $stmt->execute($params);

        $this->imagesEnBase = array_merge($this->imagesEnBase, $nomsFichiers);
    }
}
