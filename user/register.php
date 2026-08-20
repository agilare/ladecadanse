<?php

require_once("../app/bootstrap.php");

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

$champs = ["login" => '',
    "motdepasse" => '',
    "motdepasse2" => '',
    'organisateurs' => [],
    "affiliation" => '',
    "region" => '',
    "lieu" => '',
    "email" => '',
    "groupe" => UserLevel::ACTOR // forced since groupe cannot be chosen
];

$action_terminee = false;

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

        foreach ($champs as $c => $v)
        {
            // is_scalar : les champs du formulaire sont tous des valeurs simples,
            // un POST tableau ne doit pas se retrouver dans une validation
            if ($c !== 'groupe' && isset($_POST[$c]) && is_scalar($_POST[$c]))
            {
                $champs[$c] = $_POST[$c];
            }
        }
        // le select poste toujours au moins son option vide, mais rien ne garantit la forme du POST reçu
        $champs['organisateurs'] = (array) ($_POST['organisateurs'] ?? []);

        /**
         * Unicité d'un champ de personne.
         *
         * @param string $colonne Nom de colonne littéral (jamais une saisie)
         */
        $existeDeja = function (string $colonne, string $valeur) use ($connectorPdo): bool
        {
            $stmt = $connectorPdo->prepare("SELECT 1 FROM personne WHERE $colonne = :valeur LIMIT 1");
            $stmt->execute([':valeur' => $valeur]);

            return $stmt->fetchColumn() !== false;
        };


        /*
         * Validation. Les messages sont écrits pour cette page plutôt que repris de
         * Validateur, génériques et anglicisés (« Le texte est trop court : 1, min 2
         * characters ») — et surtout indiscernables les uns des autres dans l'encart
         * d'erreurs, qui les rassemble loin de leur champ.
         */

        if ($champs['login'] === '')
        {
            $verif->setErreur("login", "Veuillez choisir un login.");
        }
        else if (mb_strlen($champs['login']) < 2 || mb_strlen($champs['login']) > 80)
        {
            $verif->setErreur("login", "Votre login fait entre 2 et 80 caractères.");
        }
        else if ($existeDeja('pseudo', $champs['login']))
        {
            $verif->setErreur("login_existant", "Un membre avec ce login existe déjà, veuillez en choisir un autre.");
        }

        foreach (PasswordPolicy::erreurs($champs['motdepasse'], $champs['motdepasse2']) as $champ => $message)
        {
            $verif->setErreur($champ, $message);
        }

        // borne haute alignée sur la colonne personne.email
        if ($champs['email'] === '')
        {
            $verif->setErreur("email", "Veuillez saisir votre adresse e-mail.");
        }
        else if (filter_var($champs['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($champs['email']) > 100)
        {
            $verif->setErreur("email", "Cette adresse e-mail n'est pas valable.");
        }

        // feature temporarly disabled; let commented
//        $verif->valider($champs['groupe'], "groupe", "int", 1, 2, 1);
//
//        if (!empty($champs['groupe']))
//        {
//            if ($champs['groupe'] > UserLevel::MEMBER)
//            {
//                $verif->setErreur("groupe", "Le groupe " . $champs['groupe'] . " n'est pas valable");
//            }
//        }

        if ($champs['affiliation'] !== '' && (mb_strlen($champs['affiliation']) < 2 || mb_strlen($champs['affiliation']) > 250))
        {
            $verif->setErreur("affiliation", "Le nom de l'affiliation fait entre 2 et 250 caractères.");
        }

        if ($verif->nbErreurs() === 0)
        {
            /*
             * Un e-mail déjà pris ne se dit pas : la page rend alors le même message de
             * fin qu'une création réussie, sans rien insérer ni envoyer. L'annoncer
             * ferait de ce formulaire un oracle d'existence de compte.
             */
            if (!$existeDeja('email', $champs['email']))
            {
                $maintenant = date("Y-m-d H:i:s");

                $stmt = $connectorPdo->prepare("
                    INSERT INTO personne (pseudo, mot_de_passe, email, groupe, affiliation, region, cookie, statut, dateAjout, date_derniere_modif)
                    VALUES (:pseudo, :mot_de_passe, :email, :groupe, :affiliation, :region, '', 'actif', :dateAjout, :dateModif)");
                $stmt->execute([
                    ':pseudo' => $champs['login'],
                    ':mot_de_passe' => password_hash($champs['motdepasse'], PASSWORD_DEFAULT),
                    ':email' => $champs['email'],
                    ':groupe' => $champs['groupe'],
                    ':affiliation' => $champs['affiliation'],
                    ':region' => $champs['region'],
                    ':dateAjout' => $maintenant,
                    ':dateModif' => $maintenant,
                ]);

                $new_user_id = (int) $connectorPdo->lastInsertId();

                // si un lieu a été choisi comme affiliation
                if (!empty($champs['lieu']))
                {
                    $stmt = $connectorPdo->prepare("INSERT INTO affiliation (idPersonne, idAffiliation, genre)
                        VALUES (:idPersonne, :idAffiliation, 'lieu')");
                    $stmt->execute([':idPersonne' => $new_user_id, ':idAffiliation' => (int) $champs['lieu']]);
                }

                $stmt = $connectorPdo->prepare("INSERT INTO personne_organisateur (idPersonne, idOrganisateur)
                    VALUES (:idPersonne, :idOrganisateur)");

                foreach ($champs['organisateurs'] as $idOrg)
                {
                    if (!empty($idOrg))
                    {
                        $stmt->execute([':idPersonne' => $new_user_id, ':idOrganisateur' => (int) $idOrg]);
                    }
                }

                $mailer = new Mailing();
                $mailer->toUser(
                    $champs['email'],
                    "Votre nouveau compte 👤 " . $champs['login'] . " sur La décadanse",
                    $tplEngine->render("user-register-mail-body", [
                        'site_url' => $site_full_url,
                        'login_url' => $site_full_url . "user/login.php",
                        'dashboard_url' => $site_full_url . "user/dashboard.php?idP=" . $new_user_id,
                    ])
                );

                $logger->info('[user-register]', ['pseudo' => $champs['login'], 'email' => $champs['email'], 'groupe' => $champs['groupe'], 'idP' => $new_user_id]);
            }

            $action_terminee = true;

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
        <p>Vous pouvez maintenant vous <a href="/user/login.php">connecter</a> avec le login et le
            mot de passe que vous venez de saisir.</p>
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

        <p>Un compte vous permettra d'ajouter vos événements sans délai de publication et de les
            gérer aisément.</p>

        <div>Avant de vous inscrire, veillez svp :
            <ul>
                <li>à ce que les événements que vous souhaitez ajouter respectent notre <b><a href="/articles/charte-editoriale.php">charte&nbsp;éditoriale</a></b> (pas tous les événements sont publiés)</li>
                <li>s'il n'y a pas déjà un compte La décadanse dans votre organisation</li>
            </ul>
        </div>
        <p>Les événements annoncés sur La décadanse sont également visibles sur <a href="https://epic-magazine.ch/" target="_blank">EPIC-Magazine</a></p>
        <details style="margin-left:15px;margin-top:-11px">
            <summary>Détails</summary>
            <ul>
                <li><b>EPIC-Magazine</b> - webmagazine qui met en avant la culture locale et émergente à Genève et dans ses environs&nbsp;: intégration de l'agenda dans la <a href="https://epic-magazine.ch/lieux/" target="_blank">page Cartographie</a>
                </li>
            </ul>
        </details>
        <br>
        <p>* indique un champ obligatoire</p>

        <fieldset>

            <legend>Compte</legend>

            <p>
                <label for="login">Login*</label>
                <input type="text" name="login" id="login" size="40" minlength="2" maxlength="80" value="<?= sanitizeForHtml($champs['login']) ?>" autocomplete="username" autofocus required<?= isset($erreurs['login']) || isset($erreurs['login_existant']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

            <p>
                <label for="motdepasse">Mot de passe*</label>
                <input type="password" name="motdepasse" id="motdepasse" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" autocomplete="new-password" required<?= isset($erreurs['motdepasse']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

            <p>
                <label for="motdepasse2">Confirmer le mot de passe*</label>
                <input type="password" name="motdepasse2" id="motdepasse2" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" autocomplete="new-password" required<?= isset($erreurs['motdepasse_inegaux']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
                <?php /* dans le même paragraphe que le champ : l'aide est ainsi une case de la
                         grille, alignée sur la colonne des champs sans marge à recalculer */ ?>
                <span class="guide_champ">Le mot de passe doit faire au minimum <?= PasswordPolicy::LONGUEUR_MIN ?> caractères et comporter au moins un chiffre</span>
            </p>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="35" maxlength="100" value="<?= sanitizeForHtml($champs['email']) ?>" autocomplete="email" required<?= isset($erreurs['email']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
            </p>

<!-- feature temporarly disabled; let commented           <input type="hidden" name="groupe" id="user-register_organisateur" value="8" />-->

        </fieldset>

        <fieldset class="affiliation" id="user-register_references" >

            <legend>Affiliation</legend>

            <div class="guide_affiliation">Si vous êtes un <b>Acteur culturel</b>, merci d'indiquer à quel association, collectif, lieu, etc. vous appartenez.<br>Ainsi, une fois votre compte créé, vous pourrez modifier les informations du <a href="/lieu/lieux.php" target="_blank">Lieu</a> et/ou <a href="/organisateurs.php" target="_blank">Organisateur</a> sur La décadanse (données pratiques, images, présentations)</div>
            <p>
                <label for="lieu" class="affil">Lieu&nbsp;</label>
                <select name="lieu" id="lieu" class="js-select2-options-with-style" data-placeholder="Tapez le nom..." style="max-width:350px">
                    <option value=""></option>
                    <?php
                    $req_lieux = $connectorPdo->query("
                        SELECT idLieu, nom FROM lieu WHERE actif=1 AND statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les '
                        FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci");

                    foreach ($req_lieux as $lieuTrouve)
                    {
                        echo "<option ";
                        if ($lieuTrouve['idLieu'] == $champs['lieu'])
                        {
                            echo "selected=\"selected\" ";
                        }
                        echo "value=\"" . $lieuTrouve['idLieu'] . "\">" . sanitizeForHtml($lieuTrouve['nom']) . "</option>";
                    }
                    ?>
                </select>
            </p>
            <div class="spacer"></div>
            <p>
                <label class="affil">Organisateur&nbsp;</label>
                <select name="organisateurs[]" id="organisateurs" class="js-select2-options-with-complement" title="Un organisateur dans base de données de La décadanse" style="max-width:350px" data-placeholder="Tapez le nom...">
                    <option value=""></option>
                    <?php
                    $req_organisateurs = $connectorPdo->query("
                        SELECT idOrganisateur, nom FROM organisateur WHERE statut='actif' ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les '
                        FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci");

                    foreach ($req_organisateurs as $organisateurTrouve)
                    {
                        echo "<option value=\"" . $organisateurTrouve['idOrganisateur'] . "\">" . sanitizeForHtml($organisateurTrouve['nom']) . "</option>";
                    }
                    ?>
                </select>
            </p>

            <p class="entreLabels"><strong>sinon</strong></p>
            <div class="spacer"></div>
            <p>
                <label for="affiliation" class="affil">Nom&nbsp;</label>
                <input type="text" name="affiliation" id="affiliation" size="30" maxlength="250" value="<?= sanitizeForHtml($champs['affiliation']); ?>"<?= isset($erreurs['affiliation']) ? ' class="champ_errone" aria-invalid="true"' : '' ?>>
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
