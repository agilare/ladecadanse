<?php
use Ladecadanse\HtmlShrink;
use Ladecadanse\Utils\Text;
use Ladecadanse\Evenement;

//Affichage du lieu selon son existence ou non dans la base
if ($evenement['e_idLieu'] != 0)
{
	$listeLieu = $connector->fetchArray(
	$connector->query("SELECT nom, adresse, quartier, localite.localite AS localite, region, URL, lat, lng FROM lieu, localite
		WHERE localite_id=localite.id AND idlieu='" . (int) $evenement['e_idLieu'] . "'"));

    $nom_lieu = "<a href=\"/lieu.php?idL=" . (int) $evenement['e_idLieu'] . "\">" . sanitizeForHtml($listeLieu['nom']) . "</a>";

    if ($evenement['e_idSalle'] != 0)
	{
		$req_salle = $connector->query("SELECT nom, emplacement FROM salle WHERE idSalle='" . (int) $evenement['e_idSalle'] . "'");
        $tab_salle = $connector->fetchArray($req_salle);
		$nom_lieu .=  " - ".$tab_salle['nom'];
	}
}
else
{
	$nom_lieu = $listeLieu['nom'] = sanitizeForHtml($evenement['e_nomLieu']);
    $listeLieu['adresse'] = sanitizeForHtml($evenement['e_adresse']);
    $listeLieu['quartier'] = sanitizeForHtml($evenement['e_quartier']);
    $listeLieu['localite'] = "";
}
?>


<div id="evenements">

    <p><a href="index.php?courant=<?= $evenement['e_dateEvenement'] ?>"><?= ucfirst((string) date_fr($evenement['e_dateEvenement'], "annee")) ?></a></p>

    <div class="evenement">

        <div class="titre">
            <span class="left">
                <a href="/event/evenement.php?idE=<?= (int) $evenement['e_idEvenement'] ?>"><?= Evenement::titreSelonStatutHtml(sanitizeForHtml($evenement['e_titre']), $evenement['e_statut']) ?></a></span>
            <span class="right"><?php echo $nom_lieu ?></span>
            <div class="spacer"></div>
        </div>

        <figure class="flyer"><?= Evenement::mainFigureHtml($evenement['e_flyer'], $evenement['e_image'], $evenement['e_titre'], 100) ?></figure>
        <div class="description">
            <p>
            <?= Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($evenement['e_description'])), Text::trouveMaxChar($evenement['e_description'], 60, 6), ' <a class="continuer" href="/event/evenement.php?idE=' . (int) $evenement['e_idEvenement'] . '"> Lire la suite</a>'); ?>
            </p>
        </div>

        <div class="spacer"></div>

        <div class="pratique">
            <span class="left"><?= HtmlShrink::adresseCompacteSelonContexte(null, $listeLieu['localite'], $listeLieu['quartier'], $listeLieu['adresse']) ?></span>
            <span class="right">
                <?php
                $horaire_complet = afficher_debut_fin($evenement['e_horaire_debut'], $evenement['e_horaire_fin'], $evenement['e_dateEvenement'])." " . sanitizeForHtml($evenement['e_horaire_complement']);echo $horaire_complet;
                if (!empty($horaire_complet) && !empty($evenement['e_prix']))
                {
                    echo ", ";
                }
                echo sanitizeForHtml($evenement['e_prix']);
                ?>
            </span>
            <div class="spacer"></div>
        </div>
        <!-- fin pratique -->

    </div>
</div> <!-- evenements -->
