<?php

declare(strict_types=1);

namespace Ladecadanse;

use InvalidArgumentException;

/**
 * Les catégories d'événement : la liste, le repli des catégories en préversion, et les
 * fragments SQL qui les trient et les replient en base.
 *
 * Deux catégories — « concerts » et « cours » — sont livrées derrière un drapeau à trois
 * états. Hors préversion, un événement stocké en `concerts` se lit et se range comme une
 * « fête », un `cours` comme un « divers » : ce n'est pas un masquage mais un rangement,
 * la colonne evenement.genre reste un varchar(20), et rétrograder le drapeau ne perd
 * aucune donnée.
 *
 * Les méthodes de règle prennent $withPreview sans valeur par défaut, et les trois
 * méthodes de décision sont les seules à consulter le drapeau. C'est délibéré : le flux
 * RSS est mis en cache pour tout le monde et l'API n'a pas de session, si bien qu'un
 * appel qui aurait pu omettre l'audience aurait servi la préversion d'un administrateur
 * de passage au public pendant un quart d'heure. Ici, l'oubli ne compile pas.
 *
 * Les clés restent en français : ce sont les valeurs stockées en base, pas des symboles.
 */
final class EventCategory
{
    /** Nom du drapeau qui commande les catégories en préversion, dans app/env.php. */
    public const string FLAG = 'EVENT_NEW_CATEGORIES_ENABLED';

    /**
     * Toutes les catégories : clé stockée en base => libellé affiché.
     *
     * L'ordre commande trois choses à la fois — les boutons radio des formulaires, les
     * onglets de filtre de l'agenda et l'ordre des sections de la liste du jour.
     *
     * @var array<string, string>
     */
    public const array ALL = [
        "fête"     => "fêtes",
        "concerts" => "concerts",
        "cinéma"   => "ciné",
        "théâtre"  => "théâtre",
        "expos"    => "expos",
        "cours"    => "cours/ateliers/stages",
        "divers"   => "divers",
    ];

    /**
     * Catégorie en préversion => catégorie publique sous laquelle elle se range.
     *
     * Ce tableau *est* la préversion : ses clés disent ce que le drapeau commande, ses
     * valeurs le repli qu'il impose. Ajouter une catégorie en préversion, c'est une
     * entrée ici et une dans ALL, rien d'autre.
     *
     * @var array<string, string>
     */
    public const array PREVIEW_FALLBACKS = [
        "concerts" => "fête",
        "cours"    => "divers",
    ];

    // --- Décision : qui consulte le drapeau ------------------------------------------

    /** Les catégories en préversion sont-elles ouvertes à l'utilisateur courant ? */
    public static function isEnabled(): bool
    {
        return FeatureFlag::estActive(self::FLAG);
    }

    /** Sont-elles montrées à titre de préversion, aux seuls administrateurs ? */
    public static function isInPreview(): bool
    {
        return FeatureFlag::estEnPreview(self::FLAG);
    }

    /**
     * Sont-elles ouvertes à tout le monde ?
     *
     * La question des surfaces sans utilisateur courant : le flux RSS, mis en cache dans
     * un fichier servi à tous ; l'API, authentifiée par mot de passe et sans session ; le
     * script de maintenance des valeurs par défaut.
     */
    public static function isOpenToAll(): bool
    {
        return FeatureFlag::estOuverteATous(self::FLAG);
    }

    // --- Règle : la décision étant prise ----------------------------------------------

    /**
     * Les catégories que l'on peut proposer à la saisie et au filtrage.
     *
     * @return array<string, string>
     */
    public static function selectable(bool $withPreview): array
    {
        return $withPreview ? self::ALL : array_diff_key(self::ALL, self::PREVIEW_FALLBACKS);
    }

    /**
     * La catégorie sous laquelle une catégorie stockée s'affiche et se groupe.
     *
     * Idempotente — l'appliquer deux fois ne change rien —, ce dont profitent les
     * appelants qui ne savent pas si la valeur vient de la base ou d'un premier repli.
     * Une catégorie inconnue est rendue telle quelle : c'est Evenement::categoryLabel()
     * qui décide de son libellé, comme avant.
     */
    public static function visible(?string $category, bool $withPreview): string
    {
        $category = (string) $category;

        return $withPreview ? $category : (self::PREVIEW_FALLBACKS[$category] ?? $category);
    }

