<?php

require_once("../app/bootstrap.php");

use Ladecadanse\HtmlShrink;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Mailing;
use PHPMailer\PHPMailer\PHPMailer;

if (!$authorization->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php"); die();
}

/*
 * Nombre de mails expédiés par soumission du formulaire. PHPMailer rouvre une connexion
 * SMTP à chaque message, soit 1 à 3 secondes par destinataire : un lot de 25 demande donc
 * environ 75 secondes. À baisser si l'hébergeur coupe la requête avant la fin d'un lot.
 */
const MAILING_LOT = 25;

/** Clé de session où le mailing en cours patiente entre deux lots */
const MAILING_SESSION_KEY = 'admin_mailing';

/**
 * Découpe le CSV collé dans le formulaire.
 *
 * Format attendu, une ligne par destinataire : idPersonne, pseudo, email
 * La ligne d'en-tête est facultative. Les adresses en double ne sont retenues qu'une fois.
 *
 * @return array{0: array<int, array{idPersonne: int, pseudo: string, email: string}>, 1: array<int, array{ligne: int, motif: string, extrait: string}>}
 *         les destinataires retenus, puis les lignes écartées avec leur motif
 */
function parserCsvDestinataires(string $csv): array
{
    $destinataires = [];
    $rejets = [];
    $vus = []; // adresses déjà retenues, en minuscules

    // le BOM d'un export phpMyAdmin survit au copier-coller dans certains navigateurs
    $csv = preg_replace('/^\x{FEFF}/u', '', trim($csv)) ?? '';
    $lignes = preg_split('/\r\n|\r|\n/', $csv) ?: [];

    // phpMyAdmin exporte au point-virgule selon les réglages
    $separateur = substr_count($lignes[0] ?? '', ';') > substr_count($lignes[0] ?? '', ',') ? ';' : ',';

    foreach ($lignes as $i => $ligne)
    {
        if (trim($ligne) === '')
        {
            continue;
        }

        $numero = $i + 1;
        // $escape explicite : sa valeur par défaut est dépréciée depuis PHP 8.4
        $champs = array_map('trim', str_getcsv($ligne, $separateur, '"', ''));

        // en-tête facultatif
        if ($i === 0 && strcasecmp($champs[0], 'idPersonne') === 0)
        {
            continue;
        }

        $extrait = mb_substr(trim($ligne), 0, 80);

        if (count($champs) !== 3)
        {
            $rejets[] = ['ligne' => $numero, 'motif' => count($champs) . " champ(s) au lieu de 3", 'extrait' => $extrait];
            continue;
        }

        [$idPersonne, $pseudo, $email] = $champs;

        if ((int) $idPersonne <= 0)
        {
            $rejets[] = ['ligne' => $numero, 'motif' => "idPersonne absent ou non numérique", 'extrait' => $extrait];
            continue;
        }

        if ($pseudo === '')
        {
            $rejets[] = ['ligne' => $numero, 'motif' => "pseudo vide", 'extrait' => $extrait];
            continue;
        }

        // la validation qu'appliquera Mailing::toUser() : ce que l'aperçu annonce est ce qui partira
        if (!PHPMailer::validateAddress($email))
        {
            $rejets[] = ['ligne' => $numero, 'motif' => "adresse e-mail invalide", 'extrait' => $extrait];
            continue;
        }

        if (isset($vus[mb_strtolower($email)]))
        {
            $rejets[] = ['ligne' => $numero, 'motif' => "adresse déjà présente plus haut", 'extrait' => $extrait];
            continue;
        }

        $vus[mb_strtolower($email)] = true;
        $destinataires[] = ['idPersonne' => (int) $idPersonne, 'pseudo' => $pseudo, 'email' => $email];
    }

    return [$destinataires, $rejets];
}

// =============================================================================
// Traitement
//
// Tout se joue avant l'inclusion de _header.inc.php, comme dans user/register.php :
// l'envoi d'un lot peut durer plus d'une minute, rien ne doit être émis avant.
// =============================================================================

$formTokenName = 'form_token_admin_mailing';

