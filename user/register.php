<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\Mailing;
use Ladecadanse\HtmlShrink;

$page_titre = "Inscription";
$page_description = "Création d'un compte sur La décadanse";
$extra_css = ["formulaires"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne inscription">

<?php
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
    // check token received == token initially set in form registered in session
    if (!isset($_SESSION[$formTokenName]) || $_POST[$formTokenName] !== $_SESSION[$formTokenName])
    {
        HtmlShrink::msgErreur("Désolé, le formulaire est expiré, veuillez le saisir à nouveau");
    }
    else
    {
        unset($_SESSION[$formTokenName]);

        // pot de miel : un bot qui remplit ce champ est écarté avant toute requête
        if (!empty($_POST['username_as']))
        {
            $verif->setErreur("username_as", "Veuillez laisser ce champ vide");
        }

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


        // validation

        $verif->valider($champs['login'], "login", "texte", 2, 80, true);

        if ($existeDeja('pseudo', $champs['login']))
        {
            $verif->setErreur("login_existant", "Un membre avec cet identifiant existe déjà, veuillez en choisir un autre");
        }

        foreach (PasswordPolicy::erreurs($champs['motdepasse'], $champs['motdepasse2']) as $champ => $message)
        {
            $verif->setErreur($champ, $message);
        }


        $verif->valider($champs['email'], "email", "email", 4, 100, true);

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

        $verif->valider($champs['affiliation'], "affiliation", "texte", 2, 250, obligatoire: false);

        // set an error to fail the process of user insertion and, to avoid "email enumeration vulnerability" : enable display of
        //  result msg, and disable redisplay of form (with this error text)
        if ($verif->nbErreurs() === 0 && $existeDeja('email', $champs['email']))
        {
            $verif->setErreur("email_identique", "-");
            $action_terminee = true;
        }

        if ($verif->nbErreurs() === 0)
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

            $action_terminee = true;

        } // if erreurs == 0

        if ($action_terminee)
        {
            HtmlShrink::msgOk("<p style='font-size:1.1em;line-height:1.5em'><strong>Votre compte a été créé</strong> (si l'adresse email fournie est valide); vous pouvez maintenant vous <a href=\"/user/login.php\">connecter</a> avec le login et le mot de passe que vous venez de saisir");
        }
    }

} // if POST != ""


if (!$action_terminee)
{
    $_SESSION[$formTokenName] = bin2hex(random_bytes(32));
    ?>

    <header id="entete_contenu">
        <h1>S’inscrire sur La décadanse</h1>
        <div class="spacer"></div>
    </header>

    <?php
    if ($verif->nbErreurs() > 0)
    {
        HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
    }
    ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait">

        <input type="text" class="name_as" name="username_as">
        <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">

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
                <input type="text" name="login" id="login" size="40" minlength="2" maxlength="80" value="<?= sanitizeForHtml($champs['login']) ?>" autofocus required />
                <?= $verif->getHtmlErreur('login') ?>
                <?= $verif->getHtmlErreur("login_existant") ?>
            </p>

            <p>
                <label for="motdepasse">Mot de passe*</label>
                <input type="password" name="motdepasse" id="motdepasse" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" required />
                <?= $verif->getHtmlErreur("motdepasse") ?>
            </p>

            <p>
                <label for="motdepasse2">Confirmer le mot de passe*</label>
                <input type="password" name="motdepasse2" id="motdepasse2" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" required />
                <?= $verif->getHtmlErreur("motdepasse2") ?>
            </p>

            <div class="guide_champ">Le mot de passe doit faire au minimum <?= PasswordPolicy::LONGUEUR_MIN ?> caractères et comporter au moins un chiffre</div>
            <?= $verif->getHtmlErreur("motdepasse_inegaux") ?>

            <p>
                <label for="email">E-mail*</label>
                <input type="email" name="email" id="email" size="35" maxlength="100" value="<?= sanitizeForHtml($champs['email']) ?>" required />
                <?= $verif->getHtmlErreur("email") ?>
                <?= $verif->getHtmlErreur("email_identique") ?>
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
                <input type="text" name="affiliation" id="affiliation" size="30" maxlength="250" value="<?= sanitizeForHtml($champs['affiliation']); ?>" />
            </p>
            <?= $verif->getHtmlErreur("affiliation"); ?>
        </fieldset>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="submit" value="S'inscrire" class="submit submit-big" />
        </p>
    </form>
<?php
} // if action_terminee
?>
</main>

<div id="colonne_gauche" class="colonne">
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
?>