    /**
     * La liste à présenter au formulaire d'un événement déjà enregistré.
     *
     * Un événement classé « concerts » que son auteur non administrateur vient modifier :
     * sans rien, aucun bouton n'est coché, le `required` force un choix, et la première
     * correction de faute de frappe efface le classement de la modération. La clé du
     * repli est donc remplacée, à sa place, par la catégorie stockée — le bouton garde le
     * libellé et le rang de « fêtes » mais poste « concerts », et se coche. Qui ne touche
     * pas au champ laisse la catégorie intacte ; qui choisit « ciné » applique « ciné ».
     *
     * @param string|null $storedCategory la valeur lue en base, jamais celle du POST
     * @return array<string, string>
     */
    public static function selectableForEdit(?string $storedCategory, bool $withPreview): array
    {
        $selectable = self::selectable($withPreview);
        $fallback = self::PREVIEW_FALLBACKS[(string) $storedCategory] ?? null;

        // Rien à conserver si la catégorie stockée est déjà proposée — c'est le cas en
        // préversion, où substituer écraserait l'entrée de son repli et ferait disparaître
        // « fêtes » de la liste.
        if ($fallback === null
            || array_key_exists((string) $storedCategory, $selectable)
            || !array_key_exists($fallback, $selectable))
        {
            return $selectable;
        }

        $withStored = [];
        foreach ($selectable as $key => $label)
        {
            $withStored[$key === $fallback ? (string) $storedCategory : $key] = $label;
        }

        return $withStored;
    }

    /**
     * L'expression SQL qui rend la catégorie visible d'une ligne.
     *
     * En préversion, la colonne telle quelle — aucun CASE, donc le plan d'exécution reste
     * celui d'aujourd'hui.
     */
    public static function sqlVisibleCategory(string $column, bool $withPreview): string
    {
        $column = self::column($column);

        if ($withPreview)
        {
            return $column;
        }

        $when = '';
        foreach (self::PREVIEW_FALLBACKS as $preview => $fallback)
        {
            $when .= ' WHEN ' . self::literal($preview) . ' THEN ' . self::literal($fallback);
        }

        return 'CASE ' . $column . $when . ' ELSE ' . $column . ' END';
    }

    /**
     * L'expression SQL qui donne son rang de tri à chaque catégorie, replis compris.
     *
     * Le rang est celui de la catégorie *visible* : hors préversion, « concerts » partage
     * le rang de « fête » et « cours » celui de « divers ». C'est ce qui fait que le tri
     * secondaire — dernier ajouté, ou heure de début — porte sur les deux catégories
     * ensemble : sans ce partage, MySQL rendrait toutes les fêtes puis tous les concerts,
     * et le groupe fusionné par PDO::FETCH_GROUP repartirait en arrière au milieu.
     *
     * Une catégorie hors liste ne reçoit aucun rang, donc NULL, qui trie en premier en
     * ASC : c'est le comportement d'aujourd'hui, conservé délibérément.
     */
    public static function sqlOrderByCategory(string $column, bool $withPreview): string
    {
        $ranks = array_flip(array_keys(self::selectable($withPreview)));

        $when = '';
        foreach (array_keys(self::ALL) as $key)
        {
            $visible = self::visible($key, $withPreview);

            if (!isset($ranks[$visible]))
            {
                continue;
            }

            $when .= ' WHEN ' . self::literal($key) . ' THEN ' . ($ranks[$visible] + 1);
        }

        return 'CASE ' . self::column($column) . $when . ' END';
    }

    /**
     * Ancre HTML d'une catégorie, à partir de son libellé.
     *
     * Text::stripAccents() ne retire que les diacritiques : « cours/ateliers/stages »
     * donnerait un id et un href portant des barres obliques. Les cinq catégories
     * historiques gardent l'ancre qu'elles avaient.
     */
    public static function anchor(string $label): string
    {
        return Utils\Text::slug($label);
    }

    /**
     * Un nom de colonne, éventuellement qualifié : `e.genre`, `genre`. Rien d'autre.
     *
     * Les deux fragments ci-dessus sont concaténés et non liés : ni le nom de colonne ni
     * les littéraux ne peuvent passer par un paramètre préparé. Aucun appelant ne leur
     * donne pourtant d'entrée utilisateur — on refuse ici tout ce qui ne ressemble pas à
     * ce que l'on attend, plutôt que de s'en remettre à la discipline du prochain
     * appelant.
     */
    private static function column(string $column): string
    {
        if (preg_match('/^[a-z_]+(\.[a-z_]+)?$/', $column) !== 1)
        {
            throw new InvalidArgumentException("Nom de colonne inattendu : " . $column);
        }

        return $column;
    }

    /**
     * Un littéral SQL à partir d'une clé de catégorie.
     *
     * Vingt caractères, la largeur d'evenement.genre ; ni apostrophe ni antislash ne
     * passent.
     */
    private static function literal(string $key): string
    {
        if (preg_match('/^[\p{L}\/ -]{1,20}$/u', $key) !== 1)
        {
            throw new InvalidArgumentException("Clé de catégorie inattendue");
        }

        return "'" . $key . "'";
    }
}
