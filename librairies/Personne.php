<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use PDO;

/**
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class Personne
{
    public static $statuts = ['demande', 'actif', 'inactif'];

    public const int LOW_ACTIVITY_MONTHS_NB = 12;
    public const int VERY_LOW_ACTIVITY_MONTHS_NB = 24;

    public static function getPersonnesOfOrganisateur(int $idOrga): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT p.idPersonne AS idPersonne, pseudo, p.email AS email
            FROM personne_organisateur po
            JOIN personne p ON po.idPersonne = p.idPersonne
            WHERE po.idOrganisateur=?");
        $stmt->execute([$idOrga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function getPersonneById(int $idPersonne): ?array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo, email, groupe FROM personne WHERE idPersonne = ?");
        $stmt->execute([$idPersonne]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }


    /**
     * Réglages personnels bruts (JSON), à passer à UserSettings.
     *
     * Lecture ciblée plutôt que mise en session : Sentry ne place en session que des scalaires, et
     * y ajouter les réglages obligerait à toucher les trois requêtes de login tout en risquant de
     * servir une valeur périmée après modification du profil.
     */
    public static function getSettingsJson(int $idPersonne): ?string
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT settings FROM personne WHERE idPersonne = ?");
        $stmt->execute([$idPersonne]);
        $settings = $stmt->fetchColumn();

        return is_string($settings) ? $settings : null;
    }


    /**
     * Fiche de profil telle que l'affiche user/dashboard.php, en une requête.
     */
    public static function getProfil(int $idPersonne): ?array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo, email, affiliation, groupe, statut, dateAjout, settings
            FROM personne WHERE idPersonne = ?");
        $stmt->execute([$idPersonne]);
        $profil = $stmt->fetch(PDO::FETCH_ASSOC);

        return $profil === false ? null : $profil;
    }


    /**
     * Tous les lieux auxquels la personne est affiliée.
     *
     * La page de profil n'en lisait qu'un seul, alors qu'une personne peut en avoir plusieurs.
     */
    public static function getAffiliationsLieux(int $idPersonne): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT l.idLieu AS idLieu, l.nom AS nom
            FROM affiliation a
            JOIN lieu l ON a.idAffiliation = l.idLieu
            WHERE a.idPersonne = ? AND a.genre = 'lieu'
            ORDER BY l.nom");
        $stmt->execute([$idPersonne]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Organisateurs dont la personne est membre, au format attendu par
     * Organisateur::getListLinkedHtml() (clés idOrganisateur, nom, url).
     */
    public static function getOrganisateurs(int $idPersonne): array
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT o.idOrganisateur AS idOrganisateur, o.nom AS nom, o.URL AS url
            FROM personne_organisateur po
            JOIN organisateur o ON po.idOrganisateur = o.idOrganisateur
            WHERE po.idPersonne = ?
            ORDER BY o.nom");
        $stmt->execute([$idPersonne]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Signature d'auteur, telle qu'elle apparaît au bas d'un événement : « Juju (Bureau culturel) ».
     *
     * Le nom du lieu d'affiliation l'emporte sur le texte libre personne.affiliation. Cette méthode
     * remplace deux implémentations qui divergeaient sur ce point précis : celle d'evenement.php,
     * reprise ici, et HtmlShrink::authorSignatureForHtml(), supprimée, qui donnait la priorité au
     * texte libre.
     *
     * personne.signature est un enum à quatre valeurs, mais la table n'a ni prénom ni nom :
     * « prenom » et « nomcomplet » sont donc sans effet, comme « aucune ». Le code d'origine avait
     * déjà ce comportement, sans le dire.
     */
    public static function getSignatureHtml(int $idPersonne): string
    {
        global $connectorPdo;

        // LIMIT 1 : une personne peut avoir plusieurs affiliations, la signature n'en porte qu'une
        $stmt = $connectorPdo->prepare("SELECT p.pseudo, p.affiliation, p.signature, p.avec_affiliation,
            l.nom AS lieu_nom
            FROM personne p
            LEFT JOIN affiliation a ON a.idPersonne = p.idPersonne AND a.genre = 'lieu'
            LEFT JOIN lieu l ON a.idAffiliation = l.idLieu
            WHERE p.idPersonne = ?
            LIMIT 1");
        $stmt->execute([$idPersonne]);
        $auteur = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auteur === false)
        {
            return "";
        }

        $signature = "";

        if ($auteur['signature'] === 'pseudo')
        {
            $signature = "<strong>" . sanitizeForHtml($auteur['pseudo']) . "</strong>";
        }

        if ($auteur['avec_affiliation'] === 'oui')
        {
            $nom_affiliation = $auteur['lieu_nom'] ?? $auteur['affiliation'];

            if (!empty($nom_affiliation))
            {
                $signature .= ($signature === "" ? "" : " ") . "(" . sanitizeForHtml($nom_affiliation) . ")";
            }
        }

        return $signature;
    }


    public static function getPersonnes(array $filters, string $orderBy = 'dateAjout', string $orderDir = 'DESC', ?int $page = null, ?int $nbLignes = null): array
    {
        global $connectorPdo;

        // TODO: $params = [':statut' => $filters['statut']];
        $params = [];

        $where = '';
        if (!empty($filters['terme']))
        {
            $where = " WHERE (p.pseudo LIKE :terme OR p.email LIKE :terme2)";
            $params[':terme'] = "%" . $filters['terme'] . "%";
            $params[':terme2'] = "%" . $filters['terme'] . "%";
        }

        $limit = '';
        if (!empty($page))
        {
            $limit = " LIMIT " . (int) (($page - 1) * (int) $nbLignes) . ", " . (int) $nbLignes;
        }

        // TODO: sanitize $orderBy $orderDir
        // FIXME: replace left join with affiliation and lieu temp; create a separate query
        $sql_event = "SELECT
          p.*,
          l.idLieu AS idLieu,
          l.nom AS l_nom,
          DATE(p.dateAjout) AS dateAjout,
          DATE(p.last_login) AS last_login
          FROM personne p
          LEFT JOIN affiliation a ON p.idPersonne = a.idPersonne
          LEFT JOIN lieu l ON a.idAffiliation = l.idLieu
          $where ORDER BY $orderBy $orderDir $limit
           ";

        //echo $sql_event;
        $stmt = $connectorPdo->prepare($sql_event);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