$etape = 'saisie'; // saisie | apercu | rapport
$champs = ['csv' => '', 'objet' => '', 'corps' => ''];
$erreurs = [];
$rejets = [];
$avertissements = [];
$rapport = [];      // résultat du lot qui vient d'être expédié
$apercu = [];       // premier mail entièrement rendu
$restants = 0;

// abandon d'un mailing entamé, pour repartir d'un formulaire vide
if (isset($_GET['abandon']))
{
    unset($_SESSION[MAILING_SESSION_KEY]);
    header("Location: /admin/mailing.php"); die();
}

$mailing = $_SESSION[MAILING_SESSION_KEY] ?? null;

// « Corriger » depuis l'aperçu : on repart de la saisie avec les champs conservés
if ($mailing !== null)
{
    $champs = ['csv' => $mailing['csv'], 'objet' => $mailing['objet'], 'corps' => $mailing['corps']];
}

if (isset($_POST['formulaire']))
{
    // pot de miel : un champ masqué que seuls les robots remplissent
    if (!empty($_POST['name_as']))
    {
        $erreurs[] = "Veuillez laisser vide le champ réservé aux robots.";
    }
    // le jeton reçu doit être celui déposé en session au rendu du formulaire ; à usage
    // unique, il interdit aussi de réexpédier un lot en rechargeant la page
    elseif (empty($_SESSION[$formTokenName]) || !hash_equals($_SESSION[$formTokenName], (string) ($_POST[$formTokenName] ?? '')))
    {
        $erreurs[] = "Le formulaire a expiré ou a déjà été envoyé, veuillez recommencer.";
    }
    else
    {
        unset($_SESSION[$formTokenName]);

        if ($_POST['formulaire'] === 'apercu')
        {
            foreach ($champs as $c => $v)
            {
                // is_scalar : ces trois champs sont des valeurs simples, un POST tableau ne doit pas passer
                $champs[$c] = isset($_POST[$c]) && is_scalar($_POST[$c]) ? trim((string) $_POST[$c]) : '';
            }

            [$destinataires, $rejets] = parserCsvDestinataires($champs['csv']);

            if ($champs['objet'] === '')
            {
                $erreurs[] = "L'objet est obligatoire.";
            }

            if ($champs['corps'] === '')
            {
                $erreurs[] = "Le corps du message est obligatoire.";
            }

            if ($destinataires === [])
            {
                $erreurs[] = "Aucun destinataire valide n'a été trouvé dans le CSV.";
            }

            if ($erreurs === [])
            {
                $mailing = [
                    'csv' => $champs['csv'],
                    'objet' => $champs['objet'],
                    'corps' => $champs['corps'],
                    'destinataires' => $destinataires,
                    'index' => 0,
                    'envoyes' => 0,
                    'echecs' => 0,
                ];
                $_SESSION[MAILING_SESSION_KEY] = $mailing;
                $etape = 'apercu';
            }
        }
        elseif ($_POST['formulaire'] === 'envoi')
        {
            // les destinataires ne transitent jamais par le POST : seul ce qui a été
            // prévisualisé, et déposé en session à ce moment-là, peut partir
            if ($mailing === null || $mailing['index'] >= count($mailing['destinataires']))
            {
                $erreurs[] = "Aucun mailing en attente. Recommencez depuis le formulaire.";
            }
            else
            {
                $mailer = new Mailing();
                // sur un envoi en nombre, la copie de monitoring doublerait le volume expédié
                $mailer->disableAdminCopy();

                // ne dépend d'aucun destinataire : rendu une seule fois, hors de la boucle
                $pied = $tplEngine->render('admin-mailing-footer', [
                    'site_url' => SITE_CANONICAL_URL,
                    'contact_url' => SITE_CANONICAL_URL . '/misc/contacteznous.php',
                ]);

                $lot = array_slice($mailing['destinataires'], $mailing['index'], MAILING_LOT);

                foreach ($lot as $d)
                {
                    set_time_limit(30); // réarmé à chaque mail, un envoi SMTP prend 1 à 3 secondes

                    $vars = ['pseudo' => $d['pseudo'], 'idPersonne' => (string) $d['idPersonne']];
                    $envoye = $mailer->toUser(
                        $d['email'],
                        $tplEngine->renderString($mailing['objet'], $vars),
                        $tplEngine->renderString($mailing['corps'], $vars) . "\n\n" . $pied
                    );

                    if ($envoye)
                    {
                        $mailing['envoyes']++;
                    }
                    else
                    {
                        $mailing['echecs']++;
                    }

                    $rapport[] = $d + ['envoye' => $envoye];
                    $logger->info('[admin-mailing]', ['idP' => $d['idPersonne'], 'email' => $d['email'], 'envoye' => $envoye]);
                }

                $mailing['index'] += count($lot);
                $restants = count($mailing['destinataires']) - $mailing['index'];
                $etape = 'rapport';

                if ($restants > 0)
                {
                    $_SESSION[MAILING_SESSION_KEY] = $mailing;
                }
                else
                {
                    unset($_SESSION[MAILING_SESSION_KEY]);
                }
            }
        }
    }
}

