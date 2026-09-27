<?php

require_once("../app/bootstrap.php");

use Ladecadanse\Personne;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\Mailing;

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php : le message « formulaire
// expiré » était émis pendant le traitement, donc avant le doctype.
// =============================================================================

$formTokenName = 'form_token_user_register';

$verif = new Validateur();

/*
 * Deux comptes derrière un seul formulaire.
 *
 * Sans la case « contributor », le compte ne sert qu'à retrouver ses favoris : l'e-mail et
 * le mot de passe suffisent, et le niveau MEMBER (12) le tient à l'écart de tout ce qui
 * publie. Avec elle, le compte annoncera des événements sans délai de modération (ACTOR,
 * 8), et le formulaire demande alors de quoi signer ces annonces et savoir qui les écrit.
 *
 * Le nom d'utilisateur n'est donc demandé qu'à qui s'en servira : il signe les événements
 * ajoutés, et reste un identifiant de connexion à côté de l'adresse.
 */
$champs = [
    "email" => '',
    "motdepasse" => '',
    "contributor" => false,
    "login" => '',
    // valeur du select fusionné, de la forme « lieu:42 » ou « orga:17 »
    "affiliation_selected" => '',
    // nom saisi quand la liste ne le contient pas
    "affiliation" => '',
];

/*
 * La case peut être cochée d'avance par l'url : articles/annoncerEvenement.php envoie ici
 * les gens qui ont des événements à annoncer, ils n'ont pas à la retrouver eux-mêmes.
 */
if (!empty($_GET['contributor']))
{
    $champs['contributor'] = true;
}

