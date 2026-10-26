<?php
require_once("../app/bootstrap.php");

use Ladecadanse\EventCategory;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Localite;
use Ladecadanse\Organisateur;
use Ladecadanse\Personne;
use Ladecadanse\UserSettings;

// =============================================================================
// Accès
// =============================================================================

/*
 * Tout compte modifie le sien, MEMBER (12) compris. Sans session, direction la page de
 * connexion : un 403 envoyé avant n'y changeait rien, l'en-tête Location le remplaçant
 * d'office par un 302.
 */
if (!$authorization->checkGroup(UserLevel::MEMBER)) {
    header("Location: /user/login.php");
    die();
}

/*
 * La page ne modifie qu'un compte existant : les comptes se créent par user/register.php. Le
 * paramètre `action` des liens existants (« editer ») n'a plus de rôle, il est simplement ignoré.
 */
$get = [
    'idP' => (int) ($_GET['idP'] ?? 0),
];

$is_superadmin = (int) $_SESSION['Sgroupe'] === UserLevel::SUPERADMIN;

// Chaque refus est rendu dans la page du site par _erreur_http.inc.php
$http_error = null;

if ($get['idP'] <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun compte n'est désigné"];
}
elseif (!$is_superadmin && (int) $_SESSION['SidPersonne'] !== $get['idP'])
{
    $http_error = [403, 'Forbidden', "Vous n'avez pas les droit pour effectuer cette action"];
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

/*
 * Le compte modifié, relu à l'affichage comme à la soumission. Sans ce contrôle, un
 * identifiant inconnu rendait un formulaire vide, et l'UPDATE qui suivait ne touchait
 * aucune ligne en annonçant une réussite, puis rattachait des organisateurs à ce compte
 * inexistant. Il fournit aussi l'adresse, les réglages et les colonnes que le formulaire
 * ne montre pas à tous.
 */
$stmt = $connectorPdo->prepare("SELECT * FROM personne WHERE idPersonne = ?");
$stmt->execute([$get['idP']]);
$stored_personne = $stmt->fetch(PDO::FETCH_ASSOC);

if ($stored_personne === false)
{
    $http_error = [404, 'Not Found', "Cet utilisateur n'existe pas ou plus"];
    include("../_erreur_http.inc.php");
    exit;
}

/*
* flag pour l'affichage des champs et aussi leur traitement
* MEMBER et + : identification, mot de passe, e-mail
* ACTOR et + : affiliations, signature des événements ajoutés, valeurs par défaut du formulaire d'ajout
*/
$areContributorFieldsEnabled = (int) $_SESSION['Sgroupe'] <= UserLevel::ACTOR;

/*
* Rattachements à un lieu ou à un organisateur : ils donnent le droit de modifier ses fiches et
* ses événements. Un ACTOR les voit, en lecture seule, mais ne les choisit pas — sans quoi il
* se rattacherait à n'importe quelle fiche en postant « affiliations[]=orga:123 ». Hors de ce
* drapeau, $_POST['affiliations'] est ignoré et rien n'est écrit dans affiliation ni
* personne_organisateur.
*/
$canEditAffiliations = (int) $_SESSION['Sgroupe'] <= UserLevel::AUTHOR;

/**
 * Rattachements d'un compte : son lieu (table affiliation) et ses organisateurs.
 *
 * Relus avant puis après l'enregistrement. C'est leur différence qui décide d'un
 * e-mail d'information, et non la seule case cochée — enregistrer une autre partie
 * du formulaire n'a pas à prévenir de rattachements inchangés. Le couple
 * DELETE/INSERT de personne_organisateur, plus bas, efface l'état antérieur : il
 * faut donc le lire avant. Relus aussi à la place du POST pour qui ne peut pas les choisir.
 *
 * @return array{lieu: int, organisateurs: int[]}
 */
$getUserAffiliationsIds = function (int $idP) use ($connectorPdo): array
{
    $stmt = $connectorPdo->prepare("SELECT idAffiliation FROM affiliation WHERE idPersonne = ? AND genre = 'lieu'");
    $stmt->execute([$idP]);
    $lieuId = (int) $stmt->fetchColumn();

    $stmt = $connectorPdo->prepare("SELECT idOrganisateur FROM personne_organisateur WHERE idPersonne = ? ORDER BY idOrganisateur");
    $stmt->execute([$idP]);

    return ['lieu' => $lieuId, 'organisateurs' => array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))];
};

/*
* Valeurs par défaut à l'ajout d'un événement (fieldset « Événements »).
*
* Volontairement tenues hors de $champs, dont l'hydratation recopie $_POST : une clé
* "settings" y reviendrait à écrire en base le JSON brut envoyé par le client. La colonne n'est
* renseignée qu'en fin de validation, à partir de ces valeurs normalisées.
*/
$ev_defaults = UserSettings::eventNewDefaults(null);

// Dans l'ordre du formulaire
$champs = [
    "statut" => '',
    "pseudo" => '',
    "email" => '',
    "groupe" => '',
    'lieu' => '',
    'organisateurs' => '',
    "affiliation" => '',
    "signature" => 'pseudo',
    "avec_affiliation" => 'non',
];

/*
 * Les seules colonnes de `personne` que ce formulaire écrit, dans une liste littérale : les noms
 * de colonnes de l'UPDATE n'en sortent jamais. settings ne rejoint $champs qu'une fois la saisie
 * validée. Le mot de passe se change dans user/pwd-edit.php (#249).
 */
