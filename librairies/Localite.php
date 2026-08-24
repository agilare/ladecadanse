<?php

declare(strict_types=1);

namespace Ladecadanse;

use PDO;

class Localite
{
    /**
     * Genève, seule localité à se subdiviser en quartiers dans les formulaires.
     */
    public const int ID_GENEVE = 44;

    /**
     * Libellés des cantons de la table `localite`, dans l'ordre où les <optgroup> les présentent.
     *
     * À contre-courant de l'intuition, 'fr' désigne Fribourg et 'rf' la France ; 'hs' vaut
     * « ailleurs » (cf. $glo_regions dans app/config.php, qui ne connaît pas Fribourg).
     * Les localités fribourgeoises ne sont plus proposées à l'ajout mais restent affichables
     * en édition, d'où leur présence ici.
     */
    public const array CANTONS = [
        'ge' => 'Genève',
        'vd' => 'Vaud',
        'fr' => 'Fribourg',
        'rf' => 'France',
        'hs' => 'Autre',
    ];

    /**
     * Tri des localités d'un même canton : « Autre » — la commune qui n'est pas encore listée —
     * en fin de groupe, le reste par ordre alphabétique. Sans cette clause, l'« Autre » de
     * France finirait rangée entre Annemasse et Bordeaux au fil des ajouts.
     */
    private const string SQL_ORDRE_LOCALITES = "localite = 'Autre', localite";

    /**
     * Localités groupées par canton, pour le filtre de la liste des lieux.
     *
     * @return array<string, list<array{id: int, localite: string}>>
     */
    public static function getListByRegion(): array
    {
        global $connectorPdo;
        $stmt = $connectorPdo->prepare("SELECT canton, id, localite FROM localite WHERE canton != 'fr' ORDER BY " . self::sqlOrdreCantons() . ", " . self::SQL_ORDRE_LOCALITES);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_GROUP);
    }

    /**
     * Localités prêtes à peupler le <select> « Localité/quartier » des formulaires d'ajout et
     * d'édition, triées canton par canton dans l'ordre de self::CANTONS.
     *
     * $exclureFribourg reproduit le filtre appliqué à l'ajout : une localité fribourgeoise
     * n'est plus proposée à la création, mais reste sélectionnable en édition pour ne pas vider
     * le select d'un événement ou d'un lieu déjà rattaché à l'une d'elles.
     *
     * @return list<array{id: int, localite: string, canton: string}>
     */
    public static function getListPourSelect(bool $exclureFribourg = true): array
    {
        global $connectorPdo;

        // Fragment littéral, sans donnée saisie
        $where = $exclureFribourg ? " WHERE canton != 'fr' " : '';

        $stmt = $connectorPdo->prepare("SELECT id, localite, canton FROM localite " . $where . " ORDER BY " . self::sqlOrdreCantons() . ", " . self::SQL_ORDRE_LOCALITES);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Les <option> et <optgroup> du <select> « Localité/quartier », partagés par les trois
     * formulaires qui le portent : ajout/édition d'événement, formulaire de masse de
     * l'administration et ajout/édition de lieu. Le <select> lui-même reste dans chaque
     * formulaire, dont il porte les attributs (name, required, largeur…).
     *
     * $localiteId est la valeur à présélectionner : un id de localité, ou « 44_Pâquis » quand
     * un quartier de Genève a été choisi. $quartier ne sert qu'au chargement d'une fiche
     * existante, où localité et quartier arrivent dans deux colonnes distinctes.
     */
    public static function getOptionsHtml(string|int|null $localiteId, string|int|null $quartier = '', bool $exclureFribourg = true): string
    {
        global $glo_tab_quartiers2;

        return self::renderOptions(
            self::getListPourSelect($exclureFribourg),
            $glo_tab_quartiers2['ge'],
            (string) $localiteId,
            (string) $quartier
        );
    }

    /**
     * Rendu pur des options, sans base de données ni globale : c'est ce que couvrent les tests.
     *
     * @param list<array{id: int|string, localite: string, canton: string}> $localites
     * @param list<string> $quartiersGeneve
     */
    public static function renderOptions(array $localites, array $quartiersGeneve, string $localiteId, string $quartier = ''): string
    {
        // Après une erreur de saisie, le formulaire réaffiche la valeur postée telle quelle,
        // quartier compris ; il n'y a alors qu'un champ à relire.
        if (str_contains($localiteId, '_'))
        {
            [$localiteId, $quartier] = explode('_', $localiteId, 2);
        }

        $html = '<option value=""></option>';

        $canton_courant = null;
        foreach ($localites as $loc)
        {
            $canton = (string) $loc['canton'];
            if ($canton !== $canton_courant)
            {
                if ($canton_courant !== null)
                {
                    $html .= '</optgroup>';
                }
                $html .= '<optgroup label="' . sanitizeForHtml(self::CANTONS[$canton] ?? $canton) . '">';
                $canton_courant = $canton;
            }

            $selection = ((string) $loc['id'] === $localiteId && $quartier === '') ? ' selected="selected"' : '';
            $html .= '<option value="' . (int) $loc['id'] . '"' . $selection . '>' . sanitizeForHtml($loc['localite']) . '</option>';

            if ((int) $loc['id'] !== self::ID_GENEVE)
            {
                continue;
            }

            // Les quartiers de Genève suivent la ville, sous la même valeur composée
            // « id_quartier » que relisent les traitements de formulaire
            foreach ($quartiersGeneve as $quartier_propose)
            {
                $selection = ($localiteId === (string) self::ID_GENEVE && $quartier === $quartier_propose) ? ' selected="selected"' : '';
                $html .= '<option value="' . self::ID_GENEVE . '_' . sanitizeForHtml($quartier_propose) . '"' . $selection . '>Genève - ' . sanitizeForHtml($quartier_propose) . '</option>';
            }
        }

        if ($canton_courant !== null)
        {
            $html .= '</optgroup>';
        }

        return $html;
    }

    /**
     * Fragment ORDER BY rangeant les cantons dans l'ordre de self::CANTONS plutôt que dans
     * l'ordre alphabétique de leur code, qui ferait passer Fribourg avant Genève.
     *
     * Sert aussi aux <select> de lieux, dont les <optgroup> sont construits sur le canton de
     * la localité : deux cantons entremêlés y ouvriraient deux fois le même groupe.
     *
     * $expressionCanton est un fragment littéral écrit par l'appelant (nom de colonne ou
     * COALESCE), jamais une donnée saisie.
     */
    public static function sqlOrdreCantons(string $expressionCanton = 'canton'): string
    {
        $sql = 'CASE ' . $expressionCanton;
        foreach (array_keys(self::CANTONS) as $rang => $canton)
        {
            $sql .= " WHEN '" . $canton . "' THEN " . $rang;
        }

        return $sql . ' ELSE ' . count(self::CANTONS) . ' END';
    }
}
