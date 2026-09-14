<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse;

use Ladecadanse\Evenement;
use Ladecadanse\EvenementCalendarRenderer;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Organisateur;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Utils\DateHelper;
use Ladecadanse\Utils\RefList;
use Ladecadanse\Utils\Text;
use Ladecadanse\Utils\WebLink;

/**
 * Description of EvenementRenderer
 *
 * @author Michel Gaudry <michel@ladecadanse.ch>
 */
class EvenementRenderer
{
    /**
     * Plafond de charge utile de la description d'une carte d'événement.
     * La hauteur du bloc, elle, est plafonnée par le line-clamp de global.css.
     */
    public const DESCRIPTION_MAX_CHARS = 60 * 6;

    /**
     * Longueur au-delà de laquelle le prix d'une carte est coupé, avec les repères de temporalité
     * (#51) : rangé sous l'adresse, il doit y tenir sur une ligne.
     */
    public const PRICE_MAX_CHARS = 40;

    /**
     * Largeur d'une heure d'événement sur la barre de progression (#51), en pixels : la barre dit
     * la durée par sa longueur avant de dire la part écoulée par son remplissage.
     */
    public const PROGRESS_PX_PER_HOUR = 20;

    /**
     * Nombre de vignettes servies en chargement immédiat avant de basculer en `loading="lazy"`.
     *
     * L'agenda d'une journée chargée rend jusqu'à 140 vignettes, toutes téléchargées d'emblée
     * jusqu'ici. Les premières doivent le rester : `lazy` sur une image déjà visible retarde
     * son affichage au lieu de l'avancer, le navigateur ne la demandant qu'une fois la mise en
     * page calculée.
     */
    public const EAGER_FIGURES_PER_REQUEST = 6;

    /**
     * Vignettes déjà rendues dans cette requête, pour distinguer celles du premier écran.
     *
     * L'agenda groupe ses événements par genre, en deux boucles imbriquées : aucun indice de
     * rang ne remonte jusqu'ici, d'où ce compteur plutôt qu'un paramètre à faire traverser
     * index.php, lieu.php et organisateur.php.
     */
    private static int $figuresRendered = 0;

    /** Comportement du lien de dépublication après succès : masque la ligne de l'événement */
    public const UNPUBLISH_THEN_HIDE = 'hide';
    /** Comportement du lien de dépublication après succès : met à jour la pastille de statut de la ligne */
    public const UNPUBLISH_THEN_STATUS = 'status';
    /** Comportement du lien de dépublication après succès : recharge la page */
    public const UNPUBLISH_THEN_RELOAD = 'reload';

    public static $iconStatus = [
        "actif" => "<div class='even-icon-status-round statut-actif' title='Publié'>&nbsp;</div>",
        "inactif" => "<div class='even-icon-status-round statut-inactif' title='Dépublié'>&nbsp;</div>",
        "annule" => "<div class='even-icon-status-round statut-annule' title='Annulé'>&nbsp;</div>",
        "complet" => "<div class='even-icon-status-round statut-complet' title='Complet'>&nbsp;</div>",
        "ancien" => "<div class='even-icon-status-round statut-ancien' title='Ancien'>&nbsp;</div>",
        "propose" => "<div class='even-icon-status-round statut-propose' title='Proposé'>&nbsp;</div>",
        "demande" => "demande"
    ];