$personne_columns = ['pseudo', 'affiliation', 'email', 'groupe', 'signature', 'avec_affiliation', 'statut', 'settings'];

$user_levels = UserLevel::getConstants();

$verif = new Validateur();

$is_form_submitted = isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok';


// =============================================================================
// Traitement
//
// Trois temps : lire la saisie et la normaliser, la valider, puis l'enregistrer. Tout se
// joue avant l'inclusion de _header.inc.php : un enregistrement réussi se termine par une
// redirection vers la fiche du profil (POST/Redirect/GET), qui suppose que rien n'a encore
// été envoyé au navigateur.
// =============================================================================

if ($is_form_submitted)
{
    // -------------------------------------------------------------------------
    // 1. Lecture de la saisie
    //
    // Ce que le compte ne peut pas changer reprend ici sa valeur en base ou de session :
    // la validation et l'enregistrement ne voient plus que ce qui doit être écrit.
    // -------------------------------------------------------------------------

    // Les champs attendus seulement ; is_scalar écarte « pseudo[]=x », qu'aucun champ ne poste
    $champs = array_merge($champs, array_filter(array_intersect_key($_POST, $champs), 'is_scalar'));

    if (!$is_superadmin)
    {
        $champs['pseudo'] = $_SESSION['user'];
    }

    /*
     * Groupe et statut ne se choisissent qu'en superadmin ; pour les autres, ils restent ceux de
     * la session et de la base, quoi que porte le POST.
     *
     * Une valeur héritée hors de la liste (le groupe 10, le statut « demande ») est rendue cochée
     * mais désactivée : le navigateur ne la poste pas. Rien de posté vaut donc « inchangé ».
     */
    $stored_groupe = (int) $stored_personne['groupe'];
    $stored_statut = (string) $stored_personne['statut'];

    if (!$is_superadmin)
    {
        $champs['groupe'] = (int) $_SESSION['Sgroupe'];
    }
    elseif ($champs['groupe'] === '')
    {
        $champs['groupe'] = $stored_groupe;
    }

    if (!$is_superadmin || $champs['statut'] === '')
    {
        $champs['statut'] = $stored_statut;
    }

    /*
     * Colonnes que le formulaire n'a pas montrées : la base fait foi.
     *
     * Un champ absent du formulaire ne poste rien, et l'UPDATE écrit chaque colonne présente
     * dans $champs — l'affiliation et la signature d'un compte rétrogradé auraient été
     * remises à leur valeur par défaut au premier enregistrement, sans un mot.
     */
    if (!$areContributorFieldsEnabled)
    {
        $champs['affiliation'] = $stored_personne['affiliation'];
        $champs['signature'] = $stored_personne['signature'];
        $champs['avec_affiliation'] = $stored_personne['avec_affiliation'];
    }

    /*
     * Signature : deux cases à cocher, dont une décochée ne poste rien. La case du nom
     * d'utilisateur n'est rendue qu'aux comptes qui en ont un, ce que dit le témoin
     * signature_shown ; sans elle, la signature en base reste telle quelle. Les valeurs
     * anciennes « prenom » et « nomcomplet », sans effet, deviennent « aucune » au premier
     * enregistrement.
     */
    if ($areContributorFieldsEnabled)
    {
        $champs['avec_affiliation'] = !empty($_POST['avec_affiliation']) ? 'oui' : 'non';

        if (!empty($_POST['signature_shown']))
        {
            $champs['signature'] = !empty($_POST['signature']) ? 'pseudo' : 'aucune';
        }
        else
        {
            $champs['signature'] = $stored_personne['signature'];
        }
    }

    /*
     * Rattachements : une seule liste déroulante pour les lieux et les organisateurs (#102),
     * dont chaque valeur porte son type (Personne::parseAffiliationOption()). Le reste du
     * traitement raisonne sur $champs['lieu'] et $champs['organisateurs'] : ce sont deux tables
     * distinctes à écrire.
     */
    $champs['lieu'] = '';
    $champs['organisateurs'] = [];
    $lieux_postes = 0;

    if ($canEditAffiliations)
    {
        foreach ((array) ($_POST['affiliations'] ?? []) as $valeur)
        {
            $option = Personne::parseAffiliationOption($valeur);

            if ($option === null)
            {
                continue;
            }

            if ($option['type'] === 'lieu')
            {
                $lieux_postes++;
                $champs['lieu'] = $option['id'];
            }
            else
            {
                $champs['organisateurs'][] = $option['id'];
            }
        }
    }
    else
    {
        // Les rattachements en base, pour le ré-affichage en lecture seule ; ils ne sont pas réécrits.
        $affiliations_stockees = $getUserAffiliationsIds($get['idP']);
        $champs['lieu'] = $affiliations_stockees['lieu'] > 0 ? $affiliations_stockees['lieu'] : '';
        $champs['organisateurs'] = $affiliations_stockees['organisateurs'];
    }

    $ev_defaults = UserSettings::sanitizeEventNewDefaults( saisie: [
        'genre' => $_POST['ev_defaults_genre'] ?? '',
        'horaire_debut' => $_POST['ev_defaults_horaire_debut'] ?? '',
        'horaire_fin' => $_POST['ev_defaults_horaire_fin'] ?? '',
        'idLieu' => $_POST['ev_defaults_idLieu'] ?? 0,
        'idOrganisateurs' => $_POST['ev_defaults_organisateurs'] ?? [],
        'prix' => $_POST['ev_defaults_prix'] ?? '',
    ], genresAutorises: EventCategory::selectable(EventCategory::isEnabled()));

    // -------------------------------------------------------------------------
    // 2. Validation
    // -------------------------------------------------------------------------

    // Longueurs et obligation viennent de Personne::FIELDS, dont le formulaire tire aussi ses maxlength
    foreach (Personne::FIELDS as $field => $rule)
    {
        $verif->valider($champs[$field], $field, $rule['type'], $rule['min'], $rule['max'], $rule['required']);
    }

    // Seule la valeur déjà en base échappe à la liste des choix permis
    if ($is_superadmin && !in_array((string) $champs['groupe'], array_map('strval', array_merge(array_values($user_levels), [$stored_groupe])), true))
    {
        $verif->setErreur("groupe", "Choisissez un groupe parmi ceux proposés");
    }

    if (!in_array($champs['statut'], array_merge(Personne::$statuts, [$stored_statut]), true))
    {
        $verif->setErreur("statut", "Choisissez un statut parmi ceux proposés");
    }

    /*
     * Un seul lieu, autant d'organisateurs qu'on veut : la table `affiliation` ne porte
     * qu'une ligne « lieu » par personne, et $_SESSION['Saffiliation_lieu'] qu'un seul
     * identifiant — le second choisi écraserait le premier sans rien dire.
     */
    if ($lieux_postes > 1)
    {
        $verif->setErreur("affiliations", "Vous ne pouvez rattacher le compte qu'à un seul lieu ; les organisateurs, eux, peuvent être plusieurs.");
    }

    // Les selects et les <input type="time"> n'offrent que des valeurs valides : sanitize ci-dessus
    // ramène silencieusement à vide ce qui ne l'est pas. Les horaires font exception et sont
    // signalés, parce qu'un navigateur sans support de type="time" laisse saisir n'importe quoi.
    foreach (['horaire_debut' => 'de début', 'horaire_fin' => 'de fin'] as $cle => $libelle)
    {
        $horaire_saisi = isset($_POST['ev_defaults_' . $cle]) && is_string($_POST['ev_defaults_' . $cle]) ? trim($_POST['ev_defaults_' . $cle]) : '';

        if (!UserSettings::estHoraireValide($horaire_saisi))
        {
            $verif->setErreur('ev_defaults_' . $cle, "L'horaire " . $libelle . " par défaut doit être au format hh:mm");
        }
    }

    /*
     * Nom d'utilisateur changé par le superadmin : contrôlé seulement s'il change. Des comptes
     * anciens portent un nom partagé ou en forme d'adresse, et les refuser tels quels les
     * empêcherait d'enregistrer quoi que ce soit.
     */
    if ($champs['pseudo'] !== '' && $champs['pseudo'] !== (string) $stored_personne['pseudo'])
    {
        // vide, il est partagé par tous les comptes qui n'en ont pas : seul un nom renseigné est unique
        if (Personne::pseudoExists((string) $champs['pseudo'], $get['idP']))
        {
            // messages rendus tels quels par getHtmlErreur() et getErreur() : la saisie s'y échappe
            $verif->setErreur("pseudoIdentique", "Un membre ".sanitizeForHtml($champs['pseudo'])." existe déjà dans la base.");
        }

        /*
         * Un nom d'utilisateur en forme d'adresse rendrait ambiguë la saisie de connexion :
         * Sentry::checkLogin() cherche alors « pseudo OU email », et deux comptes peuvent
         * répondre — dont un sans pseudo, qui n'a plus alors aucun moyen de se connecter.
         */
        if (filter_var((string) $champs['pseudo'], FILTER_VALIDATE_EMAIL) !== false)
        {
            $verif->setErreur("pseudo", "Le nom d'utilisateur ne peut pas être une adresse e-mail.");
        }
    }

    /*
     * Unicité de l'adresse, seulement si elle change : un compte dont l'adresse est déjà
     * partagée — il en reste des centaines en production — ne pourrait sinon plus rien
     * enregistrer.
     */
    if (mb_strtolower(trim((string) $champs['email'])) !== mb_strtolower((string) $stored_personne['email'])
        && Personne::emailExists((string) $champs['email'], $get['idP']))
    {
        $verif->setErreur("emailIdentique", "Cette adresse e-mail est déjà celle d'un autre compte.");
    }

    // La clé est "global", rendue sous le décompte des erreurs pour tous les niveaux — sous
    // "pseudo", le refus ne s'affichait qu'aux superadmins, seuls à voir les erreurs du login.
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $verif->setErreur("global", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }

    // -------------------------------------------------------------------------
    // 3. Enregistrement, si la saisie est valide
    // -------------------------------------------------------------------------

    if ($verif->nbErreurs() === 0)
    {
        $affiliations_avant = $getUserAffiliationsIds($get['idP']);

        // Le fieldset « Événements » n'est rendu qu'aux comptes qui ajoutent des événements : on
        // ne touche à la colonne que là. Partir des réglages en base préserve ceux d'un autre
        // domaine, que ce formulaire n'expose pas.
        if ($areContributorFieldsEnabled)
        {
            $champs['settings'] = UserSettings::withEventNewDefaults(
                is_string($stored_personne['settings']) ? $stored_personne['settings'] : null,
                $ev_defaults
            );
        }

        // En chaînes, comme les écrivait l'ancienne requête concaténée : un NULL relu en base
        // ou un pseudo de session absent redevient '', que les colonnes NOT NULL acceptent
        $personne_values = array_map(
            fn (mixed $v): string => (string) $v,
            array_intersect_key($champs, array_flip($personne_columns))
        );
        $now = date("Y-m-d H:i:s");

        /*
         * Toutes les écritures ou aucune : l'effacement des organisateurs suivi d'une insertion
         * en échec laissait le compte sans aucun rattachement.
         */
        $pdo = $connectorPdo->getPDO();
        $pdo->beginTransaction();

        try
        {
            $idP_saved = $get['idP'];

            $stmt = $connectorPdo->prepare("UPDATE personne SET "
                . implode(', ', array_map(fn (string $c): string => $c . ' = :' . $c, array_keys($personne_values)))
                . ", date_derniere_modif = :date_derniere_modif WHERE idPersonne = :idPersonne");
            $stmt->execute($personne_values + ['date_derniere_modif' => $now, 'idPersonne' => $idP_saved]);

            // sans droit sur les rattachements, $champs['lieu'] est celui de la base : rien à réécrire
            if ($canEditAffiliations)
            {
                if (!empty($champs['lieu']))
                {
                    $stmt = $connectorPdo->prepare($affiliations_avant['lieu'] > 0
                        ? "UPDATE affiliation SET idAffiliation = :lieu WHERE idPersonne = :idP AND genre = 'lieu'"
                        : "INSERT INTO affiliation (idPersonne, idAffiliation, genre) VALUES (:idP, :lieu, 'lieu')");
                    $stmt->execute(['lieu' => (int) $champs['lieu'], 'idP' => $idP_saved]);
                }
                elseif ($affiliations_avant['lieu'] > 0)
                {
                    $stmt = $connectorPdo->prepare("DELETE FROM affiliation WHERE idPersonne = ? AND genre = 'lieu'");
                    $stmt->execute([$idP_saved]);
                }

                // Le couple efface-puis-réinsère ne vaut que si le select des organisateurs a
                // été rendu : sans lui, le POST n'en porte aucun et l'effacement serait sec.
                $stmt = $connectorPdo->prepare("DELETE FROM personne_organisateur WHERE idPersonne = ?");
                $stmt->execute([$idP_saved]);
            }

            /*
             * Le tableau peut être vide, tout désélectionner ne postant rien, et le DELETE qui
             * précède a alors suffi. Sans droit sur les rattachements, le tableau vient de la base
             * et rien n'a été effacé : le réinsérer doublerait les lignes.
             */
            if ($canEditAffiliations && $champs['organisateurs'] !== [])
            {
                $stmt = $connectorPdo->prepare("INSERT INTO personne_organisateur (idPersonne, idOrganisateur) VALUES (?, ?)");

                foreach ($champs['organisateurs'] as $idOrg)
                {
                    $stmt->execute([$idP_saved, (int) $idOrg]);
                }
            }

            $pdo->commit();
        }
        catch (Throwable $e)
        {
            $pdo->rollBack();
            throw $e;
        }

        /*
         * MAJ des infos perso de session si la personne s'autoédite
         */
        if ((int) $_SESSION['SidPersonne'] === $idP_saved)
        {
            $_SESSION["user"] = $champs['pseudo'];

            if (!empty($champs['groupe']))
            {
                $_SESSION['Sgroupe'] = $champs['groupe'];
            }

            // 0 comme Sentry quand le compte n'a pas de lieu : un lieu retiré cesse aussitôt de
            // donner ses droits, au lieu de les garder jusqu'à la prochaine connexion
            if ($canEditAffiliations)
            {
                $_SESSION["Saffiliation_lieu"] = (int) $champs['lieu'];
            }

            $message_succes = "Votre profil a été modifié";
        }
        else
        {
            $message_succes = "Le profil a été modifié";
        }

        $logger->info('[user-edit] user updated', ['pseudo' => $champs['pseudo'], 'idP' => $idP_saved, 'by' => (int) $_SESSION['SidPersonne']]);

        if ($canEditAffiliations && $champs['organisateurs'] !== [])
        {
            $logger->info('[user-edit] organisateurs updated', ['idP' => $idP_saved, 'idOrganisateurs' => $champs['organisateurs']]);
        }

        /*
         * Information du rattachement.
         *
         * L'inscription ne rattache plus personne à un lieu ou à un organisateur : elle
         * enregistre une déclaration, que l'équipe vérifie, et c'est ici que le compte gagne
         * ces droits. La personne ne l'apprendrait pas autrement.
         *
         * La case dit l'intention, la comparaison décide : elle n'est volontairement pas une
         * clé de $champs, dont l'UPDATE écrit les colonnes.
         */
        if (!empty($_POST['notify_affiliation']))
        {
            $affiliations_apres = $getUserAffiliationsIds($idP_saved);

            if ($affiliations_apres !== $affiliations_avant)
            {
                $noms = [];

                if ($affiliations_apres['lieu'] > 0)
                {
                    $stmt = $connectorPdo->prepare("SELECT nom FROM lieu WHERE idLieu = ?");
                    $stmt->execute([$affiliations_apres['lieu']]);
                    $nom_lieu = $stmt->fetchColumn();

                    if ($nom_lieu !== false)
                    {
                        $noms[] = (string) $nom_lieu;
                    }
                }

                if ($affiliations_apres['organisateurs'] !== [])
                {
                    list($orgas_clause, $orgas_params) = $connectorPdo->buildInClause('idOrganisateur', $affiliations_apres['organisateurs']);
                    $stmt = $connectorPdo->prepare("SELECT nom FROM organisateur WHERE $orgas_clause ORDER BY nom");
                    $stmt->execute($orgas_params);
                    $noms = array_merge($noms, $stmt->fetchAll(PDO::FETCH_COLUMN));
                }

                $mailer = new Mailing();
                $envoye = $mailer->toUser(
                    (string) $champs['email'],
                    "Votre compte sur La décadanse",
                    $tplEngine->render('user-affiliation-mail-body', [
                        'affiliations' => $noms === [] ? '' : implode(', ', $noms),
                        'dashboard_url' => $site_full_url . "user/dashboard.php?idP=" . $idP_saved,
                    ])
                );

                $message_succes .= $envoye
                    ? " La personne en a été informée par e-mail."
                    : " L'e-mail d'information n'a pas pu être envoyé.";

                $logger->info('[user-edit] affiliation notified', ['idP' => $idP_saved, 'envoye' => $envoye]);
            }
        }

        /*
        * Enregistrement terminé : le message part en session et l'utilisateur rejoint sa fiche.
        * Une requête en échec lève une exception, et la transaction l'a annulée : on n'arrive
        * ici qu'après un enregistrement complet.
        */
        $_SESSION['user_flash_msg'] = $message_succes;
        header("Location: /user/dashboard.php?idP=" . $idP_saved);
        die();

    } // if erreurs == 0
} // if POST != ""


