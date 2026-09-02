<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\Utils\Text;
use PDO;
use Ladecadanse\HasDocuments;

class Organisateur extends Element
{
    use HasDocuments;

    // TODO: rn to $documentsSystemDirPath ?
    public static $systemDirPath;
    // TODO: rn to $documentsUrlDirPath ?
    public static $urlDirPath;

    // TODO: explain better utility in var. name or context
    // TODO: mv to a future OrganisateursRenderer class ?
    public const int LOW_ACTIVITY_MONTHS_NB = 6;
    public const int VERY_LOW_ACTIVITY_MONTHS_NB = 12;
    public const int RESULTS_PER_PAGE = 100;

    /**
     * Contraintes de saisie des champs du formulaire d'édition.
     *
     * Partagées par la validation serveur (OrganisateurEdition::verification()) et par
     * les attributs du formulaire (organisateur/edit.php) : chacun les déclarait de son
     * côté, sans que rien ne garantisse qu'ils disent la même chose — le maxlength d'un
     * champ pouvait laisser saisir ce que la validation refuserait ensuite.
     *
     * `type` est celui qu'attend Validateur::valider().
     *
     * @var array<string, array{type: string, min: int, max: int, required: bool}>
     */
    public const array FIELDS = [
        'nom'          => ['type' => 'texte', 'min' => 1,  'max' => 80,    'required' => true],
        'adresse'      => ['type' => 'texte', 'min' => 1,  'max' => 80,    'required' => false],
        'URL'          => ['type' => 'url',   'min' => 2,  'max' => 100,   'required' => false],
        'email'        => ['type' => 'email', 'min' => 4,  'max' => 100,   'required' => false],
        'presentation' => ['type' => 'texte', 'min' => 20, 'max' => 10000, 'required' => false],
    ];

    /**
     * Valeurs de la colonne `statut` (ENUM), avec le libellé montré aux éditeurs.
     *
     * « Publié / Dépublié » plutôt que « Actif / Inactif » : c'est de la visibilité
     * de la fiche qu'il s'agit, pas de l'activité de l'organisateur — que « Ancien »,
     * lui, désigne bien.
     */
    public const array STATUTS = [
        'actif' => 'Publié',
        'inactif' => 'Dépublié',
        'ancien' => 'Ancien',
    ];

    function __construct()
	{
        parent::__construct();
		$this->table = "organisateur";
	}

    /**
     * Used in various pages needing orgas lists of an event or an user : gererEvenement, users in admin and evenement, evenementRenderer, Organisateur, user dashboard
     * TODO: mv to a LieuxRenderer class
     */
    public static function getListLinkedHtml(array $organisateurs, bool $isWithOrganisateurUrl = true): string
    {
        ob_start();
        ?>
        <ul class="event_orga" aria-label="Organisateurs">
            <?php foreach ($organisateurs as $eo) : ?>
                <li>
                    <a href="/organisateur/organisateur.php?idO=<?= (int) $eo['idOrganisateur']; ?>"><?= sanitizeForHtml($eo['nom']); ?></a>
                        <?php if ($isWithOrganisateurUrl && !empty($eo['url'])) { $organisateurUrl = Text::getUrlWithName($eo['url']); ?> -&nbsp;<a href="<?= sanitizeForHtml($organisateurUrl['url']); ?>" title="Site web de l'organisateur" rel="external" target="_blank"><?= sanitizeForHtml($organisateurUrl['urlName']); ?></a>
                    <?php } ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    /**
     * Organisateurs actifs, dans l'ordre alphabétique des noms sans leur article, tels que les
     * proposent les <select> des formulaires d'événement.
     *
     * @return list<array{idOrganisateur: int, nom: string, URL: string}>
     */
    public static function getActifsPourSelect(): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT idOrganisateur, nom, URL FROM organisateur WHERE statut='actif'
            ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Les <option> du <select> multiple « Organisateur(s) », partagées par les formulaires
     * d'événement. `data-nom` et `data-complement` alimentent le select2 « with complement »,
     * qui affiche l'URL de l'organisateur sous son nom.
     *
     * @param list<int|string> $idsSelectionnes
     */
    public static function getOptionsHtml(array $idsSelectionnes = []): string
    {
        return self::renderOptions(self::getActifsPourSelect(), $idsSelectionnes);
    }

    /**
     * Rendu pur des options, sans base de données ni globale : c'est ce que couvrent les tests.
     *
     * @param list<array{idOrganisateur: int|string, nom: string, URL: string|null}> $organisateurs
     * @param list<int|string> $idsSelectionnes
     */
    public static function renderOptions(array $organisateurs, array $idsSelectionnes = []): string
    {
        $selection = array_map('strval', $idsSelectionnes);

        $html = '';
        foreach ($organisateurs as $orga)
        {
            $coche = in_array((string) $orga['idOrganisateur'], $selection, true) ? ' selected="selected"' : '';
            $html .= '<option data-nom="' . sanitizeForHtml($orga['nom']) . '"'
                . ' data-complement="' . sanitizeForHtml((string) ($orga['URL'] ?? '')) . '"'
                . ' value="' . (int) $orga['idOrganisateur'] . '"' . $coche . '>'
                . sanitizeForHtml($orga['nom']) . '</option>';
        }

        return $html;
    }

    /*
     * lieux managed by the organisateur; only used in organisateur page
     * mv to a LieuRepository::getActivesByOrganisateur
     */
    public static function getActivesLieux(int $idOrga): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT l.idLieu AS idLieu, l.nom AS nom
            FROM lieu_organisateur lo
            JOIN lieu l ON lo.idLieu = l.idLieu AND l.statut = 'actif'
            WHERE lo.idOrganisateur=?");
        $stmt->execute([$idOrga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Only used for orgas table in organisateurs.php page
     * mv to a OrganisateurRepository::getList or ::find
     */
    public static function getOrganisateurs(array $filters, string $order = 'date_ajout', ?int $page = 1): array
    {
        global $connectorPdo;

        $params = [':statut' => $filters['statut']];

        if (!empty($filters['nom']))
        {
            $params[':nom'] = "%" . $filters['nom'] . "%";
        }

        // build SQL
        $orderMap = [
            'date_ajout' => 'o.date_ajout DESC',
            'nom'        => 'o.nom ASC',
        ];

        $sql_event = "SELECT
          o.*
        FROM organisateur o
        WHERE o.statut = :statut";

        if (!empty($filters['nom']))
        {
            $sql_event .= " AND o.nom LIKE :nom";
        }

        $sql_event .= " ORDER BY ". $orderMap[$order] ?? $orderMap['date_ajout'];

        if (!empty($page))
        {
            $sql_event .= " LIMIT " . (int) (($page - 1) * self::RESULTS_PER_PAGE) . ", " . (int) self::RESULTS_PER_PAGE; // (($page - 1) * self::RESULTS_PER_PAGE +
        }

        //echo $sql_event;
        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}