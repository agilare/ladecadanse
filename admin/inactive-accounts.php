<?php

require_once("../app/bootstrap.php");

use Ladecadanse\InactiveAccountRetention;
use Ladecadanse\Personne;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\DateHelper;

/**
 * Comptes que la durée de conservation va rattraper, en lecture seule.
 *
 * L'anonymisation est tenue par InactiveAccountRetention, au fil des pages vues : cet écran ne
 * déclenche rien, il donne de quoi voir venir — et, le cas échéant, de prévenir quelqu'un
 * autrement que par le courriel automatique.
 *
 * Pour effacer un compte précis tout de suite, le bouton de sa fiche (user/dashboard.php) reste
 * la voie, réservée au SUPERADMIN.
 */

if (!$authorization->checkGroup(UserLevel::ADMIN))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /user/login.php");
    die();
}

$comptes = (new InactiveAccountRetention($connectorPdo->getPDO(), $logger))->pending();

// deux groupes dans la même liste : ceux qui attendent leur avertissement, ceux qui attendent
// leur anonymisation. pending() les rend déjà dans cet ordre.
$attendent_avertissement = array_filter($comptes, static fn (array $c): bool => $c['inactivity_notified_at'] === null);
$attendent_anonymisation = array_filter($comptes, static fn (array $c): bool => $c['inactivity_notified_at'] !== null);

$page_titre = "Comptes inactifs";
$extra_css = ["admin/tables"];
require_once '../_header.inc.php';
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Comptes inactifs</h1>
        <div class="spacer"></div>
    </header>

    <section id="default">

        <p>Un compte sans connexion depuis <?= InactiveAccountRetention::RETENTION_YEARS ?>&nbsp;ans reçoit un avertissement, puis est anonymisé <?= InactiveAccountRetention::NOTICE_DAYS ?>&nbsp;jours plus tard s’il n’est pas revenu entre-temps. Les administrateurs sont hors de ce décompte. Cette page ne déclenche rien&nbsp;: elle montre ce qui va se passer.</p>

        <p>La dernière activité est la date de dernière connexion&nbsp;; pour les comptes antérieurs à l’enregistrement des connexions, celle de la dernière modification du profil, à défaut celle de l’inscription.</p>

        <h2>Anonymisation prévue (<?= count($attendent_anonymisation) ?>)</h2>

        <?php if ($attendent_anonymisation === []) : ?>
            <p>Aucun compte averti n’attend son anonymisation.</p>
        <?php else : ?>
            <table id="ajouts">
                <tr>
                    <th>Compte</th>
                    <th>Niveau</th>
                    <th>Dernière activité</th>
                    <th>Averti le</th>
                    <th>Anonymisation à partir du</th>
                </tr>
                <?php foreach ($attendent_anonymisation as $c) : ?>
                    <tr>
                        <td class="tdleft"><a href="/user/dashboard.php?idP=<?= (int) $c['idPersonne'] ?>"><?= sanitizeForHtml(Personne::displayName($c['pseudo'], $c['email'])) ?></a></td>
                        <td><?= sanitizeForHtml(UserLevel::getName((int) $c['groupe'])) ?></td>
                        <td><?= DateHelper::isoToApp(mb_substr((string) $c['derniere_activite'], 0, 10)) ?></td>
                        <td><?= DateHelper::isoToApp(mb_substr((string) $c['inactivity_notified_at'], 0, 10)) ?></td>
                        <td><?= DateHelper::isoToApp(date('Y-m-d', strtotime((string) $c['inactivity_notified_at'] . ' +' . InactiveAccountRetention::NOTICE_DAYS . ' days'))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h2>Avertissement à venir (<?= count($attendent_avertissement) ?>)</h2>

        <?php if ($attendent_avertissement === []) : ?>
            <p>Aucun compte n’a franchi le seuil d’inactivité.</p>
        <?php else : ?>
            <table id="ajouts">
                <tr>
                    <th>Compte</th>
                    <th>Niveau</th>
                    <th>Dernière activité</th>
                </tr>
                <?php foreach ($attendent_avertissement as $c) : ?>
                    <tr>
                        <td class="tdleft"><a href="/user/dashboard.php?idP=<?= (int) $c['idPersonne'] ?>"><?= sanitizeForHtml(Personne::displayName($c['pseudo'], $c['email'])) ?></a></td>
                        <td><?= sanitizeForHtml(UserLevel::getName((int) $c['groupe'])) ?></td>
                        <td><?= DateHelper::isoToApp(mb_substr((string) $c['derniere_activite'], 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </section>

</main>

<div id="colonne_gauche" class="colonne">
</div>

<?php
include("../_footer.inc.php");
?>
