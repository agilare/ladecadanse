<?php

require_once("app/bootstrap.php");

use Ladecadanse\UserLevel;
use Ladecadanse\Organisateur;
use Ladecadanse\Evenement;
use Ladecadanse\EvenementCollection;
use Ladecadanse\Utils\Text;
use Ladecadanse\HtmlShrink;

if (empty($_GET['idO']) || !is_numeric($_GET['idO']))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
    exit;
}
$get['idO'] = (int) $_GET['idO'];

$organisateur = new Organisateur();
$organisateur->setId($get['idO']);
$organisateur->load();

if (empty($organisateur))
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}

//if ($lieu['statut'] == 'inactif' && !((isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= UserLevel::AUTHOR)))
//{
//    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
//    exit;
//}

$date_debut = date("Y-m-d", time() - 21600);
$evenements = new EvenementCollection($connector);
$evenements->loadOrganisateur($get['idO'], $date_debut, "");

$extra_css = ["organisateurs_menu"];
$page_titre = $organisateur->getValue('nom');
$page_description = $organisateur->getValue('nom') . " : informations pratiques, description et prochains événements";

include("_header.inc.php");
include("_menuorganisateurs.inc.php");
?>

<main id="contenu" class="colonne">

	<p id="btn_listelieux" class="mobile">
        <button href="#"><i class="fa fa-list fa-lg"></i>&nbsp;Liste des organisateurs</button>
	</p>

    <?php
    if (!empty($_SESSION['organisateur_flash_msg']))
    {
        HtmlShrink::msgOk($_SESSION['organisateur_flash_msg']);
        unset($_SESSION['organisateur_flash_msg']);
    }
    ?>

    <section class="vcard">

        <header id="entete_contenu">

            <h1 class="fn org"><?= $organisateur->getHtmlValue('nom'); ?></h1>
            <?php if ($organisateur->getValue('logo') != '') { ?>
                <a href="<?php echo $url_uploads_organisateurs.$organisateur->getValue('logo').'?'.filemtime($rep_uploads_organisateurs.$organisateur->getValue('logo')) ?>" class="magnific-popup"><img src="<?php echo $url_uploads_organisateurs."s_".$organisateur->getValue('logo')."?".filemtime($rep_uploads_organisateurs."s_".$organisateur->getValue('logo')); ?>" alt="Logo" height="60" class="logo" /></a>
            <?php } ?>
            <div class="spacer"></div>
        </header>

        <ul class="menu_actions_lieu">
            <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::ACTOR) ) : ?>
                <li class="action_ajouter"><a href="/evenement-edit.php?idO=<?= (int)$get['idO'] ?>">Ajouter un événement de cet organisateur</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= UserLevel::AUTHOR || (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO']) && $_SESSION['Sgroupe'] <= UserLevel::ACTOR))) : ?>
                <li class="action_editer"><a href="/organisateur-edit.php?action=editer&amp;idO=<?= (int) $get['idO'] ?>">Modifier cet organisateur</a></li>
            <?php endif; ?>
        </ul>

        <div class="spacer"><!-- --></div>

        <article id="fiche">

            <div id="medias">
                <div id="photo">
                    <?php
                    $photo_principale = '';
                    if ($organisateur->getValue('photo') != '')
                    {
                    ?>
                        <a href="<?php echo $url_uploads_organisateurs.$organisateur->getValue('photo').'?'.filemtime($rep_uploads_organisateurs.$organisateur->getValue('photo')) ?>" class="magnific-popup">
                            <img src="<?php echo $url_uploads_organisateurs."s_".$organisateur->getValue('photo')."?".filemtime($rep_uploads_organisateurs."s_".$organisateur->getValue('photo')); ?>" alt="Photo"  />
                        </a>
                    <?php
                    }
                    ?>
                </div>
                <div class="spacer"><!-- --></div>
            </div>

            <?php
            $sql = "SELECT nom, lieu.idLieu AS idLieu FROM lieu_organisateur, lieu WHERE lieu_organisateur.idLieu=lieu.idLieu AND idOrganisateur=" . (int) $get['idO'];
            $req = $connector->query($sql);
            $lieux = '';
            if ($connector->getNumRows($req) > 0)
            {
                $lieux .= '<li>Lieu(x) gérés :';
                $lieux .= '<ul class="salles"> ';

                while ($tab = $connector->fetchArray($req))
                {
                    $lieux .= '<li><a href="/lieu/lieu.php?idL=' . (int)$tab['idLieu'] . '">' . sanitizeForHtml($tab['nom']) . "</a></li>";
                }
                $lieux .= '</ul></li>';
            }

            $sql = "SELECT pseudo, personne.idPersonne AS idPersonne FROM personne_organisateur, personne WHERE personne_organisateur.idPersonne=personne.idPersonne AND idOrganisateur=" . (int) $get['idO'];
            $req = $connector->query($sql);
            $membres = '';
            if ($connector->getNumRows($req) > 0)
            {
                if (isset($_SESSION['SidPersonne']) &&
                        ($authorization->isAuthor("organisateur", $_SESSION['SidPersonne'], $get['idO']) || $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO'])
                        )
                    )
                {
                    $membres .= '<li>Membre(s) :';
                    $membres .= '<ul class="salles"> ';

                    while ($tab = $connector->fetchArray($req))
                    {
                        $membres .= '<li>' . sanitizeForHtml($tab['pseudo']) . '</li>';
                    }
                    $membres .= '</ul></li>';
                }
            }
            ?>

            <div id="pratique">
                <ul>
                    <?php if (!empty($organisateur->getValue('URL'))) : $lieu_url = Text::getUrlWithName($organisateur->getValue('URL')); ?>
                        <li class="sitelieu"><a class="url lien_ext" href="<?= sanitizeForHtml($lieu_url['url']) ?>" target="_blank"><?= sanitizeForHtml($lieu_url['urlName']) ?></a>
                        </li>
                    <?php endif; ?>
                    <?php echo $lieux; ?>
                    <?php echo $membres; ?>
                </ul>
            </div>

            <?php if ( mb_strlen($organisateur->getHtmlValue('presentation')) > 0) { ?>
                <ul id="menu_descriptions">
                    <li class="ici">
                        <h3><a href="<?php echo basename(__FILE__); ?>?idO=<?php echo (int)$get['idO'] ?>">L'organisateur se présente</a></h3>
                    </li>
                </ul>
                <?php } ?>

            <div id="descriptions">
                <div class="description">
                    <p><?php echo $organisateur->getValue('presentation'); ?></p>
                </div>
            </div>

            <div class="spacer"></div>

        </article>

    </section> <!-- .vcard -->

    <div class="spacer"></div>


    <section id="prochains_evenements">

        <header>

            <h2>Événements <?php echo '<a href="/event/rss.php?type=organisateur_evenements&amp;id='.(int)$get['idO'].'" title="Flux RSS des prochains événements"><i class="fa fa-rss fa-lg" style="font-size:0.9em;color:#f5b045"></i></a>'; ?></h2>

            <!-- menu tous | futurs | anciens -->

            <div class="spacer"><!-- --></div>

        </header>

        <?php
        if ($evenements->getNbElements() == 0)
        {
            echo "<p>Pas d'événement actuellement annoncé pour <strong>".$organisateur->getHtmlValue('nom')."</strong></p>";
        }
        else
        {
        ?>

        <table>

        <?php

        $nbMois = 0;
        $moisCourant = 0;
        foreach ($evenements->getElements() as $id => $even)
        {
            $presentation = '';
            if ($even->getValue('description') != '')
            {
                $maxChar = Text::trouveMaxChar($even->getValue('description'), 50, 2);

                if (mb_strlen((string) $even->getValue('description')) > $maxChar)
                {
                    //$continuer = "<span class=\"continuer\"><a href=\"/event/evenement.php?idE=".$even->getValue('idEvenement')."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a></span>";
                    $presentation = Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($even->getValue('description'))), $maxChar);
                }
                else
                {
                    $presentation = Text::wikiToHtml(sanitizeForHtml($even->getValue('description')));
                }
            }
            if ($nbMois == 0)
            {
                $moisCourant = date2mois($even->getValue('dateEvenement'));
                echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst((string) mois2fr($moisCourant))."</td></tr>";
            }

            if (date2mois($even->getValue('dateEvenement')) != $moisCourant)
            {
                echo "<tr><td colspan=\"3\" class=\"mois\">".ucfirst((string) mois2fr(date2mois($even->getValue('dateEvenement'))));

                if (date2mois($even->getValue('dateEvenement')) == "01")
                {
                    echo " ".date2annee($even->getValue('dateEvenement'));
                }

                echo "</td></tr>";
            }

            $salle = '';
            $sql_salle = "SELECT nom FROM salle WHERE idSalle=" . (int) $even->getValue('idSalle');

            $req_salle = $connector->query($sql_salle);

            if ($connector->getNumRows($req_salle) > 0)
            {
                $tab_salle = $connector->fetchArray($req_salle);
                $salle = $tab_salle['nom'];
            }

            $nom_lieu = '';
            if ($even->getValue('idLieu') != 0)
            {
                $tab_lieu = $connector->fetchArray(
                $connector->query("SELECT nom FROM lieu WHERE idlieu='" . (int) $even->getValue('idLieu') . "'"));

                $nom_lieu = "<a href=\"/lieu/lieu.php?idL=" . (int)$even->getValue('idLieu') . "\" title=\"Voir la fiche du lieu : " . sanitizeForHtml($tab_lieu['nom']) . "\" >" . sanitizeForHtml($tab_lieu['nom']) . "</a>";
            }
            else
            {
                $nom_lieu = sanitizeForHtml($even->getValue('nomLieu'));
            }

        ?>
        <tr <?php if ($date_debut == $even->getValue('dateEvenement')) { echo "class=\"ici\""; } ?> class="evenement">

            <td><?php echo date2nomJour($even->getValue('dateEvenement')) ?></td>

            <td><?php echo date2jour($even->getValue('dateEvenement')) ?></td>

            <td class="flyer">
                <?= Evenement::mainFigureHtml($even->getValue('flyer'), $even->getValue('image'), $even->getValue('titre'), 60) ?>
            </td>

            <td>
                <h3>
                    <a href="/event/evenement.php?idE=<?= (int)$even->getValue('idEvenement') ?>"><?= Evenement::titreSelonStatutHtml(sanitizeForHtml($even->getValue('titre')), $even->getValue('statut')) ?></a>
                </h3>
                <p class="description"><?php echo $presentation; ?></p>

                        <p class="pratique"><?php echo afficher_debut_fin($even->getValue('horaire_debut'), $even->getValue('horaire_fin'), $even->getValue('dateEvenement')) . " " . sanitizeForHtml($even->getValue('prix')) ?></p>
                    </td>

            <td><?php echo $nom_lieu; ?></td>
            <td><?php echo $glo_tab_genre[$even->getValue('genre')] ?></td>

            <td class="lieu_actions_evenement">
                <?php
                if (
                (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
                || $_SESSION['SidPersonne'] == $even->getValue('idPersonne'))
                )
                ||  (isset($_SESSION['Saffiliation_lieu']) && !empty($get['idL']) && $get['idL'] == $_SESSION['Saffiliation_lieu'])
                ||  (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInOrganisateur($_SESSION['SidPersonne'], $get['idO']))
                || (isset($_SESSION['SidPersonne']) && $authorization->isPersonneInEvenementByOrganisateur($_SESSION['SidPersonne'], $id))
                || (isset($_SESSION['SidPersonne']) && $even->getValue('idLieu') != 0 && $authorization->isPersonneInLieuByOrganisateur($_SESSION['SidPersonne'], $even->getValue('idLieu')))
                )
                {
                ?>
                <ul>

                    <li ><a href="/event/copy.php?idE=<?= (int)$even->getValue('idEvenement') ?>" title="Copier cet événement"><?php echo $iconeCopier ?></a></li>
                    <li ><a href="/evenement-edit.php?action=editer&amp;idE=<?php echo (int)$even->getValue('idEvenement') ?>" title="Éditer cet événement"><?php echo $iconeEditer ?></a></li>
                    <li class=""><a href="#" id="btn_event_unpublish_<?php echo (int)$even->getValue('idEvenement'); ?>" class="btn_event_unpublish" data-id="<?php echo (int)$even->getValue('idEvenement') ?>"><?php echo $icone['depublier']; ?></a></li>
                </ul>
                <?php
                }
                ?>
            </td>
        </tr>

        <?php

            $moisCourant = date2mois($even->getValue('dateEvenement'));
            $nbMois++;
        }
        ?>

        </table>

        <?php } // nb even ?>

        <?php if (!empty($organisateur->getValue('URL'))) :
            $url_with_name = Text::getUrlWithName($organisateur->getValue('URL'))     ?>
            <p><br>Pour des informations complémentaires veuillez consulter <a href="<?= $url_with_name['url'] ?>" target='_blank'><?= sanitizeForHtml($url_with_name['urlName']) ?></a></p>
        <?php endif; ?>

    </section> <!-- evenements -->

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("event/_navigation_calendrier.inc.php"); ?>
</div>
<div id="colonne_droite" class="colonne">
    <?php echo $aff_menulieux; ?>
</div>

<div class="spacer"><!-- --></div>
<?php
include("_footer.inc.php");
?>