// =============================================================================
// Préparation du rendu : toutes les lectures en base se font ici, avant l'en-tête
// =============================================================================

// Premier affichage : la base remplit le formulaire
if (!$is_form_submitted)
{
    foreach (array_keys($champs) as $n)
    {
        if (array_key_exists($n, $stored_personne))
        {
            $champs[$n] = $stored_personne[$n];
        }
    }

    $ev_defaults = UserSettings::eventNewDefaults(is_string($stored_personne['settings'] ?? null) ? $stored_personne['settings'] : null);

    $affiliations_stockees = $getUserAffiliationsIds($get['idP']);
    $champs['lieu'] = $affiliations_stockees['lieu'] > 0 ? $affiliations_stockees['lieu'] : '';
    $champs['organisateurs'] = $affiliations_stockees['organisateurs'];
}

/*
 * Choix rendus en boutons radio : les valeurs permises, plus celle du compte si elle n'en fait pas
 * partie (groupe 10 hérité, statut « demande »), cochée et désactivée.
 */
$groupe_choices = [];

foreach ($user_levels as $nom => $id)
{
    $groupe_choices[(int) $id] = ['label' => $id . ' : ' . $nom, 'locked' => false];
}

if (!isset($groupe_choices[(int) $stored_personne['groupe']]))
{
    $groupe_choices[(int) $stored_personne['groupe']] = ['label' => $stored_personne['groupe'] . ' : ancien niveau', 'locked' => true];
}

