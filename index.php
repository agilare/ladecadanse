<?php

require_once("config/reglages.php");


require_once($rep_librairies."Sentry.php");
$videur = new Sentry();

$page_titre_region = 'Genève';
if ($_SESSION['region'] == 'vd')
{
    $page_titre_region = "Lausanne";   
}
elseif ($_SESSION['region'] == 'fr')
{
    $page_titre_region = "Fribourg";   
}

$nom_page = "index";
$page_titre = " agenda de sorties à ".$page_titre_region." : concerts, soirées, films, théâtre, expos, bars, cinémas";
$page_description = "Programme des prochains événements festifs et culturels à Genève et Lausanne : fêtes, concerts et soirées, cinéma,
théâtre, expositions, vernissages, conférences, lieux culturels et alternatifs";

include("includes/header.inc.php");

if (isset($_GET['auj']))
{
	$get['auj'] = $_GET['auj'];
}
else
{
	$get['auj'] = date("Y-m-d", time() - 21600);
}

$tab_auj = explode("-", $get['auj']);
$auj2 = date("Y-m-d", mktime(0, 0, 0, $tab_auj[1], $tab_auj[2], $tab_auj[0]));

$sql_rf = "";
if ($_SESSION['region'] == 'ge')
    $sql_rf = " 'rf', ";


