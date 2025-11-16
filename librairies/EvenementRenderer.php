<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use Ladecadanse\Evenement;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Organisateur;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Utils\Text;

/**
 * Description of EvenementRenderer
 *
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class EvenementRenderer
{
    public static $iconStatus = [
        "actif" => "<div class='even-icon-status-round statut-actif' title='Publié'>&nbsp;</div>",
        "inactif" => "<div class='even-icon-status-round statut-inactif' title='Dépublié'>&nbsp;</div>",
        "annule" => "<div class='even-icon-status-round statut-annule' title='Annulé'>&nbsp;</div>",
        "complet" => "<div class='even-icon-status-round statut-complet' title='Complet'>&nbsp;</div>",
        "ancien" => "<div class='even-icon-status-round statut-ancien' title='Ancien'>&nbsp;</div>",
        "propose" => "<div class='even-icon-status-round statut-propose' title='Proposé'>&nbsp;</div>",
        "demande" => "demande"
    ];


    public static function titreSelonStatutHtml(string $titreHtml, string $statut, bool $isPersonneAllowedToEdit = false): string
    {
        $result = $titreHtml;

        $badge = '';
        if (isset(Evenement::$statuts_evenement[$statut]))
        {
            $badge = ' <span class="even-statut-label statut-' . $statut . '">' . mb_strtoupper(Evenement::$statuts_evenement[$statut]) . '</span>';
        }

        if ($statut == 'actif' || (in_array($statut, ['inactif', 'propose']) && !$isPersonneAllowedToEdit))
        {
            $badge = '';
        }

        if ($statut == "annule")
        {
            $result = '<strike>' . $titreHtml . '</strike>';
        }

        if ($statut == "complet")
        {
            $result = '<em>' . $titreHtml . '</em>';
        }

        return $result . $badge;
    }

    public static function getRefListHtml(string $refCsv): string
    {
        ob_start();
        $tab_ref = explode(";", strip_tags($refCsv));
        foreach ($tab_ref as $r)
        {
            $r = trim($r);
            if (mb_substr($r, 0, 3) == "www")
            {
                $r = "http://".$r;
            }
            ?>
            <li>
                <?php
                // it's an URL
                if (preg_match('#^(https?\\:\\/\\/)[a-z0-9_-]+\.([a-z0-9_-]+\.)?[a-zA-Z]{2,3}#i', $r))
                {
                    $url_with_name = Text::getUrlWithName($r);
                ?>
                    <i class="fa fa-hand-o-right" aria-hidden="true"></i>&nbsp;<a href="<?= sanitizeForHtml($url_with_name['url']) ?>" rel="external" target='_blank'><?= sanitizeForHtml($url_with_name['urlName']) ?></a>
                <?php
                }
                else
                {
                    echo sanitizeForHtml($r);
                }
                ?>
            </li>
            <?php
        }
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    public static function mainFigureHtml(string $flyer, string $image, string $titre, ?int $smallWidth = null): string
    {
        ob_start();

        // by default display small version
        $imgSmallFilePathPrefix = "s_";
        // 120 : max width when saving small version of uploaded flyers
        // if container width exceeds width of small version, choose big version
        if (empty($smallWidth) || (!empty($smallWidth) && $smallWidth > 120))
        {
            $imgSmallFilePathPrefix = '';
        }

        if (empty($flyer) && empty($image))
        {
            return '';
        }

        $imgHeight = '';
        if (!empty($flyer))
        {
            $href = Evenement::getWebPath(Evenement::getFilePath($flyer));
            $imgSrc = Evenement::getWebPath(Evenement::getFilePath($flyer, $imgSmallFilePathPrefix), isWithAntiCache: true);
            $imgAlt = "Flyer de ". sanitizeForHtml($titre);
            //$imgHeight = ImageDriver2::getProportionalHeightFromGivenWidth(self::getSystemFilePath(self::getFilePath($flyer, $imgSmallFilePathPrefix)), $smallWidth);
        }
        elseif (!empty($image))
        {
            $href = Evenement::getWebPath(Evenement::getFilePath($image));
            $imgSrc = Evenement::getWebPath(Evenement::getFilePath($image, $imgSmallFilePathPrefix), isWithAntiCache: true);
            $imgAlt = "Illustration de ". sanitizeForHtml($titre);
            //$imgHeight = ImageDriver2::getProportionalHeightFromGivenWidth(self::getSystemFilePath(self::getFilePath($image, $imgSmallFilePathPrefix)), $smallWidth);
        }
        ?>

        <a href="<?= $href ?>" class="magnific-popup">
            <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>" <?php if (!empty($smallWidth)) : ?> width="<?= $smallWidth ?>" height="<?= $imgHeight ?>" <?php endif; ?>>
        </a>

        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }


    public static function eventShortArticleHtml(array $tab_even, array $tab_events_today_in_region_orgas = []): string
    {
        $even_lieu = Evenement::getLieu($tab_even);

        ob_start();
        ?>

        <article id="event-<?= (int) $tab_even['e_idEvenement'] ?>" class="evenement-short">

            <header class="titre">
                <h3 class="left"><a href="/event/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= self::titreSelonStatutHtml(sanitizeForHtml($tab_even['e_titre']), $tab_even['e_statut']) ?></a></h3>
                <span class="right"><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></span>
                <div class="spacer"></div>
            </header>

            <figure class="flyer"><?= self::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre'], 100) ?></figure>

            <div class="description">
                <p>
                <?= Text::texteHtmlReduit(Text::wikiToHtml(sanitizeForHtml($tab_even['e_description'])), Text::trouveMaxChar($tab_even['e_description'], 60, 6), ' <a class="continuer" href="/event/evenement.php?idE=' . (int) $tab_even['e_idEvenement'] . '"> Lire la suite</a>'); ?>
                </p>
                <?php if (!empty($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']])): ?>
                    <?= Organisateur::getListLinkedHtml($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']]) ?>
                <?php endif; ?>
            </div>

            <div class="spacer"></div>

            <div class="pratique">
                <span class="left"><?= sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse'])); ?></span>
                <span class="right">
                    <?php
                    $horaire_complet = afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']);
                    if (!empty($tab_even['e_horaire_complement']))
                    {
                        $horaire_complet .= " ".$tab_even['e_horaire_complement'];
                    }
                    echo sanitizeForHtml($horaire_complet);
                    if (!empty($horaire_complet) && !empty($tab_even['e_prix']))
                    {
                        echo ", ";
                    }
                    echo sanitizeForHtml($tab_even['e_prix']);
                    ?>
                </span>
                <div class="spacer"></div>
            </div> <!-- fin pratique -->


        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }


    public static function eventTableRowHtml(array $tab_even, Authorization $authorization, bool $isWithLieu): string
    {
        // TODO: mv $glo_tab_genre to a class constant; $icone... to... ?
        global $glo_tab_genre, $glo_auj_6h, $iconeCopier, $iconeEditer, $icone;

        $vcard_starttime = '';
        if (mb_substr((string) $tab_even['e_horaire_debut'], 11, 5) != '06:00')
            $vcard_starttime = "T".mb_substr((string)$tab_even['e_horaire_debut'], 11, 5).":00";

        // depending on rendering in lieu or organisateur page
        $location = sanitizeForHtml($tab_even['s_nom']);
        if ($isWithLieu)
        {
            $even_lieu = Evenement::getLieu($tab_even);
            $location = Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']);
        }

        ob_start();
        ?>

        <tr class="<?php if ($glo_auj_6h == $tab_even['e_dateEvenement']) { echo "ici"; } ?> vevent evenement">

            <td class="dtstart">
                <a href="/index.php?courant=<?= sanitizeForHtml($tab_even['e_dateEvenement']) ?>"><?= date2nomJour($tab_even['e_dateEvenement']); ?>&nbsp;<?= date2jour($tab_even['e_dateEvenement']); ?><span class="value-title" title="<?= $tab_even['e_dateEvenement'].$vcard_starttime; ?>"></span></a><br>
                <span class="pratique"><?= afficher_debut_fin($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']) ?></span>
            </td>
            <td class="flyer photo">
                <?= self::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre'], 60) ?>
            </td>
            <td>
                <a class="url" href="/event/evenement.php?idE=<?= (int)$tab_even['e_idEvenement']?>">
                    <strong class="summary"><?= self::titreSelonStatutHtml(sanitizeForHtml($tab_even['e_titre']), $tab_even['e_statut']) ?></strong>
                </a><br>
                <span class="category"><?= $glo_tab_genre[$tab_even['e_genre']]; ?></span>
            </td>
            <td class="location">
                <?= $location ?>
                <?php if (!empty($even_lieu['nom'])) : ?>
                    <div class="location">
                        <span class="value-title" title="<?= sanitizeForHtml($even_lieu['nom']); ?>"></span>
                    </div>
                <?php endif; ?>
            </td>
            <?php if ($authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even)) : ?>
            <td class="lieu_actions_evenement">
                <ul>
                    <li><a href="/event/copy.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Copier cet événement"><?= $iconeCopier ?></a></li>
                    <li><a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Modifier cet événement"><?= $iconeEditer ?></a></li>
                    <li class=""><a href="#" id="btn_event_unpublish_<?= (int) $tab_even['e_idEvenement'] ?>" class="btn_event_unpublish" data-id="<?= (int) $tab_even['e_idEvenement'] ?>"><?= $icone['depublier']; ?></a></li>
                </ul>

            </td>
            <?php endif; ?>
        </tr>

        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }

    /**
     * id
     * horaire_debut, horaire_fin
     * region, localite, quartier, adresse
     * url
     * titre
     * description
     *
     * @param array<string, string> $event
     * @param string global const site domain
     */
    public static function getIcsValues(array $event, string $site_full_url): array
    {
        $even_lieu = Evenement::getLieu($event);
        return [
            'UID' => (int) $event['e_idEvenement'],
            'URI' => $site_full_url . "event/evenement.php?idE=" . (int) $event['e_idEvenement'],
            'DTSTAMP' => date('Ymd\THis', time()),
            'DTSTART' => date('Ymd\THis', date("U", strtotime((($event['e_horaire_debut'] != "0000-00-00 00:00:00") ? $event['e_horaire_debut'] : $event['e_dateEvenement'])))),
            'DTEND' => ($event['e_horaire_fin'] != "0000-00-00 00:00:00") ? date('Ymd\THis', date("U", strtotime($event['e_horaire_fin']))) : "",
            'LOCATION' => Text::escapeAndFoldString($even_lieu['nom'] . " - " . HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse'])),
            'SUMMARY' => Text::escapeAndFoldString($event['e_titre']),
            'DESCRIPTION' => Text::escapeAndFoldString($event['e_description']),
        ];
    }
}