$statut_choices = Personne::$statuts;

if (!in_array($stored_personne['statut'], $statut_choices, true))
{
    $statut_choices[] = (string) $stored_personne['statut'];
}

if ($areContributorFieldsEnabled)
{
    /*
    * Rattachements présélectionnés, sous la forme que poste la liste : « lieu:42 », « orga:17 ».
    *
    * $champs porte déjà la bonne sélection dans tous les cas : au ré-affichage après une erreur,
    * celle qui a été postée, et elle seule — y ajouter ce que porte la base ferait revenir ce que
    * l'utilisateur vient de retirer ; au premier affichage, celle de la base, relue ci-dessus.
    */
    $affiliations_pers = [];

    if (!empty($champs['lieu']))
    {
        $affiliations_pers[] = 'lieu:' . (int) $champs['lieu'];
    }

    foreach ((array) $champs['organisateurs'] as $idOrg)
    {
        if ((int) $idOrg > 0)
        {
            $affiliations_pers[] = 'orga:' . (int) $idOrg;
        }
    }

    if ($canEditAffiliations)
    {
        $stmt = $connectorPdo->prepare("SELECT idLieu, nom FROM lieu WHERE statut = 'actif'
            ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $lieux_affiliables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else
    {
        // Organisateurs en lecture seule, avec leurs noms
        $organisateurs_rattaches = Personne::getOrganisateurs($get['idP']);
    }

    // Le lieu rattaché, lu même inactif : affiché en lecture seule, et nommé par la signature
    $lieu_rattache = null;

    if (!empty($champs['lieu']))
    {
        $stmt = $connectorPdo->prepare("SELECT idLieu, nom FROM lieu WHERE idLieu = ?");
        $stmt->execute([(int) $champs['lieu']]);
        $lieu_rattache = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
     * Aperçu de la signature, selon les règles de Personne::getSignatureHtml() : un nom
     * d'utilisateur en forme d'adresse ne signe pas, et le nom du lieu l'emporte sur le texte
     * libre. Rendu ici pour le premier affichage ; web/js/forms.js le tient à jour pendant la saisie.
     */
    $signature_pseudo = trim((string) $champs['pseudo']);
    $signature_signable_pseudo = Personne::looksLikeEmail($signature_pseudo) ? '' : $signature_pseudo;
    $signature_affiliation = $lieu_rattache['nom'] ?? trim((string) $champs['affiliation']);

    $signature_preview_html = $champs['signature'] === 'pseudo' && $signature_signable_pseudo !== ''
        ? '<strong>' . sanitizeForHtml($signature_signable_pseudo) . '</strong>'
        : '';

    if ($champs['avec_affiliation'] === 'oui' && $signature_affiliation !== '')
    {
        $signature_preview_html .= ($signature_preview_html === '' ? '' : ' ') . '(' . sanitizeForHtml($signature_affiliation) . ')';
    }

    // Mêmes organisateurs et même tri pour la liste des rattachements et pour les valeurs par défaut
    $organisateurs_actifs = Organisateur::getActifsPourSelect();

    // Mêmes lieux et même tri que le formulaire d'ajout d'événement, sans les salles (le
    // réglage se fait au niveau du lieu)
    $lieux_defaut = Lieu::getActifsPourSelect();
}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "modifier un compte";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne user-edit">

    <header id="entete_contenu">
        <h1>Modification du compte</h1>
        <div class="spacer"></div>
    </header>

    <?php
    if ($verif->nbErreurs() > 0)
    {
        HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s)");
        // les erreurs qu'aucun champ ne porte, comme le refus du jeton CSRF
        echo $verif->getHtmlErreur("global");
    }
    ?>


    <form method="post" id="ajouter_editer" enctype="multipart/form-data" class="js-submit-freeze-wait"
          action="<?= basename(__FILE__) . "?idP=" . $get['idP'] ?>" >

        <p>* indique un champ obligatoire</p>

        <?php if ($is_superadmin) : ?>

            <fieldset>
                <legend>Statut</legend>
                <ul class="radio">
                <?php foreach ($statut_choices as $s) : ?>
                    <li class="listehoriz">
                        <input type="radio" name="statut" value="<?= sanitizeForHtml($s) ?>"
                            <?php if ($s == $champs['statut']) : ?> checked="checked" <?php endif; ?>
                            <?php if (!in_array($s, Personne::$statuts, true)) : ?> disabled="disabled" <?php endif; ?>
                            id="statut_<?= sanitizeForHtml($s) ?>" class="radio_horiz" />
                        <label for="statut_<?= sanitizeForHtml($s) ?>" class="continu"><?= sanitizeForHtml($s) ?></label>
                    </li>
                <?php endforeach ?>
                </ul>
                <?= $verif->getHtmlErreur("statut") ?>
            </fieldset>

        <?php endif; ?>

        <fieldset>

            <legend>Compte</legend>
            <p>
                <label for="pseudo">Nom d'utilisateur</label>
                <?php if ($is_superadmin) : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="<?= Personne::FIELDS['pseudo']['max'] ?>" value="<?= sanitizeForHtml($champs['pseudo']) ?>" />
                    <?= $verif->getHtmlErreur('pseudo') ?>
                    <?= $verif->getHtmlErreur("pseudoIdentique") ?>
                <?php elseif (trim((string) $champs['pseudo']) !== '') : ?>
                    <input type="text" name="pseudo" id="pseudo" size="30" maxlength="<?= Personne::FIELDS['pseudo']['max'] ?>" value="<?= sanitizeForHtml($champs['pseudo']) ?>" readonly />
                <?php else : ?>
                    <?php /* Ni champ ni valeur : ce compte n'a pas de nom d'utilisateur */ ?>
                    <span class="sans-valeur">aucun</span>
                <?php endif; ?>
            </p>
            <div class="guideChamp">
                <?php if (trim((string) $champs['pseudo']) !== '' || $is_superadmin) : ?>
                    Permet de se connecter, comme l'e-mail, et peut signer les événements ajoutés
                <?php else : ?>
                    C'est votre e-mail qui vous identifie
                <?php endif; ?>
            </div>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="40" maxlength="<?= Personne::FIELDS['email']['max'] ?>" value="<?= sanitizeForHtml(stripslashes((string) $champs['email'])) ?>" required />
                <?= $verif->getHtmlErreur("email") ?>
                <?= $verif->getErreur("emailIdentique") ?>
            </p>
            <div class="guideChamp">Pour se connecter et recevoir un lien en cas de mot de passe oublié</div>

            <?php if ($is_superadmin) : ?>
                <?php /* sa légende tient la place d'un libellé, comme « Afficher » plus bas */ ?>
                <fieldset class="groupe-radios">
                    <legend>Groupe*</legend>
                    <ul class="radio mobile-vertical">
                    <?php foreach ($groupe_choices as $id => $choice) : ?>
                        <li class="listehoriz">
                            <input type="radio" name="groupe" value="<?= $id ?>" id="groupe_<?= $id ?>" class="radio_horiz"
                                <?php if ($id == $champs['groupe']) : ?> checked="checked" <?php endif; ?>
                                <?php if ($choice['locked']) : ?> disabled="disabled" <?php endif; ?> />
                            <label for="groupe_<?= $id ?>" class="continu"><?= sanitizeForHtml($choice['label']) ?></label>
                        </li>
                    <?php endforeach ?>
                    </ul>
                    <?= $verif->getHtmlErreur("groupe") ?>
                </fieldset>
            <?php endif; ?>
        </fieldset>

            <?php
            // Affiliations, signature et valeurs par défaut des événements ajoutés : ne parlent qu'aux
            // comptes qui en ajoutent (ACTOR et +)
            if ($areContributorFieldsEnabled) {
            ?>

            <fieldset id="organisateur-evenements">

                <legend>Organisateur d'événements</legend>

                <fieldset id="references">

                    <legend>Affiliation(s)</legend>

                    <?php if ($canEditAffiliations) : ?>
                    <p>
                        <label for="affiliations">Lieu et organisateurs</label>
                        <?php /* Un seul lieu peut être retenu, autant d'organisateurs qu'on veut; vérifié au traitement */ ?>
                        <select name="affiliations[]" id="affiliations" multiple class="js-select2-affiliations"
                                data-placeholder="Tapez un nom de lieu ou d'organisateur...">
                            <optgroup label="Lieux">
                            <?php foreach ($lieux_affiliables as $lieu_affiliable) : ?>
                                <?php $valeur = 'lieu:' . (int) $lieu_affiliable['idLieu']; ?>
                                <option value="<?= $valeur ?>"<?= in_array($valeur, $affiliations_pers, true) ? ' selected="selected"' : '' ?>><?= sanitizeForHtml($lieu_affiliable['nom']) ?></option>
                            <?php endforeach ?>
                            </optgroup>
                            <optgroup label="Organisateurs">
                            <?php foreach ($organisateurs_actifs as $organisateur_actif) : ?>
                                <?php $valeur = 'orga:' . (int) $organisateur_actif['idOrganisateur']; ?>
                                <option value="<?= $valeur ?>"<?= in_array($valeur, $affiliations_pers, true) ? ' selected="selected"' : '' ?>><?= sanitizeForHtml($organisateur_actif['nom']) ?></option>
                            <?php endforeach ?>
                            </optgroup>
                        </select>
                        <?= $verif->getHtmlErreur("affiliations"); ?>
                    </p>

                    <div class="guideChamp">
                        Un seul lieu, autant d'organisateurs que nécessaire. Le compte pourra modifier leurs fiches et les événements qui s'y rattachent
                    </div>

                    <p>
                        <label for="affiliation">Si pas dans la liste</label>
                        <input type="text" name="affiliation" id="affiliation" size="30" maxlength="<?= Personne::FIELDS['affiliation']['max'] ?>" value="<?= sanitizeForHtml($champs['affiliation']); ?>" />
                        <?= $verif->getHtmlErreur("affiliation"); ?>
                    </p>

                    <div class="guideChamp">Nom du lieu ou de l'organisateur représenté, en texte libre ; repris par la signature si aucun lieu n'est choisi</div>

                    <?php
                    /*
                     * L'inscription de l'utilisateur enregistre une déclaration, puis l'équipe la vérifie
                     * et peut ici lier le compte et enfin notifier l'utilisateur. Case dans la colonne des
                     * champs, libellé après elle : le texte est trop long pour la colonne des libellés.
                     */
                    if ((int) $_SESSION['SidPersonne'] !== $get['idP']) :
                    ?>
                    <p class="case-seule">
                        <input type="checkbox" name="notify_affiliation" id="notify_affiliation" value="1" class="checkbox" />
                        <label for="notify_affiliation" class="continu">Prévenir par e-mail du rattachement</label>
                    </p>
                    <?php endif; ?>

                <?php else : ?>

                    <div class="guideForm">Si vous souhaitez modifier ces informations merci de nous <a href="/misc/contacteznous.php">contacter</a></div>

                    <?php if (!empty($champs['affiliation'])) : ?>
                        <p>
                            <label for="affiliation">Affiliation</label>
                            <input name="affiliation" id="affiliation" type="text" readonly value="<?= sanitizeForHtml($champs['affiliation']); ?>" >
                        </p>
                    <?php endif ?>

                    <?php /* div et non p : un <ul> fermerait le paragraphe */ ?>
                    <?php if ($lieu_rattache !== null) : ?>
                        <div class="rattachements">
                            <label>Lieu</label>
                            <ul>
                                <li>
                                    <a href="/lieu/lieu.php?idL=<?= (int) $lieu_rattache['idLieu'] ?>"><?= sanitizeForHtml($lieu_rattache['nom']) ?></a>
                                </li>
                            </ul>
                        </div>
                        <div class="guideChamp">Vous pouvez modifier les informations de ce lieu et tous les événements qui s'y déroulent</div>
                    <?php endif ?>

                    <?php if ($organisateurs_rattaches !== []) : ?>
                        <div class="rattachements">
                            <label>Organisateur(s)</label>
                            <ul>
                                <?php foreach ($organisateurs_rattaches as $organisateur_rattache) : ?>
                                    <li><a href="/organisateur/organisateur.php?idO=<?= (int) $organisateur_rattache['idOrganisateur'] ?>"><?= sanitizeForHtml($organisateur_rattache['nom']) ?></a></li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                        <div class="guideChamp">
                            Vous pouvez modifier la fiche de ces organisateurs, tous les événements qui y sont associés ainsi que les lieux associés à ces organisateurs
                        </div>
                    <?php endif ?>

                <?php endif ?>
                <?= $verif->getHtmlErreur("affiliation"); ?>
                <?= $verif->getHtmlErreur("doublon_organisateur"); ?>
            </fieldset>



        <fieldset id="signature">

            <legend>Votre signature (en bas de la page d'un événement que vous avez ajouté)</legend>

            <fieldset class="groupe-radios">
                <legend>Afficher</legend>
                <ul class="radio mobile-vertical">
                    <?php
                    /* Un compte sans nom d'utilisateur n'a rien à signer : pas de case, et sa
                       signature en base reste telle quelle. Le témoin dit au traitement que la
                       case a été rendue, une case décochée ne postant rien. */
                    if ($signature_pseudo !== '') : ?>
                    <li>
                        <input type="hidden" name="signature_shown" value="1" />
                        <input type="checkbox" name="signature" value="pseudo" id="signature_pseudo" class="checkbox"
                            <?php if ($champs['signature'] === 'pseudo') : ?> checked="checked" <?php endif; ?> />
                        <label class="continu" for="signature_pseudo">votre identifiant « <b><?= sanitizeForHtml($signature_pseudo) ?></b> »</label>
                    </li>
                    <?php endif; ?>
                    <li>
                        <input type="checkbox" name="avec_affiliation" value="oui" id="avec_affiliation" class="checkbox"
                            <?php if ($champs['avec_affiliation'] === 'oui') : ?> checked="checked" <?php endif; ?> />
                        <label class="continu" for="avec_affiliation">avec votre affiliation (ci-dessus)</label>
                    </li>
                </ul>
            </fieldset>

            <p class="signature-apercu">
                <label>Aperçu</label>
                <output id="signature-preview" for="signature_pseudo avec_affiliation affiliations affiliation" aria-live="polite"
                    data-pseudo="<?= sanitizeForHtml($signature_signable_pseudo) ?>"
                    data-lieu="<?= sanitizeForHtml($canEditAffiliations ? '' : (string) ($lieu_rattache['nom'] ?? '')) ?>"><?= $signature_preview_html === '' ? '<i>aucune signature</i>' : $signature_preview_html ?></output>
            </p>

        </fieldset>

        <?php // Réglages du formulaire d'ajout d'événement ?>
        <fieldset>
            <legend>Événements</legend>

            <div class="guideForm">
                Valeurs par défaut dans le formulaire « Ajouter un événement ». Vous n'aurez plus besoin de saisir ces valeurs à chaque nouvel événement
            </div>
            <p>
                <label for="ev_defaults_genre">Catégorie</label>
                <select name="ev_defaults_genre" id="ev_defaults_genre">
                    <option value=""></option>
                    <?php foreach (EventCategory::selectable(EventCategory::isEnabled()) as $genre_cle => $genre_label) : ?>
                    <option value="<?= sanitizeForHtml($genre_cle) ?>" <?php if ((string) $genre_cle === $ev_defaults['genre']) { echo 'selected="selected"'; } ?>><?= sanitizeForHtml($genre_label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $verif->getHtmlErreur("ev_defaults_genre"); ?>
            </p>

            <p>
                <label for="ev_defaults_horaire_debut">Début</label>
                <input type="time" name="ev_defaults_horaire_debut" id="ev_defaults_horaire_debut" size="5" value="<?= sanitizeForHtml($ev_defaults['horaire_debut']); ?>" />
                <?= $verif->getHtmlErreur("ev_defaults_horaire_debut"); ?>
            </p>

            <p>
                <label for="ev_defaults_horaire_fin">Fin</label>
                <input type="time" name="ev_defaults_horaire_fin" id="ev_defaults_horaire_fin" size="5" value="<?= sanitizeForHtml($ev_defaults['horaire_fin']); ?>" />
                <?= $verif->getHtmlErreur("ev_defaults_horaire_fin"); ?>
            </p>

            <p>
                <label for="ev_defaults_idLieu">Lieu</label>
                <select name="ev_defaults_idLieu" id="ev_defaults_idLieu" class="js-select2-options-with-style" data-placeholder="">
                    <option value=""></option>
                    <?php
                    $canton_courant = null;

                    foreach ($lieux_defaut as $lieu_defaut)
                    {
                        $canton = (string) $lieu_defaut['canton'];

                        if ($canton !== $canton_courant)
                        {
                            if ($canton_courant !== null) { echo '</optgroup>'; }
                            echo '<optgroup label="' . (Localite::CANTONS[$canton] ?? sanitizeForHtml($canton)) . '">';
                            $canton_courant = $canton;
                        }

                        $selectionne = ((int) $lieu_defaut['idLieu'] === $ev_defaults['idLieu']) ? ' selected="selected"' : '';
                        echo '<option value="' . (int) $lieu_defaut['idLieu'] . '"' . $selectionne . '>' . sanitizeForHtml($lieu_defaut['nom']) . '</option>';
                    }

                    if ($canton_courant !== null) { echo '</optgroup>'; }
                    ?>
                </select>
                <?= $verif->getHtmlErreur("ev_defaults_idLieu"); ?>
            </p>

            <p>
                <label for="ev_defaults_organisateurs">Organisateur(s)</label>
                <select name="ev_defaults_organisateurs[]" id="ev_defaults_organisateurs" class="js-select2-options-with-style" multiple data-placeholder="Tapez les noms des organisateurs">
                    <?php
                    foreach ($organisateurs_actifs as $orga_defaut)
                    {
                        $selectionne = in_array((int) $orga_defaut['idOrganisateur'], $ev_defaults['idOrganisateurs'], true) ? ' selected="selected"' : '';
                        echo '<option value="' . (int) $orga_defaut['idOrganisateur'] . '"' . $selectionne . '>' . sanitizeForHtml($orga_defaut['nom']) . '</option>';
                    }
                    ?>
                </select>
                <?= $verif->getHtmlErreur("ev_defaults_organisateurs"); ?>
            </p>

            <p>
                <label for="ev_defaults_prix">Prix</label>
                <input type="text" name="ev_defaults_prix" id="ev_defaults_prix" size="45" maxlength="100" value="<?= sanitizeForHtml($ev_defaults['prix']); ?>" />
                <?= $verif->getHtmlErreur("ev_defaults_prix"); ?>
            </p>
        </fieldset>

            </fieldset>

        <?php
        } // areContributorFieldsEnabled
        ?>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="hidden" name="token" value="<?= SecurityToken::getToken(); ?>" />
            <input type="submit" value="Enregistrer" class="submit submit-big" />
        </p>

    </form>

</main>

<div id="colonne_gauche" class="colonne"></div>

<?php
include("../_footer.inc.php");
?>
