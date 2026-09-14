-- 3.13.0 — sept catégories de plus pour la table `lieu`
--
-- Buvette, club, maison/espace de quartier, centre socioculturel, bibliothèque,
-- ludothèque, école/conservatoire : des lieux que l'agenda accueille depuis longtemps
-- sans savoir les dire, rangés jusqu'ici sous « salle » ou « autre ».
--
-- **L'ordre compte.** Un SET MariaDB est un masque de bits dont les positions viennent de
-- l'ordre de déclaration : `bistrot` vaut 1, `salle` 2, `restaurant` 4, et ainsi de suite.
-- Les sept valeurs sont donc **appendées après `autre`**, sans toucher aux neuf premières.
-- Glisser ne serait-ce qu'une valeur au milieu décalerait tous les bits suivants et
-- réinterpréterait silencieusement chaque ligne de la table — un cinéma deviendrait un
-- théâtre sans qu'aucune erreur ne le signale.
--
-- L'ordre de `Ladecadanse\Lieu::CATEGORIES` n'a pas cette contrainte et diffère
-- volontairement : il sert l'affichage du formulaire et garde « autre » en dernier. Ne pas
-- « ranger » le SET pour le faire correspondre au PHP.
--
-- La table est en MyISAM : l'ALTER la reconstruit et pose un verrou d'écriture. Négligeable
-- sur quelques centaines de lignes, mais à passer hors des heures de saisie.
--
-- Aucune valeur n'est retirée ni renommée : les lignes existantes gardent leur typage, et
-- le reclassement des lieux (sortir les maisons de quartier et les bibliothèques de
-- « salle » et « autre ») se fait ensuite à la main.
--
-- Relever avant de passer l'ALTER, puis après, et comparer — les deux sorties doivent être
-- rigoureusement identiques ; une divergence dit que l'ordre du SET a bougé :
--
--     SELECT categories, COUNT(*) AS nb FROM lieu GROUP BY categories ORDER BY categories;

ALTER TABLE `lieu`
    MODIFY `categories` SET(
        'bistrot','salle','restaurant','cinema','theatre','galerie','boutique','musee','autre',
        'buvette','club','quartier','socioculturel','bibliotheque','ludotheque','ecole'
    ) COLLATE utf8mb4_unicode_ci NOT NULL;
