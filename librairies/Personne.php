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
     * Nom sous lequel désigner un compte là où seuls son titulaire et les administrateurs
     * lisent : son nom d'utilisateur, ou à défaut son adresse, qui est alors son seul
     * identifiant.
     *
     * Le nom d'utilisateur est facultatif depuis l'inscription simplifiée. Sans ce repli,
     * les listes d'administration et la fiche de profil rendaient un lien au texte vide,
     * invisible et incliquable. En public, rien ne remplace un nom absent : voir
     * getSignatureHtml(), qui ne signe alors pas.
     */
    public static function displayName(?string $pseudo, ?string $email = null): string
    {
        $pseudo = trim((string) $pseudo);

        return $pseudo !== '' ? $pseudo : trim((string) $email);
    }


    /**
     * Cette adresse est-elle déjà celle d'un compte ?
     *
     * Unicité tenue par l'application, faute d'index UNIQUE : la production porte des
     * centaines d'adresses partagées, héritées. Le contrôle arrête donc leur nombre sans
     * rien exiger de l'existant, et il reste une fenêtre de course entre lui et l'écriture.
     *
     * $exceptIdPersonne écarte le compte en cours de modification, sans quoi enregistrer
     * son profil sans toucher à l'adresse se refuserait tout seul.
     *
     * La comparaison est celle de la base : la collation utf8mb4_unicode_ci ignore la casse
     * et les espaces finales, un LOWER() n'ajouterait rien.
     */
    public static function emailExists(string $email, int $exceptIdPersonne = 0): bool
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT 1 FROM personne
            WHERE email = :email AND idPersonne <> :idP LIMIT 1");
        $stmt->execute([':email' => $email, ':idP' => $exceptIdPersonne]);

        return $stmt->fetchColumn() !== false;
    }


    /**
     * Ce nom d'utilisateur est-il déjà pris ?
     *
     * Appelé seulement sur un pseudo non vide : celui-ci est facultatif depuis
     * l'inscription simplifiée, et la chaîne vide est partagée par tous les comptes qui
     * n'en ont pas.
     */
    public static function pseudoExists(string $pseudo, int $exceptIdPersonne = 0): bool
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT 1 FROM personne
            WHERE pseudo = :pseudo AND idPersonne <> :idP LIMIT 1");
        $stmt->execute([':pseudo' => $pseudo, ':idP' => $exceptIdPersonne]);

        return $stmt->fetchColumn() !== false;
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

        // Un compte sans nom d'utilisateur ne signe pas. Sans ce contrôle la méthode rendait
        // « <strong></strong> », qui n'est pas une chaîne vide : les pages qui testent la
        // signature écrivaient « Ajouté par  le 3 mars », et la fiche de profil affichait du
        // vide là où elle annonce « aucune ».
        //
        // Un nom d'utilisateur qui est une adresse électronique ne signe pas non plus : la
        // signature est le seul endroit public où ce champ paraisse, et des comptes y ont mis
        // leur adresse — publiée telle quelle sous chacune de leurs annonces, à portée des
        // moissonneurs. Le champ étant en lecture seule hors SUPERADMIN (user-edit.php), ces
        // personnes ne pouvaient pas le corriger elles-mêmes.
        if ($auteur['signature'] === 'pseudo'
            && trim((string) $auteur['pseudo']) !== ''
            && !self::looksLikeEmail((string) $auteur['pseudo']))
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

    /**
     * Cette valeur ressemble-t-elle à une adresse électronique ?
     *
     * Volontairement plus large que `FILTER_VALIDATE_EMAIL` : il s'agit d'écarter ce qui se lit
     * comme une adresse, pas de valider une adresse. Une saisie mal formée — espace manquant,
     * domaine incomplet — reste lisible par un moissonneur, et doit donc être écartée aussi.
     *
     * Un point est exigé après l'arobase : sans lui, un pseudo de la forme « @dj_machin »
     * perdrait sa signature alors qu'il ne porte aucune adresse.
     */
    public static function looksLikeEmail(string $value): bool
    {
        return (bool) preg_match('/\S+@\S+\.\S+/', trim($value));
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

    /**
     * Anonymise un compte, en réponse à une demande d'effacement.
     *
     * La LPD (art. 6 al. 4) et le RGPD (considérant 26) admettent l'anonymisation en lieu
     * et place de la suppression : des données qui ne se rapportent plus à une personne
     * identifiable sortent du champ des deux textes. Le choix se justifie surtout ici par
     * ce qu'il évite — la ligne survit, donc aucun `evenement.idPersonne` ni
     * `descriptionlieu.idPersonne` ne se retrouve orphelin, et les annonces publiées
     * gardent leur place dans l'agenda sans garder leur auteur.
     *
     * Ce qui est vidé va au-delà des deux colonnes évidentes :
     *   - `pseudo` : getSignatureHtml() ne signe pas un compte sans nom, la signature
     *     publique des événements disparaît donc d'elle-même
     *   - `email` : remplacé plutôt que vidé, displayName() y retombe quand le nom manque
     *     et rendrait sinon un lien au texte vide dans les écrans d'administration. Le TLD
     *     `.invalid` est réservé par la RFC 2606, aucune adresse réelle ne peut le porter
     *   - `settings` : il porte le lieu et les organisateurs par défaut du formulaire
     *     d'ajout, qui désignent une personne aussi sûrement qu'un nom
     *   - `affiliation` : texte libre, le plus souvent le nom d'un lieu
     * et les lignes d'`affiliation`, de `personne_organisateur` et de
     * `user_reset_requests` partent pour la même raison : elles rattachent le compte à une
     * structure nommée, la dernière portant en outre l'adresse en clair.
     *
     * Irréversible, et voulu tel : une anonymisation qui se défait n'anonymise rien.
     *
     * Les tables liées d'abord : ces tables sont en MyISAM, donc sans transaction. Si
     * l'opération s'interrompt en chemin, la ligne `personne` porte encore ses valeurs et
     * un second appel reprend le travail — l'inverse laisserait des rattachements
     * pointant sur un compte déjà vidé, sans moyen de les retrouver.
     *
     * Ne traite pas les journaux applicatifs (var/logs/), qui gardent nom et adresse
     * jusqu'à leur rotation.
     */
    public static function anonymize(int $idPersonne): bool
    {
        global $connectorPdo;

        $stmt = $connectorPdo->prepare("SELECT email FROM personne WHERE idPersonne = :idP");
        $stmt->execute([':idP' => $idPersonne]);
        $currentEmail = $stmt->fetchColumn();

        if ($currentEmail === false)
        {
            return false;
        }

        foreach (['affiliation', 'personne_organisateur'] as $table)
        {
            // noms de tables littéraux, jamais une valeur reçue
            $stmt = $connectorPdo->prepare("DELETE FROM $table WHERE idPersonne = :idP");
            $stmt->execute([':idP' => $idPersonne]);
        }

        // idPersonne y est nullable : une demande de réinitialisation retrouvée par la
        // seule adresse survivrait à une suppression qui ne viserait que l'identifiant
        $stmt = $connectorPdo->prepare("DELETE FROM user_reset_requests WHERE idPersonne = :idP OR email = :email");
        $stmt->execute([':idP' => $idPersonne, ':email' => $currentEmail]);

        // le statut inactif suffit à les refuser, mais rien ne justifie de les garder
        $stmt = $connectorPdo->prepare("DELETE FROM remember_token WHERE user_id = :idP");
        $stmt->execute([':idP' => $idPersonne]);

        $stmt = $connectorPdo->prepare("UPDATE personne SET
            pseudo = '',
            email = :email,
            mot_de_passe = '',
            cookie = '',
            gds = '',
            affiliation = '',
            settings = NULL,
            signature = 'aucune',
            avec_affiliation = 'non',
            statut = 'inactif',
            actif = 0,
            last_login = NULL,
            date_derniere_modif = NOW()
            WHERE idPersonne = :idP");

        return $stmt->execute([
            ':email' => 'anonyme-' . $idPersonne . '@supprime.invalid',
            ':idP' => $idPersonne,
        ]);
    }
}