$action_terminee = false;
$action_contributor = false;

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    // pot de miel : un champ masqué que seuls les robots remplissent
    if (!empty($_POST['username_as']))
    {
        $verif->setErreur("username_as", "Veuillez laisser vide le champ réservé aux robots.");
    }
    // le jeton reçu doit être celui déposé en session à l'affichage du formulaire
    else if (empty($_SESSION[$formTokenName]) || !hash_equals($_SESSION[$formTokenName], (string) ($_POST[$formTokenName] ?? '')))
    {
        $verif->setErreur("formulaire", "Le formulaire a expiré, veuillez le saisir à nouveau.");
    }
    else
    {
        unset($_SESSION[$formTokenName]);

        // is_scalar : ces champs du formulaire sont tous des valeurs simples, un POST
        // tableau ne doit pas se retrouver dans une validation
        foreach (['email', 'login', 'affiliation_selected', 'affiliation'] as $c)
        {
            if (isset($_POST[$c]) && is_scalar($_POST[$c]))
            {
                $champs[$c] = trim((string) $_POST[$c]);
            }
        }

        // le mot de passe n'est pas trimé : une espace initiale ou finale en fait partie
        if (isset($_POST['motdepasse']) && is_scalar($_POST['motdepasse']))
        {
            $champs['motdepasse'] = (string) $_POST['motdepasse'];
        }

        // une case décochée ne poste rien : c'est le témoin `formulaire` qui sépare
        // « décochée » de « premier affichage »
        $champs['contributor'] = !empty($_POST['contributor']);

        /**
         * Nom du lieu ou de l'organisateur choisi dans la liste.
         *
         * La valeur postée porte son type — « lieu:42 », « orga:17 » — parce que les deux
         * tables ont chacune leurs identifiants, qui se recouvrent. Le type est comparé à
         * une liste blanche : il décide de la table, jamais la saisie.
         *
         * @return string|false le nom, ou false si la valeur ne désigne aucune fiche active
         */
        $nomAffiliation = function (string $valeur) use ($connectorPdo): string|false
        {
            $cibles = [
                'lieu' => ['table' => 'lieu', 'id' => 'idLieu'],
                'orga' => ['table' => 'organisateur', 'id' => 'idOrganisateur'],
            ];

            [$type, $id] = array_pad(explode(':', $valeur, 2), 2, '');

            if (!isset($cibles[$type]) || (int) $id <= 0)
            {
                return false;
            }

            $cible = $cibles[$type];
            $stmt = $connectorPdo->prepare("SELECT nom FROM {$cible['table']}
                WHERE {$cible['id']} = :id AND statut = 'actif'");
            $stmt->execute([':id' => (int) $id]);
            $nom = $stmt->fetchColumn();

            return $nom === false ? false : (string) $nom;
        };

        /*
         * Validation. Les messages sont écrits pour cette page plutôt que repris de
         * Validateur, génériques et anglicisés (« Le texte est trop court : 1, min 2
         * characters ») — et surtout indiscernables les uns des autres dans l'encart
         * d'erreurs, qui les rassemble loin de leur champ.
         */

        // borne haute alignée sur la colonne personne.email
        if ($champs['email'] === '')
        {
            $verif->setErreur("email", "Veuillez saisir votre adresse e-mail.");
        }
        else if (filter_var($champs['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($champs['email']) > 100)
        {
            $verif->setErreur("email", "Cette adresse e-mail n'est pas valable.");
        }

        // un seul champ de mot de passe : le bouton « Afficher » remplace la confirmation
        foreach (PasswordPolicy::erreurs($champs['motdepasse']) as $champ => $message)
        {
            $verif->setErreur($champ, $message);
        }

        /*
         * Nom retenu pour l'affiliation, quelle que soit sa provenance. Il ne rattache rien :
         * c'est une déclaration, que l'équipe vérifie avant de lier le compte au lieu ou à
         * l'organisateur (voir plus bas, à l'insertion).
         */
        $affiliation_nom = '';

        // Les champs du fieldset dépliant ne sont lus que si la case est cochée. Un fieldset
        // masqué reste postable : sans cette garde, un compte de base pourrait se donner un
        // pseudo et une affiliation que le formulaire ne lui a pas demandés.
        if ($champs['contributor'])
        {
            if ($champs['login'] === '')
            {
                $verif->setErreur("login", "Veuillez choisir un nom d'utilisateur.");
            }
            else if (mb_strlen($champs['login']) < 2 || mb_strlen($champs['login']) > 80)
            {
                $verif->setErreur("login", "Votre nom d'utilisateur doit faire entre 2 et 80 caractères.");
            }
            // Un nom d'utilisateur en forme d'adresse rendrait ambiguë la saisie de
            // connexion : Sentry cherche alors « pseudo OU email », et deux comptes peuvent
            // répondre — dont un sans pseudo, qui n'aurait plus aucun moyen de se connecter.
            else if (filter_var($champs['login'], FILTER_VALIDATE_EMAIL) !== false)
            {
                $verif->setErreur("login", "Votre nom d'utilisateur ne peut pas être une adresse e-mail.");
            }
            else if (Personne::pseudoExists($champs['login']))
            {
                $verif->setErreur("login_existant", "Un membre avec ce nom d'utilisateur existe déjà, veuillez en choisir un autre.");
            }

            if ($champs['affiliation_selected'] !== '')
            {
                $nom_trouve = $nomAffiliation($champs['affiliation_selected']);

                if ($nom_trouve === false)
                {
                    $verif->setErreur("affiliation", "Ce lieu ou cet organisateur n'a pas été retrouvé, veuillez le choisir à nouveau dans la liste.");
                }
                else
                {
                    $affiliation_nom = $nom_trouve;
                }
            }
            else if ($champs['affiliation'] === '')
            {
                $verif->setErreur("affiliation", "Veuillez choisir votre lieu ou organisateur dans la liste, ou saisir son nom.");
            }
            else if (mb_strlen($champs['affiliation']) < 2 || mb_strlen($champs['affiliation']) > 250)
            {
                $verif->setErreur("affiliation", "Le nom de votre lieu ou organisateur doit faire entre 2 et 250 caractères.");
            }
            else
            {
                $affiliation_nom = $champs['affiliation'];
            }
        }

        if ($verif->nbErreurs() === 0)
        {
            /*
             * Un e-mail déjà pris ne se dit pas : la page rend alors le même message de
             * fin qu'une création réussie, sans rien insérer ni envoyer. L'annoncer
             * ferait de ce formulaire un oracle d'existence de compte.
             *
             * L'unicité est tenue ici, faute d'index UNIQUE : la production porte des
             * centaines d'adresses partagées, héritées (voir Personne::emailExists()).
             */
            if (!Personne::emailExists($champs['email']))
            {
                $maintenant = date("Y-m-d H:i:s");
                $groupe = $champs['contributor'] ? UserLevel::ACTOR : UserLevel::MEMBER;

                /*
                 * signature : un compte sans nom d'utilisateur n'a rien à signer, et le
                 * défaut de la colonne (« pseudo ») ferait rendre une signature vide sous
                 * les événements qu'il ajouterait après une promotion.
                 */
                $stmt = $connectorPdo->prepare("
                    INSERT INTO personne (pseudo, mot_de_passe, email, groupe, affiliation, signature, region, cookie, statut, dateAjout, date_derniere_modif)
                    VALUES (:pseudo, :mot_de_passe, :email, :groupe, :affiliation, :signature, '', '', 'actif', :dateAjout, :dateModif)");
                $stmt->execute([
                    ':pseudo' => $champs['contributor'] ? $champs['login'] : '',
                    ':mot_de_passe' => password_hash($champs['motdepasse'], PASSWORD_DEFAULT),
                    ':email' => $champs['email'],
                    ':groupe' => $groupe,
                    ':affiliation' => $affiliation_nom,
                    ':signature' => $champs['contributor'] ? 'pseudo' : 'aucune',
                    ':dateAjout' => $maintenant,
                    ':dateModif' => $maintenant,
                ]);

                $new_user_id = (int) $connectorPdo->lastInsertId();

                /*
                 * Aucune ligne dans `affiliation` ni `personne_organisateur` : elles sont
                 * écrites par l'équipe, depuis user-edit.php, une fois la déclaration
                 * vérifiée. Les poser ici donnait sur simple déclaration le droit de
                 * modifier la fiche du lieu et tous les événements qui s'y déroulent.
                 */

                $mailer = new Mailing();

                if ($champs['contributor'])
                {
                    $mailer->toUser(
                        $champs['email'],
                        "Votre nouveau compte 👤 " . $champs['login'] . " sur La décadanse",
                        $tplEngine->render("user-register-contributor-mail-body", [
                            'site_url' => $site_full_url,
                            'login_url' => $site_full_url . "user/login.php",
                            'login' => $champs['login'],
                            'affiliation' => $affiliation_nom,
                            'dashboard_url' => $site_full_url . "user/dashboard.php?idP=" . $new_user_id,
                        ])
                    );
                }
                else
                {
                    $mailer->toUser(
                        $champs['email'],
                        "Votre nouveau compte sur La décadanse",
                        $tplEngine->render("user-register-mail-body", [
                            'site_url' => $site_full_url,
                            'login_url' => $site_full_url . "user/login.php",
                            'favorites_url' => $site_full_url . "favoris.php",
                        ])
                    );
                }

                $logger->info('[user-register]', ['pseudo' => $champs['login'], 'email' => $champs['email'], 'groupe' => $groupe, 'idP' => $new_user_id]);
            }

            $action_terminee = true;
            $action_contributor = $champs['contributor'];

        } // if erreurs == 0
    }

} // if POST != ""


// =============================================================================
// Rendu
// =============================================================================

$page_titre = "Inscription";
$page_description = "Création d'un compte sur La décadanse";
// user/register.css est chargé automatiquement par _header.inc.php, d'après le nom de la page
$extra_css = ["formulaires"];
include("../_header.inc.php");

if (!$action_terminee)
{
    // jeton renouvelé à chaque affichage du formulaire
    $_SESSION[$formTokenName] = bin2hex(random_bytes(32));
}

$erreurs = $verif->getErreurs();
?>

<main id="contenu" class="colonne inscription">

    <header id="entete_contenu">
        <h1>S’inscrire sur La décadanse</h1>
        <div class="spacer"></div>
    </header>

<?php if ($action_terminee) : ?>

    <div class="msg_ok" role="status">
        <p><strong>Votre compte a été créé</strong> (si l'adresse e-mail fournie est valide).</p>
        <?php if ($action_contributor) : ?>
        <p>Vous pouvez maintenant vous <a href="/user/login.php">connecter</a> avec votre nom
            d'utilisateur ou votre adresse e-mail.</p>
        <p>Le lieu ou l'organisateur que vous avez indiqué sera rattaché à votre compte après
            vérification par notre équipe.</p>
        <?php else : ?>
        <p>Vous pouvez maintenant vous <a href="/user/login.php">connecter</a> avec votre
            adresse e-mail et le mot de passe que vous venez de saisir.</p>
        <?php endif; ?>
    </div>

<?php else : ?>

    <?php if ($erreurs !== []) : ?>
    <?php // les messages sont écrits ici, jamais repris d'une saisie : ils peuvent porter du HTML ?>
    <div class="msg_erreur" role="alert">
        <?php if (count($erreurs) === 1) : ?>
        <?= current($erreurs) ?>
        <?php else : ?>
        <p class="msg_erreur_titre"><?= $verif->getMsgNbErreurs() ?></p>
        <ul>
            <?php foreach ($erreurs as $message) : ?>
            <li><?= $message ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait">

        <input type="text" class="name_as" name="username_as" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">

        <div class="intro_form">Un compte vous permet de&nbsp;:
            <ul>
                <li>retrouver vos <a href="/favoris.php">favoris</a> d'un appareil à l'autre</li>
                <li>si vous organisez des événements, les ajouter vous-même et sans délai</li>
            </ul>
        </div>

        <p>* indique un champ obligatoire</p>

        <fieldset>

            <legend>Compte</legend>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="35" maxlength="100" value="<?= sanitizeForHtml($champs['email']) ?>" autocomplete="email" required<?= isset($erreurs['email']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

            <?php /* Un seul champ, avec de quoi relire sa saisie : la confirmation ne
                     protégeait que de la frappe en aveugle, et doublait l'effort. */ ?>
            <p>
                <label for="motdepasse">Mot de passe*</label>
                <span class="password-field">
                    <input type="password" name="motdepasse" id="motdepasse" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" autocomplete="new-password" required<?= isset($erreurs['motdepasse']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
                    <button type="button" class="js-toggle-password" data-target="motdepasse" aria-pressed="false" aria-label="Afficher le mot de passe" title="Afficher le mot de passe"><i class="fa fa-eye" aria-hidden="true"></i></button>
                </span>
                <?php /* dans le même paragraphe que le champ : l'aide est ainsi une case de la
                         grille, alignée sur la colonne des champs sans marge à recalculer */ ?>
                <span class="guide_champ">Au minimum <?= PasswordPolicy::LONGUEUR_MIN ?> caractères. Une phrase dont vous vous souvenez vaut mieux qu'un mot compliqué.</span>
            </p>

            <?php /* La case commande le niveau du compte et les champs demandés. Elle ferme le
                     cadre du compte, à la façon du « Resté connecté-e » de la page de connexion.
                     Le fieldset qu'elle déplie n'a pas d'attribut `required` : un champ requis
                     dans un bloc masqué bloque l'envoi sans message lisible. C'est le traitement
                     qui exige ses champs, et lui seul les lit. */ ?>
            <p class="contributor-choice">
                <label for="contributor">Peut ajouter des événements</label>
                <input type="checkbox" name="contributor" id="contributor" value="1" class="js-toggle-fieldset" data-target="contributor-fields"<?= $champs['contributor'] ? ' checked' : '' ?>>
            </p>

        </fieldset>

        <fieldset class="contributor-fields" id="contributor-fields">

            <legend>Avec profil d'organisateur d'événements</legend>

            <div class="guide_contributor">Avant de continuer, vérifiez svp&nbsp;:
                <ul>
                    <li>à ce que les événements que vous souhaitez ajouter respectent notre <b><a href="/articles/charte-editoriale.php">charte&nbsp;éditoriale</a></b> (les événements ne sont pas tous publiés)</li>
                    <li>si votre organisation n'a pas déjà un compte La décadanse</li>
                </ul>
                <p>Les événements annoncés sur La décadanse sont également visibles sur
                    <a href="https://epic-magazine.ch/" target="_blank">EPIC-Magazine</a>, webmagazine
                    qui met en avant la culture locale et émergente à Genève et dans ses environs.</p>
            </div>

            <p>
                <label for="login">Nom d'utilisateur*</label>
                <input type="text" name="login" id="login" size="40" minlength="2" maxlength="80" value="<?= sanitizeForHtml($champs['login']) ?>" autocomplete="username"<?= isset($erreurs['login']) || isset($erreurs['login_existant']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
                <span class="guide_champ">Il signe les événements que vous ajoutez (désactivable), et vous pourrez vous en servir pour vous connecter, comme de votre e-mail.</span>
            </p>

            <p>
                <label for="affiliation_selected" class="affil">Lieu ou organisateur*</label>
                <?php /* Une seule liste pour les deux, parce que la distinction n'est pas celle
                         de la personne qui s'inscrit. La valeur porte son type : les deux tables
                         ont leurs propres identifiants, qui se recouvrent. */ ?>
                <select name="affiliation_selected" id="affiliation_selected" class="js-select2-affiliations" data-placeholder="Tapez le nom..." style="max-width:350px"<?= isset($erreurs['affiliation']) ? ' aria-invalid="true"' : '' ?>>
                    <option value=""></option>
                    <optgroup label="Lieux">
                    <?php
                    $tri_nom = "TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les '
                        FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci";

                    $req_lieux = $connectorPdo->query("SELECT idLieu, nom FROM lieu WHERE statut='actif' ORDER BY " . $tri_nom);

                    foreach ($req_lieux as $lieuTrouve)
                    {
                        $valeur = 'lieu:' . (int) $lieuTrouve['idLieu'];
                        echo '<option value="' . $valeur . '"' . ($valeur === $champs['affiliation_selected'] ? ' selected="selected"' : '') . '>'
                            . sanitizeForHtml($lieuTrouve['nom']) . '</option>';
                    }
                    ?>
                    </optgroup>
                    <optgroup label="Organisateurs">
                    <?php
                    $req_organisateurs = $connectorPdo->query("SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY " . $tri_nom);

                    foreach ($req_organisateurs as $organisateurTrouve)
                    {
                        $valeur = 'orga:' . (int) $organisateurTrouve['idOrganisateur'];
                        echo '<option value="' . $valeur . '"' . ($valeur === $champs['affiliation_selected'] ? ' selected="selected"' : '') . '>'
                            . sanitizeForHtml($organisateurTrouve['nom']) . '</option>';
                    }
                    ?>
                    </optgroup>
                </select>
            </p>

            <p>
                <?php // trait d'union insécable : « ci-dessus » coupé en fin de ligne se lisait mal ?>
                <label for="affiliation" class="affil">Son nom, si pas dans la liste ci&#8209;dessus</label>
                <input type="text" name="affiliation" id="affiliation" size="30" maxlength="250" value="<?= sanitizeForHtml($champs['affiliation']); ?>" autocomplete="organization"<?= isset($erreurs['affiliation']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
                <span class="guide_champ">Si nous en créons un <a href="/lieu/lieux.php" target="_blank">lieu</a> ou un <a href="/organisateur/organisateurs.php" target="_blank">organisateur</a>, nous y rattacherons votre compte.</span>
            </p>

        </fieldset>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="submit" value="S'inscrire" class="submit submit-big" />
        </p>
    </form>

<?php endif; ?>

</main>

<?php
include("../_footer.inc.php");
?>