// l'aperçu se recalcule à l'affichage, à partir de ce qui vient d'être déposé en session
if ($etape === 'apercu')
{
    $premier = $mailing['destinataires'][0];
    $vars = ['pseudo' => $premier['pseudo'], 'idPersonne' => (string) $premier['idPersonne']];

    $pied = $tplEngine->render('admin-mailing-footer', [
        'site_url' => SITE_CANONICAL_URL,
        'contact_url' => SITE_CANONICAL_URL . '/misc/contacteznous.php',
    ]);

    $apercu = [
        'destinataire' => $premier,
        'objet' => $tplEngine->renderString($mailing['objet'], $vars),
        'corps' => $tplEngine->renderString($mailing['corps'], $vars) . "\n\n" . $pied,
    ];

    if (strip_tags($apercu['corps']) !== $apercu['corps'])
    {
        $avertissements[] = "Le corps contient des balises de type &lt;…&gt; : elles seront supprimées à l'envoi.";
    }

    if (mb_strlen($apercu['corps']) > Mailing::MAX_BODY_LENGTH)
    {
        $avertissements[] = "Le corps dépasse " . Mailing::MAX_BODY_LENGTH . " caractères et sera tronqué à l'envoi.";
    }

    if (mb_strlen($apercu['objet']) > Mailing::MAX_SUBJECT_LENGTH)
    {
        $avertissements[] = "L'objet dépasse " . Mailing::MAX_SUBJECT_LENGTH . " caractères et sera tronqué à l'envoi.";
    }
}

/*
 * Un lot a été expédié, puis la page a été rechargée ou quittée : le jeton à usage unique
 * a ramené l'admin au formulaire de saisie alors qu'il reste des destinataires à traiter.
 * Le mailing dort en session, on propose de le reprendre plutôt que de le perdre.
 */
$reprise = $etape === 'saisie' && $mailing !== null && $mailing['index'] > 0
    ? count($mailing['destinataires']) - $mailing['index']
    : 0;

// jeton neuf à chaque rendu : les trois étapes enchaînent chacune un formulaire
$_SESSION[$formTokenName] = bin2hex(random_bytes(32));

$page_titre = "Mailing aux utilisateurs";
$extra_css = ["admin/tables"];
require_once '../_header.inc.php';
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Mailing aux utilisateurs</h1>
        <div class="spacer"></div>
    </header>

    <section id="default">

        <?php
        foreach ($erreurs as $erreur)
        {
            HtmlShrink::msgErreur(sanitizeForHtml($erreur));
        }
        ?>

        <?php if ($etape === 'saisie' && $reprise > 0) : ?>

            <?php HtmlShrink::msgInfo("Un mailing est en cours : " . (int) $mailing['index'] . " destinataire(s) déjà traité(s), "
                . $reprise . " en attente."); ?>

            <form method="post" class="js-submit-freeze-wait" action="mailing.php">
                <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">
                <p class="piedForm">
                    <input type="hidden" name="formulaire" value="envoi" />
                    <input type="text" name="name_as" value="" class="name_as" />
                    <input type="submit" value="Reprendre — <?= $reprise ?> restant(s)" class="submit submit-big" />
                    <div class="spacer"><!-- --></div>
                </p>
            </form>

            <p><a href="mailing.php?abandon=1" onclick="return confirm('Abandonner ce mailing ? Les destinataires non encore traités ne recevront rien.');">Abandonner ce mailing et composer un nouveau message</a></p>

        <?php elseif ($etape === 'saisie') : ?>

            <p>Les destinataires sont fournis en CSV, par exemple depuis un export phpMyAdmin de la table
                <code>personne</code>. Une ligne par personne, la ligne d'en-tête est facultative :</p>

            <pre style="white-space:pre-wrap">idPersonne, pseudo, email
