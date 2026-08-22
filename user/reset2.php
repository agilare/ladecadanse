<?php

require_once("../app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\PasswordPolicy;
use Ladecadanse\Utils\QueryParamValidator;
use Ladecadanse\HtmlShrink;

if ($authorization->checkGroup(UserLevel::MEMBER)) {
	header("Location: /index.php"); die();
}

$page_titre = "Réinitialisation du mot de passe";
$page_description = "";
$extra_css = ["formulaires"];
include("../_header.inc.php");

if (empty($_GET['token']))
{
    HtmlShrink::msgErreur("token obligatoire");
	exit;
}

$get['token'] = QueryParamValidator::validateUrlQueryValue($_GET['token'], "alpha_numeric", 1);

$verif = new Validateur();

$champs = ["idPersonne" => '',
    "motdepasse" => '',
    "motdepasse2" => ''
];

$action_terminee = false;
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Réinitialisation du mot de passe</h1>
        <div class="spacer"></div>
    </header>


<?php
// vérification en tous temps; un seul enregistrement accepté
$stmt = $connectorPdo->prepare("SELECT idPersonne, email FROM user_reset_requests
	WHERE token = :token AND expiration > NOW()");
$stmt->execute([':token' => $get['token']]);
$demandes = $stmt->fetchAll();

if (count($demandes) === 1)
{
	$tab_temp = $demandes[0];

	if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
	{
		foreach ($champs as $c => $v)
		{
            // is_scalar : les champs du formulaire sont tous des valeurs simples,
            // un POST tableau ne doit pas se retrouver dans une validation
            if (isset($_POST[$c]) && is_scalar($_POST[$c]))
            {
                $champs[$c] = $_POST[$c];
            }
		}

		if (empty($tab_temp['idPersonne']) && empty($champs['idPersonne']))
		{
			$verif->setErreur("motdepasse", "Veuillez choisir un compte");
		}

		$tab_auth = null;

		// si l'email avait été fourni, vérification qu'il correspond bien au compte choisi
		if (!empty($tab_temp['email']) && !empty($champs['idPersonne']))
		{
			// retrouver user dans personne à partir de la demande ; si le demandeur a choisi un
			// compte parmi plusieurs qui ont son email, l'id doit correspondre à cet email
			$stmt = $connectorPdo->prepare("SELECT pseudo, idPersonne FROM personne
				WHERE email = :email AND idPersonne = :idPersonne");
			$stmt->execute([':email' => $tab_temp['email'], ':idPersonne' => (int) $champs['idPersonne']]);
			$comptes_auth = $stmt->fetchAll();

			// on doit obtenir un seul compte
			if (count($comptes_auth) !== 1)
			{
				$verif->setErreur("auth", "Vous n'êtes pas autorisé à modifier ce compte");
			}
			else
			{
				// un seul row donc
				$tab_auth = $comptes_auth[0];
			}
		}

		$idPersonne = '';

		if (!empty($tab_temp['idPersonne']))
		{
			$idPersonne = $tab_temp['idPersonne'];
		}
		else if (!empty($tab_temp['email']))
		{
			$idPersonne = $tab_auth['idPersonne'] ?? '';
		}
		else
		{
			$verif->setErreur("motdepasse", "L'utilisateur n'a pu être retrouvé");
		}

		foreach (PasswordPolicy::erreurs($champs['motdepasse'], $champs['motdepasse2']) as $champ => $message)
		{
			$verif->setErreur($champ, $message);
		}

		if ($verif->nbErreurs() === 0)
		{
			try
			{
				// gds : sel du stockage historique (sha1), vidé partout où le mot de passe change
				$stmt = $connectorPdo->prepare("UPDATE personne
					SET mot_de_passe = :hash, gds = '', date_derniere_modif = NOW()
					WHERE idPersonne = :idPersonne");
				$stmt->execute([
					':hash' => password_hash($champs['motdepasse'], PASSWORD_DEFAULT),
					':idPersonne' => (int) $idPersonne,
				]);

				$stmt = $connectorPdo->prepare("DELETE FROM user_reset_requests WHERE token = :token");
				$stmt->execute([':token' => $get['token']]);

				HtmlShrink::msgOk("Le mot de passe a été mis à jour, vous pouvez maintenant vous "
                    . "<a href='/user/login.php'>connecter</a> avec ce nouveau mot de passe");

                $logger->info('[user-reset2] password reset success', ['idP' => $idPersonne]);
			}
			// PDO est en ERRMODE_EXCEPTION : sans ce catch, un échec d'écriture n'afficherait
			// plus rien à l'utilisateur
			catch (PDOException $e)
			{
				HtmlShrink::msgErreur("La requête UPDATE a échoué, veuillez contacter le webmaster");

				$logger->error('[user-reset2] password reset failed', ['idP' => $idPersonne, 'exception' => $e->getMessage()]);
			}

			$action_terminee = true;
		} // if erreurs == 0
	} // if POST != ""

	// si l'email avait été fourni, retrouve le(s) compte(s) associé(s)
	$tab_comptes = [];
	if (empty($tab_temp['idPersonne']) && !empty($tab_temp['email']))
	{
		// statut : 'actif' et non '!= inactif', puisque Sentry n'accepte au login que les comptes actifs
		$stmt = $connectorPdo->prepare("SELECT idPersonne, pseudo FROM personne
			WHERE email = :email AND statut = 'actif'");
		$stmt->execute([':email' => $tab_temp['email']]);
		$tab_comptes = $stmt->fetchAll();
	}

    if (!$action_terminee)
    {
        if ($verif->nbErreurs() > 0)
        {
            HtmlShrink::msgErreur("Il y a ".$verif->nbErreurs()." erreur(s).");
        }
        ?>
        <form method="post" id="ajouter_editer"  class="js-submit-freeze-wait" action="?token=<?= sanitizeForHtml($get['token']) ?>">

            <fieldset>

                <span class="mr_as">
                    <label for="mr_as">Ne pas remplir ce champ</label><input name="as_nom" id="as_nom" type="text">
                </span>

                <?php if (count($tab_comptes) > 1) { ?>
                <p>
                    <label for="idPersonne"  class="large">Lequel de votre compte ?</label>
                    <select name="idPersonne" id="idPersonne" >
                    <?php
                    foreach ($tab_comptes as $c)
                    {
                        $selected = '';
                        if (isset($_POST['idPersonne']) && $c['idPersonne'] == $_POST['idPersonne'])
                            $selected = " selected";
                    ?>
                        <option value="<?= $c['idPersonne']; ?>" <?= $selected; ?>><?= sanitizeForHtml($c['pseudo']); ?></option>
                    <?php } ?>
                    </select>
                    <?= $verif->getHtmlErreur("auth");?>
                </p>
                <?php
                }
                else if (count($tab_comptes) == 1)
                {
                ?>

                <input type="hidden" name="idPersonne" value="<?= $tab_comptes[0]['idPersonne']; ?>" />

                <?php } ?>

                <p>
                    <label for="motdepasse" class="large">Nouveau mot de passe</label>
                    <input type="password" name="motdepasse" id="motdepasse" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" required />
                    <?= $verif->getHtmlErreur("motdepasse");?>
                </p>

                <p>
                    <label for="motdepasse2" class="large">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="motdepasse2" id="motdepasse2" size="20" minlength="<?= PasswordPolicy::LONGUEUR_MIN ?>" maxlength="<?= PasswordPolicy::LONGUEUR_MAX ?>" value="" required />
                    <?= $verif->getHtmlErreur("motdepasse2");?>
                </p>

                <div class="guideChamp">Le mot de passe doit faire au minimum <?= PasswordPolicy::LONGUEUR_MIN ?> caractères et comporter au moins un chiffre</div>

                <?= $verif->getHtmlErreur("motdepasse_inegaux");?>

            </fieldset>

            <p class="piedForm">
                <input type="hidden" name="formulaire" value="ok" />
                <input type="submit" value="Envoyer" class="submit" />
            </p>

        </form>

    <?php
    } // if action
}
else
{
	HtmlShrink::msgErreur("Cette demande n'est pas valable");
}
?>
</main>

<div id="colonne_gauche" class="colonne">
<?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<?php
include("../_footer.inc.php");
?>