$req_even = $connector->query("SELECT idEvenement, genre, idLieu, idSalle, nomLieu, adresse, quartier, localite.localite AS localite, urlLieu, statut,
 titre, idPersonne, dateEvenement, URL1, ref, flyer, image, description, horaire_debut, horaire_fin,
 horaire_complement, prix, prelocations
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '".$auj2."%' AND statut!='inactif' AND region IN ('".$connector->sanitize($_SESSION['region'])."', ".$sql_rf." 'hs')   
 ORDER BY CASE `genre`
        WHEN 'fête' THEN 1
        WHEN 'cinéma' THEN 2
        WHEN 'théâtre' THEN 3
        WHEN 'expos' THEN 4
        WHEN 'divers' THEN 5
        END, dateAjout DESC");

$nb_evenements = $connector->getNumRows($req_even);
$event_count = ['ge' => $connector->getNumRows($req_even), 'vd' => 0];
$req_even_ge_nb = $connector->query("SELECT COUNT(idEvenement) AS nb
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '".$auj2."%' AND statut!='inactif' AND region IN ('ge', 'rf', 'hs')");
$event_count['ge'] = $connector->fetchAll($req_even_ge_nb)[0];
$req_even_vd_nb = $connector->query("SELECT COUNT(idEvenement) AS nb
 FROM evenement, localite
 WHERE evenement.localite_id=localite.id AND dateEvenement LIKE '".$auj2."%' AND statut!='inactif' AND region IN ('vd', 'hs')");
$event_count['vd'] = $connector->fetchAll($req_even_vd_nb)[0];
?>

<div id="contenu" class="colonne">

<?php
if (!isset($_COOKIE['msg_orga_benevole'])) // isset($_GET['debug']) && 
{
?>
    <div style="position:relative;padding:0.7em 0.5em;margin:0em 0;background:#fff3cd;color:#856404">
        <h2 style="padding:0; margin:0.1em 0 0.4em 0.1em;font-size:1.3em;color:#856404">Développer La décadanse</h2>
        <a style="position:absolute;right:0;top:0;padding:5px;font-size: 1rem;font-weight: 700;color:#856404" href="#" onclick="SetCookie('msg_orga_benevole', 1, 180);this.parentNode.style.display = 'none';return false;">&times;</a>
        <p style="line-height:18px">Je recherche actuellement des <strong>programmeur-euse-s</strong> (bénévoles) afin de m'aider à améliorer La décadanse : design, fonctionnalités, modernisation. <a href="https://github.com/agilare/ladecadanse/"><i class="fa fa-github" aria-hidden="true"></i> GitHub</a>
            <br>Si ça vous intéresse envoyez-moi un ptit message : <a href="mailto:michel@ladecadanse.ch">michel@ladecadanse.ch</a></p>
    </div>
<?php
}
?>    
    
	<div id="entete_contenu">
        <h2 class="accueil" style="margin: 0;">Aujourd’hui <a href="<?php echo $url_site ?>rss.php?type=evenements_auj" title="Flux RSS des événements du jour" style="font-size:12px;vertical-align: top;" class="desktop"><i class="fa fa-rss fa-lg" style="color:#f5b045"></i></a></h2>
        <?php getMenuRegions($glo_regions, $get, $event_count); ?>
        <div class="spacer"></div>           
        <h2 style="width:65%;font-size: 1.4em;margin-top: 0;"><small><?php echo ucfirst(date_fr($get['auj'])); ?></small></h2>                             
	</div>
	<div class="spacer"><!-- --></div>

	<div id="prochains_evenements" >
        
    <?php

    if ($nb_evenements == 0)
    {
        echo msgInfo("Pas d’événement prévu aujourd’hui");
    }

    $dateCourante = ' ';

    $tab_genres = $glo_tab_genre;
    $genre_courant = '';
    $genre_prec = '';
    $genre_fr = "";
    $i = 0;

    while ($tab_even = $connector->fetchArray($req_even))
    {
        if ($tab_even['genre'] != $genre_courant)
        {
            if ($genre_courant != '')
            {
                $genre_prec = replace_accents($tab_genres[$genre_courant]);
                echo "</div>";
                $i = 0;
            }

            $genre_fr = ucfirst($glo_tab_genre[$tab_even['genre']]);

            $proch = '';
    /* 		if ($np = next(&$tab_genres))
                $proch = replace_accents($np); */
            if ($np = next($tab_genres))
                $proch = replace_accents($np);			
        ?>
            <div class="genre">	
                <div class="genre-titre">
                    <h3 id="<?php echo mb_strtolower(replace_accents($genre_fr)); ?>"><?php echo $genre_fr; ?></h3>

                    <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1 && $tab_even['genre'] != 'divers') { ?>
                    <a class="genre-jump" href="#<?php echo $proch; ?>"><i class="fa fa-long-arrow-down"></i></a>
                    <?php } else { ?>
                    <span style="float: right;margin: 0.2em;padding: 0.4em 0.8em;">&nbsp;</span>
                    <?php } ?>	

                    <?php if (isset($_SESSION['Sgroupe']) && $_SESSION['Sgroupe'] <= 1 && $tab_even['genre'] != 'fête') { ?>
                    <a class="genre-jump" href="#<?php echo $genre_prec; ?>"><i class="fa fa-long-arrow-up"></i></a>
                    <?php } ?>

                    <div class="spacer"></div>
                </div>
        <?php
        }

        $genre_courant = $tab_even['genre'];


        //Affichage du lieu selon son existence ou non dans la base
        if ($tab_even['idLieu'] != 0)
        {
            $listeLieu = $connector->fetchArray(
            $connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, URL FROM lieu, localite WHERE lieu.localite_id=localite.id AND idlieu='".$tab_even['idLieu']."'"));

            $infosLieu = "<a href=\"".$url_site."lieu.php?idL=".$tab_even['idLieu']."\" title=\"Voir la fiche du lieu : ".htmlspecialchars($listeLieu['nom'])."\" >".htmlspecialchars($listeLieu['nom'])."</a>";

            if ($tab_even['idSalle'])
            {
                $req_salle = $connector->query("SELECT nom FROM salle WHERE idSalle='".$tab_even['idSalle']."'");
                $tab_salle = $connector->fetchArray($req_salle);
                $infosLieu .= " - ".$tab_salle['nom'];
            }
        }
        else
        {

            $listeLieu['nom'] = htmlspecialchars($tab_even['nomLieu']);
            $infosLieu = htmlspecialchars($tab_even['nomLieu']);
            $listeLieu['adresse'] = htmlspecialchars($tab_even['adresse']);
            $listeLieu['quartier'] = htmlspecialchars($tab_even['quartier']);
            $listeLieu['localite'] = htmlspecialchars($tab_even['localite']);
        }

        $even_adresse = get_adresse(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']);

        if ($i > 1 && ($i % 2 != 0))
        {
            $region = $glo_regions[$_SESSION['region']];
            if ($_SESSION['region'] == 'vd')
                $region = "Lausanne";
        ?>
                <p class="rappel_date"><?php echo $region; ?>, aujourd’hui, <?php echo mb_strtolower($genre_fr); // ",".date_fr($get['auj']); ?></p>

        <?php	
        }
    ?>

        <div class="evenement">

            <div class="titre">
                <span class="left">
                <?php
                $maxChar = trouveMaxChar($tab_even['description'], 60, 6);
                $titre_url = '
        <a href="'.$url_site.'evenement.php?idE='.$tab_even['idEvenement'].'&amp;tri_agenda='.$get['tri_agenda'].'&amp;courant='.$get['courant'].'"
        title="Voir la fiche complète de l\'événement">'.securise_string($tab_even['titre']).'</a>';

                echo titre_selon_statut($titre_url, $tab_even['statut']);

                ?>
                </span>
                <span class="right"><?php echo $infosLieu ?></span>
                <div class="spacer"></div>
            </div>

            <div class="flyer">
            <?php
            if (!empty($tab_even['flyer']))
            {
                $imgInfo = @getimagesize($rep_images.$tab_even['flyer']);
                ?>

                <a href="<?php echo $IMGeven.$tab_even['flyer']; ?>" class="magnific-popup"><img src="<?php echo $IMGeven."s_".$tab_even['flyer']; ?>" alt="Flyer" width="100" /></a>

                <?php
            }
            else if (!empty($tab_even['image']))
            {
                $imgInfo = @getimagesize($rep_images.$tab_even['image']);

                ?>

                <a href="<?php echo $IMGeven.$tab_even['image']; ?>" class="magnific-popup"><img src="<?php echo $IMGeven."s_".$tab_even['image']; ?>" alt="Photo" width="100" /></a>

            <?php 
            }
            ?>

            </div>
            <div class="description">
            <?php
            //reduction de la description pour la caser dans la boite "desc"
            if (mb_strlen($tab_even['description']) > $maxChar)
            {

                $continuer = " <a class=\"continuer\" href=\"".$url_site."evenement.php?idE=".$tab_even['idEvenement']."\" title=\"Voir la fiche complète de l'événement\"> Lire la suite</a>";

                echo texteHtmlReduit(textToHtml(htmlspecialchars($tab_even['description'])),$maxChar, $continuer);
                //echo TronqueHtml(textToHtml(htmlspecialchars($tab_even['description'])), $maxChar, ' ', ' ...');

        /* echo $continuer; */
                //echo texteHtmlReduit(htmlspecialchars($tab_even['description']), $maxChar);
            }
            else
            {
                echo textToHtml(htmlspecialchars($tab_even['description']));
            }
            ?>
            </div>

            <div class="spacer"></div>
                <div class="pratique">

                <span class="left"><?php echo htmlspecialchars($even_adresse); ?></span><span class="right"><?php echo afficher_debut_fin($tab_even['horaire_debut'], $tab_even['horaire_fin'], $tab_even['dateEvenement']);
                if (!empty($tab_even['prix']))
                {
                    if (!empty($tab_even['horaire_debut']) || !empty($tab_even['horaire_fin']))
                    {
                        echo ", ";
                    }
                    echo htmlspecialchars($tab_even['prix']);

                }
                ?>

                </span>
                <div class="spacer"></div>

            </div> <!-- fin pratique -->

            <div class="edition">
            <?php
                $sql = "
                SELECT idCommentaire
                 FROM commentaire
                 WHERE id='".$tab_even['idEvenement']."' AND statut='actif'";


                $commentaires = "";
                $req = $connector->query($sql);
                $nb_comm = $connector->getNumRows($req);
                if ($nb_comm > 0)
                {
                    $pluriel = "";
                    if ($nb_comm > 1)
                        $pluriel = "s";

                    $commentaires = '<span class="nb_commentaires">'.$icone['commentaire'].'
                    <a href="'.$url_site.'evenement.php?idE='.$tab_even['idEvenement'].'#commentaires"
                    title="Voir le'.$pluriel.' '.$nb_comm.' commentaires">'.$nb_comm.' commentaire'.$pluriel.'</a>';
                    $commentaires .= '</span>';
                }

                echo $commentaires;

            //Peut ètre édité par les 'auteurs' sinon par le propre publicateur de cet événement
            if (isset($_SESSION['Sgroupe']) && ($_SESSION['Sgroupe'] <= 6
            || $_SESSION['SidPersonne'] == $tab_even['idPersonne'])
            || (isset($_SESSION['Saffiliation_lieu']) && !empty($tab_even['idLieu']) && $tab_even['idLieu'] == $_SESSION['Saffiliation_lieu'])
            || isset($_SESSION['SidPersonne']) && est_organisateur_evenement($_SESSION['SidPersonne'], $tab_even['idEvenement'])
            || isset($_SESSION['SidPersonne']) && $tab_even['idLieu'] != 0 && est_organisateur_lieu($_SESSION['SidPersonne'], $tab_even['idLieu'])	
            )
            {
                echo '<ul class="menu_actions">';
                echo "<li class=\"action_copier\"><a href=\"".$url_site."copierEvenement.php?idE=".$tab_even['idEvenement']."\" title=\"Copier l'événement\">Copier vers d'autres dates</a></li>";
                echo "<li class=\"action_editer\"><a href=\"".$url_site."ajouterEvenement.php?action=editer&amp;idE=".$tab_even['idEvenement']."\" title=\"Modifier l'événement\">Modifier</a></li>";
                echo '</ul>';

            }
            ?>
        </div> <!-- fin edition -->
            <div class="spacer"></div>
        </div> <!-- evenement -->

        <div class="spacer"></div>

        <?php
        $i++;
    } //while
if ($genre_courant != '')
{
    echo "</div>";
}
?>
</div> <!-- prochains_evenements -->

</div>
<!-- fin contenu -->


<div id="colonne_gauche" class="colonne">

    <?php include("includes/navigation_calendrier.inc.php"); ?>


    <div id="dernieres" style="margin-top:40px;width: 100%;">


    <h2>Partenaires</h2>
    <ul style="padding-left:5px">
        <li style="margin:2px 0;float:left;"><a href="https://epic-magazine.ch/" onclick="window.open(this.href,'_blank');return false;"><img src="images/interface/EPIC_noir.png" alt="EPIC Magazine" width="150" style="border:1px solid #eaeaea" /></a></li>

    <li style="margin:2px 0;float:left;"><a href="http://www.radiovostok.ch" onclick="window.open(this.href,'_blank');return false;"><img src="images/interface/radio_vostok.png" alt="Radio Vostok" width="150" height="59" style="border:1px solid #eaeaea" /></a></li>

    <li style="margin:2px 0;float:left;">
    <a href="https://www.darksite.ch" onclick="window.open(this.href,'_blank');return false;"><img src="images/interface/darksite.png" alt="Darksite" width="150" height="43" style="border:1px solid #eaeaea" /></a></li>
    </ul>
    </div>

    <?php

    /**
    * les commentaires la dernière ajoutée d'abord
    */
    /* $req_commentaires = $connector->query("SELECT idCommentaire, idEvenement, titre, contenu, idPersonne, dateAjout FROM commentaire
     WHERE actif=1 ORDER BY dateAjout DESC LIMIT 0,5");
    $dateAvant = '';

    while($tab_commentaires = $connector->fetchArray($req_commentaires))
    {

        $req_auteur = $connector->query("SELECT pseudo FROM personne WHERE idPersonne=".$tab_commentaires['idPersonne']);
        $tab_auteur = $connector->fetchArray($req_auteur);

        $da = explode(" ", $tab_commentaires['dateAjout']);


        echo "<h2>".date_fr($da[0])."</h2>";


        echo "<div>
        <h3>".$tab_auteur['pseudo']." sur ".$tab_commentaires['idEvenement']."</h3>\n
        <div class=\"spacer\"></div>\n";

        echo "<p>".textToHtml(htmlspecialchars($tab_commentaires['contenu']))."<a href=\"#\">Lire la suite</a></p>";
        echo "<div class=\"spacer\"></div>\n
        </div>\n";
        $dateAvant = $da[0];
    }

    @mysql_free_result($req_dernUpdate); */



    ?>
	<!--
	</div>
	Fin derniers_commentaires -->
</div>
<!-- Fin Colonnegauche -->


<div id="colonne_droite" class="colonne">

    <div id="dernieres"><span style="float:right;margin-top:0.4em;padding:0.2em;">
    <a href="<?php echo $url_site ?>rss.php?type=evenements_ajoutes" title="Flux RSS des derniers événements ajoutés"><i class="fa fa-rss fa-lg" style="color:#f5b045"></i></a></span>
    <h2>Derniers événements ajoutés</h2>

    <div id="derniers_evenements">
    <?php
    $sql_rf = "";
    if ($_SESSION['region'] == 'ge')
        $sql_rf = " 'rf', ";

    $req_dern_even = $connector->query("
    SELECT idEvenement, titre, dateEvenement, dateAjout, nomLieu, idLieu, idSalle, flyer, image, statut
    FROM evenement WHERE region IN ('".$connector->sanitize($_SESSION['region'])."', ".$sql_rf." 'hs') AND statut!='inactif' ORDER BY dateAjout DESC LIMIT 0, 6
    ");

    $date_ajout_courante = "";

    // Création de la section si il y a moins un lieu
    if ($connector->getNumRows($req_dern_even) > 0)
    {
        while ($tab_dern_even = $connector->fetchArray($req_dern_even))
        {
            $date_ajout = mb_substr($tab_dern_even['dateAjout'], 0, 10);

            if ($tab_dern_even['idLieu'] != 0)
            {

                $infosLieu = "<a href=\"".$url_site."lieu.php?idL=".$tab_dern_even['idLieu']."\" title=\"Voir la fiche du lieu : ".htmlspecialchars($tab_dern_even['nomLieu'])."\" >".htmlspecialchars($tab_dern_even['nomLieu'])."</a>";

                if ($tab_dern_even['idSalle'] != 0)
                {
                    $req_salle = $connector->query("SELECT nom, emplacement FROM salle
                    WHERE idSalle='".$tab_dern_even['idSalle']."'");
                    $tab_salle = $connector->fetchArray($req_salle);
                    $infosLieu .=  " - ".$tab_salle['nom'];

                }
            }
            else
            {
                $infosLieu = htmlspecialchars($tab_dern_even['nomLieu']);
            }

            echo "<div class=\"dernier_evenement\">";

            echo "<div class=\"flyer\">";

            if (!empty($tab_dern_even['flyer']))
            {
                $imgInfo = @getimagesize($rep_images.$tab_dern_even['flyer']);

                ?>

                <a href="<?php echo $IMGeven.$tab_dern_even['flyer']; ?>" class="magnific-popup"><img src="<?php echo $IMGeven."t_".$tab_dern_even['flyer']; ?>" alt="Flyer" width="60" /></a>

                <?php

                //echo lien_popup($IMGeven.$tab_dern_even['flyer'], "flyer", $imgInfo[0]+20, $imgInfo[1]+20, "<img src=\"".$IMGeven."t_".$tab_dern_even['flyer']."\" alt=\"Flyer\" width=\"60\" />");	

            }
            else if (!empty($tab_dern_even['image']))
            {
                $imgInfo = @getimagesize($rep_images.$tab_dern_even['image']);

                ?>

                <a href="<?php echo $IMGeven.$tab_dern_even['image']; ?>" class="magnific-popup"><img src="<?php echo $IMGeven."s_".$tab_dern_even['image']; ?>" alt="Photo" width="60" /></a>

                <?php 

                //echo lien_popup($IMGeven.$tab_dern_even['image'], "Image", $imgInfo[0]+20, $imgInfo[1]+20, "<img width=\"60\" src=\"".$IMGeven."s_".$tab_dern_even['image']."\" alt=\"Image\" />");


            } 

            /*
            if (!empty($tab_dern_even['flyer']))
            {
                $imgInfo = getimagesize($rep_images.$tab_dern_even['flyer']);


                //echo lien_popup($IMGeven.$tab_even['flyer'], "Flyer", $imgInfo[0]+20, $imgInfo[1]+20, "<img src=\"".$IMGeven."s_".$tab_even['flyer']."\" alt=\"Flyer\" width=\"100\" />");
                ?>

                <a href="<?php echo $IMGeven.$tab_dern_even['flyer']; ?>" class="magnific-popup"><img src="<?php echo $IMGeven."s_".$tab_dern_even['flyer']; ?>" alt="Flyer" width="100" /></a>

            <?php

            }
            else if (!empty($tab_dern_even['image']))
            {
                $imgInfo = @getimagesize($rep_images.$tab_dern_even['image']);
                //echo lien_popup($IMGeven.$tab_even['image']."?".filemtime($rep_images_even.$tab_even['image']), "Image", $imgInfo[0]+20, $imgInfo[1]+20,"<img src=\"".$IMGeven."s_".$tab_even['image']."?".filemtime($rep_images_even.$tab_even['image'])."\" alt=\"Image\" width=\"100\" />");

                ?>

                <a href="<?php echo $IMGeven.$tab_dern_even['image']; ?>" class="magnific-popup"><img src="<?php echo $IMGeven."s_".$tab_dern_even['image']; ?>" alt="Photo" width="100" /></a>

            <?php		
            }
            */

            echo "</div>";



            echo "<h4>";
            $titre_url = "<a href=\"".$url_site."evenement.php?idE=".$tab_dern_even['idEvenement']."\" title=\"\" >".
            securise_string($tab_dern_even['titre']).'</a>';



            echo titre_selon_statut($titre_url, $tab_dern_even['statut']);

            echo "</h4>";
            echo '<h5 style="font-size:1em;color:#5C7378">';
            echo $infosLieu;
            echo "</h5>";
            echo "<p>le&nbsp;<a href=".$url_site."agenda.php?courant=".$tab_dern_even['dateEvenement'].">".date_fr($tab_dern_even['dateEvenement'])."</a></p><div class=\"spacer\"></div>";
            echo "</div>";
            echo '<div class="spacer"><!-- --></div>';
            $date_ajout_courante = $date_ajout;
        }
    }
    ?>

    </div>

    <?php if (0) { ?>
    <div id="dernieres" style="padding:0.6em 0.2em;margin:1em 0;background:#ff5;border-radius:5px;">
    <h2 style="padding:0; margin:0.1em 0 0.4em 0.1em;font-size:1.3em">Touche pas à ma sécu&nbsp;!</h2>
    <p style="margin:2px 0;line-height:17px;font-weight:bold;">Signez la <a href="http://www.grandconseildelanuit.ch/files/GCN_PETITION_CONCORDAT_2014.pdf" onclick="window.open(this.href,'_blank');return false;">pétitition (PDF)</a>
    </p>
    <p style="line-height:17px">Contre l’extension du concordat sur les entreprises
    de sécurité (CES) à l’ensemble du personnel assurant
    des tâches de protection et de surveillance dans les
    établissements publics</p>
    </div>
    <?php } ?>

    <!-- Fin derniers_evenements -->
    <iframe style="margin-top:1em;width:180px;" src="https://www.facebook.com/plugins/likebox.php?href=http%3A%2F%2Fwww.facebook.com%2Fpages%2FLa-decadanse%2F119538226363&amp;width=180&amp;colorscheme=light&amp;show_faces=false&amp;stream=false&amp;header=true&amp;height=62" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:180px; height:62px;" allowTransparency="true"></iframe>

</div> <!-- fin dernieres -->
</div> <!-- Fin colonne_droite -->

<div class="spacer"><!-- --></div>

<?php
include("includes/footer.inc.php");
?>