    public static function titreSelonStatutHtml(string $titre, string $statut, bool $isPersonneAllowedToEdit = false): string
    {
        // Le titre est du texte brut : on l'échappe ici, au plus près du rendu, plutôt que chez
        // chaque appelant (l'un d'eux l'avait oublié, d'où une XSS stockée sur les titres proposés
        // par le formulaire public, lus par les éditeurs). Les balises de statut ajoutées ensuite
        // sont les nôtres et restent intactes.
        $titreHtml = sanitizeForHtml($titre);
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

    /**
     * Lien ajax de dépublication, traité par Events.init() dans web/js/global.js
     *
     * L'appelant est responsable du contrôle des droits d'édition ;
     * event/actions.php les revérifie de son côté.
     *
     * @param int $idEvenement
     * @param string $labelHtml contenu du lien : icône et/ou texte
     * @param string $onSuccess une des constantes self::UNPUBLISH_THEN_*
     */
    public static function unpublishLinkHtml(int $idEvenement, string $labelHtml, string $onSuccess = self::UNPUBLISH_THEN_HIDE): string
    {
        return '<a href="#" id="btn_event_unpublish_' . $idEvenement . '" class="btn_event_unpublish"'
            . ' data-id="' . $idEvenement . '" data-on-success="' . sanitizeForHtml($onSuccess) . '"'
            . ' title="Dépublier cet événement">' . $labelHtml . '</a>';
    }

    /**
     * Colonne « par » des listes d'administration : le lien vers la fiche de l'auteur.
     *
     * Un événement peut n'avoir aucun auteur — le formulaire public accepte les propositions
     * sans compte, et `idPersonne` vaut alors 0. Les deux listes rendaient quand même un lien,
     * vers `/user/dashboard.php?idP=0` et sans libellé : rien à lire, rien à cliquer, et une
     * infobulle vide.
     *
     * @param int $maxCaracteres Coupe le texte visible au-delà, le pseudo entier restant dans
     *                           l'infobulle ; 0 pour ne pas couper
     */
    public static function authorLinkHtml(int $idPersonne, ?string $pseudo, int $maxCaracteres = 0): string
    {
        if ($idPersonne <= 0 || $pseudo === null || $pseudo === '')
        {
            return 'anonyme';
        }

        $texte = $maxCaracteres > 0 ? Text::truncateCharsToHtml($pseudo, $maxCaracteres) : sanitizeForHtml($pseudo);

        return '<a href="/user/dashboard.php?idP=' . $idPersonne . '" title="' . sanitizeForHtml($pseudo) . '">' . $texte . '</a>';
    }

    /**
     * @param string $horaire_debut datetime
     * @param string $horaire_fin datetime
     * @param string $date_evenement date
     * @return string 21:00 or 21:00 - 01:00 or fin : 01:00
     */
    public static function schedulesToHhMm(string $horaire_debut, string $horaire_fin, string $date_evenement): string
    {
        $result = self::datetimeToHhMm($horaire_debut, $date_evenement);
        // both times exists : add separator
        if ($horaire_fin != DateHelper::isoToNextDay($date_evenement) . " 06:00:01" && $horaire_fin != "0000-00-00 00:00:00"
            && $horaire_debut != DateHelper::isoToNextDay($date_evenement) . " 06:00:01" && $horaire_debut != "0000-00-00 00:00:00")
        {
            $result .= " – ";
        }
        // (rare)
        if ($horaire_fin != DateHelper::isoToNextDay($date_evenement) . " 06:00:01" && $horaire_fin != "0000-00-00 00:00:00"
            && $horaire_debut == DateHelper::isoToNextDay($date_evenement) . " 06:00:01")
        {
            $result .= "fin : ";
        }

        $result .= self::datetimeToHhMm($horaire_fin, $date_evenement);

        return $result;
    }

    /**
     * @param string $datetime 2026-04-28 09:30:00
     * @param string $date_evenement 2026-04-28
     * @return string 09:30 or empty if $datetime is beyond event day time
     */
    public static function datetimeToHhMm(string $datetime, string $date_evenement): string
    {
        if ($datetime > DateHelper::isoToNextDay($date_evenement) . " 06:00:00" || $datetime == "0000-00-00 00:00:00"
            || empty($datetime)
            )
        {
            return "";
        }

        return mb_substr($datetime, 11, -3);
    }

    /**
     * Valeur ISO 8601 de la propriété hCalendar dtstart.
     *
     * Date seule quand l'événement n'a pas d'horaire (sentinelle 06:00:01), date et heure sinon.
     * La date vient de horaire_debut et non de dateEvenement : un événement qui commence après
     * minuit appartient à la journée d'agenda de la veille (borne des 6h, cf. datetimeToHhMm),
     * son dtstart réel est donc le lendemain de dateEvenement. Cette lecture d'un horaire, y
     * compris la tolérance aux dates aberrantes, appartient à DateHelper::horaireInstant().
     *
     * @param string $date_evenement 2026-04-28
     * @param string|null $horaire_debut 2026-04-28 21:30:00
     * @return string 2026-04-28T21:30:00 ou 2026-04-28
     */
    public static function dtstartIso(string $date_evenement, ?string $horaire_debut): string
    {
        $instant = DateHelper::horaireInstant($date_evenement, $horaire_debut);

        if ($instant === null)
        {
            return mb_substr($date_evenement, 0, 10);
        }

        return str_replace(' ', 'T', $instant);
    }

    /**
     * La liste des références web d'un événement.
     *
     * Chaque URL est rendue par WebLink : libellé raccourci sur la partie
     * lisible de l'adresse, et icône de la plateforme quand elle est reconnue.
     * Les autres gardent la puce d'origine.
     */
    public static function getRefListHtml(string $refCsv): string
    {
        ob_start();
        $tab_ref = RefList::split(strip_tags($refCsv));
        foreach ($tab_ref as $r)
        {
            $r = trim($r);
            ?>
            <li>
                <?php
                if (preg_match('#^(https?://|www\d?\.)[a-z0-9_-]+\.[a-z0-9_.-]*[a-z]{2,}#i', $r))
                {
                    echo WebLink::html($r, iconeParDefaut: 'fa-hand-o-right');
                }
                else
                {
                    echo sanitizeForHtml($r);
                }
                ?>
            </li>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * La vignette d'un événement : le flyer s'il existe, l'illustration à défaut, rien sinon.
     *
     * $smallHeight n'est utile qu'aux cadres de dimensions figées — les colonnes « Image » des
     * tableaux de gestion — où le CSS recadre ensuite l'image sur le cadre. Sans lui, la hauteur
     * reste au navigateur, comme partout ailleurs.
     *
     * $lazy laissé à null décide seul : chargement immédiat pour les EAGER_FIGURES_PER_REQUEST
     * premières vignettes de la requête, différé pour les suivantes. Le passer explicitement
     * n'a d'intérêt qu'à un appelant qui sait sa vignette hors du premier écran, ou dedans.
     */
    public static function mainFigureHtml(string $flyer, string $image, string $titre, ?int $smallWidth = null, ?int $smallHeight = null, ?bool $lazy = null): string
    {
        global $assets;

        // Au-delà de la largeur de la miniature, c'est l'image de 600 px qu'il faut servir :
        // étirée au-delà de sa taille, la miniature serait floue. Le seuil suit désormais
        // la constante d'écriture au lieu d'un 120 recopié ici.
        $useThumbnail = !empty($smallWidth) && $smallWidth <= Evenement::THUMBNAIL_MAX_WIDTH;

        if (empty($flyer) && empty($image))
        {
            return '';
        }

        // Compté ici et non à l'entrée : un événement sans flyer n'occupe pas le premier écran
        $isLazy = $lazy ?? (self::$figuresRendered >= self::EAGER_FIGURES_PER_REQUEST);
        self::$figuresRendered++;

        $imgHeight = $smallHeight ?? '';
        if (!empty($flyer))
        {
            $href = $assets->get(Evenement::getAssetPath(Evenement::getFilePath($flyer)));
            $imgSrc = $assets->get(Evenement::getAssetPath($useThumbnail ? Evenement::getThumbFilePath($flyer) : Evenement::getFilePath($flyer)));
            $imgAlt = "Flyer de ". sanitizeForHtml($titre);
        }
        elseif (!empty($image))
        {
            $href = $assets->get(Evenement::getAssetPath(Evenement::getFilePath($image)));
            $imgSrc = $assets->get(Evenement::getAssetPath($useThumbnail ? Evenement::getThumbFilePath($image) : Evenement::getFilePath($image)));
            $imgAlt = "Illustration de ". sanitizeForHtml($titre);
        }

        // ouvert après le retour anticipé : un tampon abandonné capterait la suite du gabarit
        // appelant, celui d'eventShortArticleHtml() par exemple
        ob_start();
        ?>

        <a href="<?= $href ?>" class="magnific-popup">
            <?php // chaque dimension est émise pour elle-même : la hauteur était jusqu'ici toujours
                  // vide, et height="" n'est pas une valeur valide ?>
            <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>"<?php if (!empty($smallWidth)) : ?> width="<?= $smallWidth ?>"<?php endif; ?><?php if (!empty($imgHeight)) : ?> height="<?= $imgHeight ?>"<?php endif; ?><?php if ($isLazy) : ?> loading="lazy" decoding="async"<?php endif; ?>>
        </a>

        <?php
        return ob_get_clean();
    }

    /**
     * Remet à zéro le compteur de vignettes de la requête.
     *
     * Une requête HTTP rend une page puis s'arrête : en production le compteur n'a rien à
     * remettre à zéro. Les tests, eux, rendent plusieurs pages dans le même processus.
     */
    public static function resetFiguresRendered(): void
    {
        self::$figuresRendered = 0;
    }


    /**
     * @param bool $withTimeStatus Situer l'événement par rapport à maintenant (#51). L'agenda le
     *                             passe selon EVENT_TIME_STATUS_ENABLED ; les autres appelants ne
     *                             le passent pas et rendent l'article inchangé.
     */
    public static function eventShortArticleHtml(array $tab_even, array $tab_events_today_in_region_orgas = [], bool $withTimeStatus = false): string
    {
        $even_lieu = Evenement::getLieu($tab_even);

        $time_status = $withTimeStatus ? EvenementTimeStatus::fromEvent($tab_even) : null;

        // Terminé, ou séance commencée depuis trop longtemps : deux façons d'être hors d'atteinte,
        // une seule classe, car le traitement visuel est le même — la carte pâlit.
        $is_dimmed = $time_status !== null
            && ($time_status->state === EvenementTimeStatus::PAST || $time_status->tooLate);
        $article_class = 'evenement-short' . ($is_dimmed ? ' even-time-dimmed' : '');

        ob_start();
        ?>

        <article id="event-<?= (int) $tab_even['e_idEvenement'] ?>" class="<?= $article_class ?>">

            <header class="titre">
                <h3 class="left"><a href="/event/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"><?= self::titreSelonStatutHtml($tab_even['e_titre'], $tab_even['e_statut']) ?></a></h3>
                <span class="right"><?= Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']) ?></span>
                <div class="spacer"></div>
            </header>

            <?php // flyer et description forment une rangée flex : le fond gris du flyer
                  // s'étire ainsi jusqu'en bas de la description, organisateurs compris ?>
            <div class="event-media">

            <figure class="flyer"><?= self::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre'], 100) ?></figure>

            <div class="description">
                <?php // le lien reste hors du <p> : le line-clamp du CSS le rognerait avec le texte ?>
                <p class="js-description-clamp">
                <?= Text::shortenToHtml((string) $tab_even['e_description'], self::DESCRIPTION_MAX_CHARS); ?>
                </p>
                <a class="continuer js-lire-la-suite" href="/event/evenement.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>"<?= Text::isCut((string) $tab_even['e_description'], self::DESCRIPTION_MAX_CHARS) ? '' : ' hidden' ?>>Lire la suite</a>
                <?php if (!empty($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']])): ?>
                    <?= Organisateur::getListLinkedHtml($tab_events_today_in_region_orgas[$tab_even['e_idEvenement']]) ?>
                <?php endif; ?>
            </div>

            </div> <!-- event-media -->

            <div class="spacer"></div>

            <div class="pratique">
                <span class="left"><?php
                    $adresse = sanitizeForHtml(HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse']));
                    echo $adresse;
                    // avec les repères de temporalité (#51), le prix quitte la colonne des horaires,
                    // que la barre de progression occupe désormais, pour se ranger sous l'adresse
                    if ($withTimeStatus && !empty($tab_even['e_prix']))
                    {
                        echo ($adresse !== '' ? '<br>' : '') . self::priceShortHtml((string) $tab_even['e_prix']);
                    }
                ?></span>
                <span class="right">
                    <?php
                    $horaire_complet = EvenementRenderer::schedulesToHhMm($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']);
                    if (!empty($tab_even['e_horaire_complement']))
                    {
                        $horaire_complet .= " ".$tab_even['e_horaire_complement'];
                    }

                    if ($withTimeStatus)
                    {
                        /*
                         * Avec les repères de temporalité (#51), la colonne se lit en lignes : l'horaire,
                         * puis le repère quand il en réclame une. L'horaire est enveloppé parce qu'il
                         * pâlit quand l'événement est hors d'atteinte — le repère qui l'explique reste net.
                         */
                        if (!empty($horaire_complet))
                        {
                            echo '<span class="even-time-line">' . sanitizeForHtml($horaire_complet) . '</span>';
                        }
                        echo self::timeStatusHtml($time_status);
                    }
                    else
                    {
                        echo sanitizeForHtml($horaire_complet);
                        if (!empty($horaire_complet) && !empty($tab_even['e_prix']))
                        {
                            echo ", ";
                        }
                        echo sanitizeForHtml($tab_even['e_prix']);
                    }
                    ?>
                </span>
                <div class="spacer"></div>
            </div> <!-- fin pratique -->


        <?php
        return ob_get_clean();
    }


    /**
     * Le badge qui situe un événement par rapport à maintenant (#51), à poser après ses horaires.
     *
     * Rien à afficher quand les horaires ne permettent pas de le situer : le statut est alors null.
     * L'icône est décorative — le libellé qui la suit porte l'information.
     *
     * « terminé » se lit à la suite de l'horaire, entre parenthèses, parce qu'il le qualifie ;
     * le compte à rebours et la barre prennent la ligne suivante — la barre à la suite du compte
     * à rebours tant que l'événement n'a pas commencé, seule ensuite.
     */
    public static function timeStatusHtml(?EvenementTimeStatus $status): string
    {
        if ($status === null)
        {
            return '';
        }

        if ($status->state === EvenementTimeStatus::PAST)
        {
            return ' <span class="even-time-status even-time-status-past" title="Terminé">'
                . '(<i class="fa fa-check-square" aria-hidden="true"></i>&nbsp;' . sanitizeForHtml($status->label) . ')</span>';
        }

        if ($status->state === EvenementTimeStatus::RUNNING)
        {
            return '<br>' . self::timeProgressHtml($status);
        }

        // l'espace qui sépare le compte à rebours de la barre est le seul point où la ligne peut
        // se couper : une barre longue passe alors dessous plutôt que de déborder de la colonne
        return '<br><span class="even-time-status even-time-status-coming" title="' . sanitizeForHtml('Commence ' . $status->label) . '">'
            . '<i class="fa fa-clock-o" aria-hidden="true"></i>&nbsp;' . sanitizeForHtml($status->label) . '</span> '
            . self::timeProgressHtml($status);
    }


    /**
     * La barre de progression d'un événement : sa durée par la longueur, sa part écoulée par le
     * remplissage et le pourcentage inscrit au milieu.
     *
     * <progress> porte le rôle ARIA progressbar et sa valeur ; le pourcentage inscrit par-dessus
     * et les rayures qui animent le remplissage sont des calques décoratifs, masqués aux
     * technologies d'assistance. Les rayures ne sont pas dessinées par le pseudo-élément de
     * remplissage, qui ne s'anime pas de façon fiable (Firefox l'ignore, cf. Bugzilla 812442) :
     * un calque de même largeur, lui, s'anime partout.
     *
     * Avant le début, la barre, vide, ne dit rien que le compte à rebours et l'horaire ne disent
     * déjà : elle est masquée en bloc aux technologies d'assistance.
     *
     * Sans horaire de fin, la durée est estimée (cf. EvenementTimeStatus) : le « ? » qui suit la
     * barre le signale à l'œil, l'infobulle dit à quelle heure la fin a été fixée.
     */
    private static function timeProgressHtml(EvenementTimeStatus $status): string
    {
        $isRunning = $status->state === EvenementTimeStatus::RUNNING;
        $percent = (int) $status->percent;
        $width = (int) round((int) $status->durationMinutes * self::PROGRESS_PX_PER_HOUR / 60);
        $percentHtml = $percent . '&nbsp;%';

        $estimation = $status->endEstimated
            ? 'fin inconnue, estimée à ' . mb_substr((string) $status->end, 11, 5)
            : null;

        $title = $isRunning
            ? 'En cours, ' . $status->label . ' écoulés' . ($estimation !== null ? ' — ' . $estimation : '')
            : null;

        $html = '<span class="even-time-status even-time-status-' . $status->state . '"' . ($isRunning ? '' : ' aria-hidden="true"') . '>'
            . '<span class="even-time-bar" style="width:' . $width . 'px"' . ($title !== null ? ' title="' . sanitizeForHtml($title) . '"' : '') . '>'
            . '<progress class="even-time-progress" max="100" value="' . $percent . '"' . ($title !== null ? ' aria-label="' . sanitizeForHtml($title) . '"' : '') . '>' . $percentHtml . '</progress>';

        if ($percent > 0)
        {
            $html .= '<span class="even-time-bar-stripes" style="width:' . $percent . '%" aria-hidden="true"></span>';
        }

        $html .= '<span class="even-time-bar-label" aria-hidden="true">' . $percentHtml . '</span>'
            . '</span>';

        if ($estimation !== null)
        {
            $html .= '<span class="even-time-estimated" title="' . sanitizeForHtml(ucfirst($estimation)) . '" aria-hidden="true">?</span>';
        }

        return $html . '</span>';
    }


    /**
     * Le prix d'une carte, rangé sous l'adresse avec les repères de temporalité (#51).
     *
     * Au-delà de PRICE_MAX_CHARS, il est coupé au mot près et suivi de « (...) » ; l'infobulle le
     * rend entier, et la fiche de l'événement le donne de toute façon en entier.
     */
    public static function priceShortHtml(string $prix): string
    {
        $prix = trim($prix);

        if (!Text::isCut($prix, self::PRICE_MAX_CHARS))
        {
            return '<span class="even-time-price">' . sanitizeForHtml($prix) . '</span>';
        }

        return '<span class="even-time-price" title="' . sanitizeForHtml($prix) . '">'
            . sanitizeForHtml(Text::truncateWords($prix, self::PRICE_MAX_CHARS)) . ' (...)</span>';
    }


    /**
     * @param string|null $lieuName Nom du lieu quand la page en tient déjà lieu de contexte
     *                              ($isWithLieu à false) : la colonne reste vide à l'écran mais
     *                              la propriété hCalendar location doit avoir une valeur.
     */
    public static function eventTableRowHtml(array $tab_even, Authorization $authorization, bool $isWithLieu, ?string $lieuName = null): string
    {
        // TODO: mv $icone... to... ?
        global $glo_auj_6h, $iconeCopier, $iconeEditer, $icone, $site_full_url;

        $isFutureEvent = $tab_even['e_dateEvenement'] >= $glo_auj_6h;
        $isAllowedToEdit = $authorization->isPersonneAllowedToEditEvenement($_SESSION, $tab_even);

        $dtstart_iso = self::dtstartIso($tab_even['e_dateEvenement'], $tab_even['e_horaire_debut']);

        // depending on rendering in lieu or organisateur page
        $location = sanitizeForHtml($tab_even['s_nom']);
        $location_masquee = '';
        if ($isWithLieu)
        {
            $even_lieu = Evenement::getLieu($tab_even);
            $location = Lieu::getLinkNameHtml($even_lieu['nom'], $even_lieu['idLieu'], $even_lieu['salle']);
        }
        elseif (!empty($lieuName))
        {
            // sur une fiche lieu la colonne resterait vide : hCalendar n'a alors aucune valeur
            // pour location (1760 erreurs « Champ location manquant » dans Search Console)
            $location_masquee = $lieuName;
        }

        ob_start();
        ?>

        <tr class="<?php if ($glo_auj_6h == $tab_even['e_dateEvenement']) { echo "ici"; } ?> vevent evenement">

            <?php
            // hCalendar : la date lisible ne peut pas porter dtstart elle-même, d'où le abbr.
            // Le motif <span class="value-title" title="..."> utilisé auparavant n'est pas compris
            // par le parseur de Google, qui lit le texte de l'élément et signalait alors 1961 erreurs
            // « dtstart non conforme à la norme ISO 8601 » dans Search Console.
            ?>
            <td class="date">
                <a href="/index.php?courant=<?= sanitizeForHtml($tab_even['e_dateEvenement']) ?>"><abbr class="dtstart" title="<?= sanitizeForHtml($dtstart_iso) ?>"><?= DateHelper::isoToDayName($tab_even['e_dateEvenement']); ?>&nbsp;<?= (new \DateTime($tab_even['e_dateEvenement']))->format('j') ?></abbr></a><br>
                <span class="pratique"><?= self::schedulesToHhMm($tab_even['e_horaire_debut'], $tab_even['e_horaire_fin'], $tab_even['e_dateEvenement']) ?></span>
            </td>
            <td class="flyer photo">
                <?= self::mainFigureHtml($tab_even['e_flyer'], $tab_even['e_image'], $tab_even['e_titre'], 60) ?>
            </td>
            <td>
                <a class="url" href="/event/evenement.php?idE=<?= (int)$tab_even['e_idEvenement']?>">
                    <strong class="summary"><?= self::titreSelonStatutHtml($tab_even['e_titre'], $tab_even['e_statut']) ?></strong>
                </a><br>
                <span class="category"><?= Evenement::categoryLabel($tab_even['e_genre']); ?></span>
            </td>
            <td class="location">
                <?= $location ?>
                <?php if ($location_masquee !== '') : ?><span class="visually-hidden"><?= sanitizeForHtml($location_masquee) ?></span><?php endif; ?>
            </td>
            <?php if ($isFutureEvent || $isAllowedToEdit) : ?>
            <td class="lieu_actions_evenement">
                <ul>
                    <?php if ($isFutureEvent) : ?>
                        <?= EvenementCalendarRenderer::renderMenuHtml($tab_even, $site_full_url, compact: true) ?>
                    <?php endif; ?>
                    <?php if ($isAllowedToEdit) : ?>
                        <li><a href="/event/copy.php?idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Copier cet événement"><?= $iconeCopier ?></a></li>
                        <?php if ($authorization->isPersonneAllowedToEditEvenementNow($_SESSION, $tab_even)) : ?>
                        <li><a href="/evenement-edit.php?action=editer&amp;idE=<?= (int) $tab_even['e_idEvenement'] ?>" title="Modifier cet événement"><?= $iconeEditer ?></a></li>
                        <?php endif; ?>
                        <li class=""><?= self::unpublishLinkHtml((int) $tab_even['e_idEvenement'], $icone['depublier']) ?></li>
                    <?php endif; ?>
                </ul>

            </td>
            <?php endif; ?>
        </tr>

        <?php
        return ob_get_clean();
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
     * @param string $site_full_url Domaine complet du site (constante globale)
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
            'LOCATION' => self::escapeAndFoldString($even_lieu['nom'] . " - " . HtmlShrink::adresseCompacteSelonContexte($even_lieu['region'], $even_lieu['localite'], $even_lieu['quartier'], $even_lieu['adresse'])),
            'SUMMARY' => self::escapeAndFoldString($event['e_titre']),
            'DESCRIPTION' => self::escapeAndFoldString($event['e_description']),
        ];
    }

    /** Escape and fold an iCalendar property value per RFC 5545 §3.1 and §3.3.11. */
    private static function escapeAndFoldString(string $string): string
    {
        $escaped = strtr($string, [
            '\\' => '\\\\',
            "\r" => '',
            "\n" => '\\n',
            ','  => '\,',
            ';'  => '\;',
        ]);

        $lines = [];
        $line  = '';
        $bytes = 0;

        foreach (mb_str_split($escaped) as $char) {
            $charBytes = strlen($char);

            if ($bytes + $charBytes > 75) {
                $lines[] = $line;
                $line    = ' ' . $char;
                $bytes   = 1 + $charBytes;
            } else {
                $line  .= $char;
                $bytes += $charBytes;
            }
        }

        $lines[] = $line;

        return implode("\r\n", $lines);
    }
}