12345, pseudo12345, pseudo12345@gromail.com</pre>

            <p>Dans l'objet comme dans le corps, <code>%pseudo%</code> et <code>%idPersonne%</code> sont remplacés
                par les valeurs de chaque ligne. Une variable mal orthographiée apparaît en clair dans l'aperçu
                sous la forme <code>[non défini: %…%]</code>.</p>

            <p>Le message part en texte brut, signé
                <strong><?= sanitizeForHtml(EMAIL_SITE_NAME) ?> &lt;<?= sanitizeForHtml(EMAIL_SITE) ?>&gt;</strong>,
                suivi d'une mention de désinscription ajoutée automatiquement. Rien ne s'envoie à cette étape :
                l'aperçu vient d'abord.</p>

            <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="mailing.php">

                <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">

                <fieldset>

                    <p>
                        <label for="csv">Destinataires (CSV)</label>
                        <textarea name="csv" id="csv" rows="10" required><?= sanitizeForHtml($champs['csv']) ?></textarea>
                    </p>

                    <p>
                        <label for="objet">Objet</label>
                        <input type="text" name="objet" id="objet" size="60"
                               maxlength="<?= Mailing::MAX_SUBJECT_LENGTH ?>"
                               value="<?= sanitizeForHtml($champs['objet']) ?>" required />
                    </p>

                    <p>
                        <label for="corps">Message</label>
                        <textarea name="corps" id="corps" rows="16" required><?= sanitizeForHtml($champs['corps']) ?></textarea>
                    </p>

                </fieldset>

                <p class="piedForm">
                    <input type="hidden" name="formulaire" value="apercu" />
                    <input type="text" name="name_as" value="" class="name_as" />
                    <input type="submit" value="Aperçu" class="submit submit-big" />
                    <div class="spacer"><!-- --></div>
                </p>

            </form>

        <?php elseif ($etape === 'apercu') :

            $nb = count($mailing['destinataires']);
            ?>

            <h2><?= $nb ?> destinataire<?= $nb > 1 ? 's' : '' ?>,
                <?= (int) ceil($nb / MAILING_LOT) ?> lot<?= $nb > MAILING_LOT ? 's' : '' ?>
                de <?= MAILING_LOT ?> au maximum</h2>

            <?php
            // non échappés à dessein : ces messages sont des littéraux du script,
            // sans donnée utilisateur, et contiennent des entités HTML volontaires
            foreach ($avertissements as $avertissement)
            {
                HtmlShrink::msgInfo($avertissement);
            }
            ?>

            <?php if ($rejets !== []) : ?>
                <h3><?= count($rejets) ?> ligne<?= count($rejets) > 1 ? 's' : '' ?> écartée<?= count($rejets) > 1 ? 's' : '' ?></h3>
                <table id="ajouts">
                    <tr><th>Ligne</th><th>Motif</th><th>Contenu</th></tr>
                    <?php foreach ($rejets as $rejet) : ?>
                        <tr>
                            <td><?= (int) $rejet['ligne'] ?></td>
                            <td><?= sanitizeForHtml($rejet['motif']) ?></td>
                            <td><?= sanitizeForHtml($rejet['extrait']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <h3>Premières adresses</h3>
            <ul>
                <?php foreach (array_slice($mailing['destinataires'], 0, 3) as $d) : ?>
                    <li><?= sanitizeForHtml($d['email']) ?> (<?= sanitizeForHtml($d['pseudo']) ?>)</li>
                <?php endforeach; ?>
            </ul>

            <h3>Le mail tel que le recevra <?= sanitizeForHtml($apercu['destinataire']['pseudo']) ?></h3>
            <table id="ajouts">
                <tr><th>De</th><td><?= sanitizeForHtml(EMAIL_SITE_NAME) ?> &lt;<?= sanitizeForHtml(EMAIL_SITE) ?>&gt;</td></tr>
                <tr><th>Pour</th><td><?= sanitizeForHtml($apercu['destinataire']['email']) ?></td></tr>
                <tr><th>Objet</th><td><?= sanitizeForHtml($apercu['objet']) ?></td></tr>
            </table>
            <pre style="white-space:pre-wrap;border:1px solid #ccc;padding:0.8em"><?= sanitizeForHtml($apercu['corps']) ?></pre>

            <form method="post" class="js-submit-freeze-wait" action="mailing.php"
                  onsubmit="return confirm('Envoyer réellement ce message ?');">
                <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">
                <p class="piedForm">
                    <input type="hidden" name="formulaire" value="envoi" />
                    <input type="text" name="name_as" value="" class="name_as" />
                    <input type="submit" value="Envoyer le premier lot de <?= min($nb, MAILING_LOT) ?>" class="submit submit-big" />
                    <div class="spacer"><!-- --></div>
                </p>
            </form>

            <p><a href="mailing.php">Corriger le message</a>
                &nbsp;·&nbsp;
                <a href="mailing.php?abandon=1" onclick="return confirm('Abandonner ce mailing ? Les destinataires non encore traités ne recevront rien.');">Abandonner ce mailing</a></p>

        <?php elseif ($etape === 'rapport') : ?>

            <?php
            if ($restants > 0)
            {
                HtmlShrink::msgOk("Lot expédié : " . $mailing['envoyes'] . " envoi(s) réussi(s), "
                    . $mailing['echecs'] . " échec(s) depuis le début. Il reste " . $restants . " destinataire(s).");
            }
            else
            {
                HtmlShrink::msgOk("Mailing terminé : " . $mailing['envoyes'] . " envoi(s) réussi(s), "
                    . $mailing['echecs'] . " échec(s).");
            }
            ?>

            <table id="ajouts">
                <tr><th>idPersonne</th><th>Pseudo</th><th>E-mail</th><th>Envoi</th></tr>
                <?php foreach ($rapport as $r) : ?>
                    <tr>
                        <td><?= (int) $r['idPersonne'] ?></td>
                        <td><?= sanitizeForHtml($r['pseudo']) ?></td>
                        <td><?= sanitizeForHtml($r['email']) ?></td>
                        <td><?= $r['envoye'] ? '✓' : '✗' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if ($mailing['echecs'] > 0) : ?>
                <p>Le détail des échecs n'est pas exposé ici : <code>Mailing</code> le consigne dans le journal
                    d'erreurs du serveur.</p>
            <?php endif; ?>

            <?php if ($restants > 0) : ?>
                <form method="post" class="js-submit-freeze-wait" action="mailing.php">
                    <input type="hidden" name="<?= $formTokenName; ?>" value="<?= $_SESSION[$formTokenName]; ?>">
                    <p class="piedForm">
                        <input type="hidden" name="formulaire" value="envoi" />
                        <input type="text" name="name_as" value="" class="name_as" />
                        <input type="submit" value="Continuer — <?= $restants ?> restant(s)" class="submit submit-big" />
                        <div class="spacer"><!-- --></div>
                    </p>
                </form>
                <p><a href="mailing.php?abandon=1" onclick="return confirm('Abandonner ce mailing ? Les destinataires non encore traités ne recevront rien.');">Abandonner les destinataires restants</a></p>
            <?php else : ?>
                <p><a href="mailing.php">Composer un autre message</a></p>
            <?php endif; ?>

        <?php endif; ?>

    </section>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<div class="spacer"><!-- --></div>
<?php
include("../_footer.inc.php");
?>
