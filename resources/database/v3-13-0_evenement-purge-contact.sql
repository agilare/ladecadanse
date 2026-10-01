-- 3.13.0 — durée de conservation des coordonnées des propositions anonymes
--
-- Le formulaire public « Proposer un événement » garde l'adresse du visiteur dans
-- `evenement.user_email` et sa note à l'administrateur dans `evenement.remarque`. Rien ne les
-- effaçait : elles restaient attachées à des événements passés depuis des années. La durée de
-- conservation est de deux ans après la date de l'événement.
--
-- Ce script est le rattrapage, à passer une fois. La suite est tenue par l'application :
-- `Ladecadanse\EventContactRetention` purge au fil des pages vues les événements qui franchissent
-- le seuil, mais seulement sur une fenêtre de 90 jours — `evenement` est en MyISAM, et un UPDATE
-- qui parcourrait toute la table à chaque passage la verrouillerait d'autant. Tout ce qui est plus
-- ancien que la fenêtre n'est effacé que par ce script.
--
-- `remarque` est effacée aussi sans adresse : même nature de donnée, et le script reste idempotent.
-- `date_derniere_modif` n'est pas touchée : le contenu de l'événement ne change pas.
--
-- Indépendant du code : peut passer avant la mise en ligne, avec elle ou après. MyISAM : verrou
-- d'écriture le temps de l'UPDATE, à passer hors des heures de saisie.

UPDATE evenement
SET user_email = NULL, remarque = NULL
WHERE dateEvenement < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
  AND (user_email IS NOT NULL OR remarque IS NOT NULL);
