<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\WebLink;

/**
 * Couvre `WebLink`, qui transforme une URL brute saisie par un contributeur en
 * libellé affichable.
 *
 * Deux exigences s'y croisent : ne jamais mentir sur la destination (le href
 * reste l'URL saisie, à la ponctuation de phrase près) et en dire assez pour
 * qu'on sache où l'on va (le domaine seul ne distingue pas huit liens
 * SoundCloud d'affilée).
 */
final class WebLinkTest extends Unit
{
    // --- la partie lisible de l'adresse -------------------------------------

    public function testUnDomaineSeulResteTelQuel(): void
    {
        $this->assertSame('villabernasconi.ch', WebLink::from('https://www.villabernasconi.ch')->label);
    }

    public function testLeSegmentQuiNommeLaPageEstConserve(): void
    {
        $this->assertSame(
            'theatreduloup.ch/…/inconditionnelles',
            WebLink::from('https://theatreduloup.ch/spectacle/inconditionnelles/')->label
        );
    }

    public function testLesCodesDeLangueEtLesNumerosDOrdreSontEcartes(): void
    {
        $this->assertSame(
            'cinemas-du-grutli.ch/…/les-annees-40-et-50',
            WebLink::from('https://www.cinemas-du-grutli.ch/agenda/51452-les-annees-40-et-50')->label
        );
    }

    /**
     * Le cas qui motive tout : l'identifiant opaque en fin d'URL n'apprend rien,
     * c'est le segment précédent qui nomme le spectacle.
     */
    public function testUnIdentifiantOpaqueNEstPasPrisPourUnLibelle(): void
    {
        $this->assertSame(
            'Billetterie · open-mic-by-jist',
            WebLink::from('https://infomaniak.events/fr-ch/culture-et-spectacles/open-mic-by-jist/7Zoti61usM69SeplfaQpw91xk595')->label
        );
    }

    public function testUneUrlSansAucunSegmentParlantRetombeSurLaMarque(): void
    {
        $this->assertSame(
            'Billetterie Infomaniak',
            WebLink::from('https://infomaniak.events/shop/zDlY0F7MjI/events/1218048/')->label
        );
    }

    public function testUnLibelleTropLongEstCoupeEntreDeuxMots(): void
    {
        $label = WebLink::from('https://www.centrephotogeneve.ch/expo/exposition-massao-mascaro-du-27-mars-au-15-juin')->label;

        $this->assertSame('centrephotogeneve.ch/…/exposition-massao…', $label);
        $this->assertLessThanOrEqual(48, mb_strlen($label));
    }

    // --- les plateformes fréquentes ----------------------------------------

    public function testUnProfilInstagramDevientSonPseudo(): void
    {
        $lien = WebLink::from('https://www.instagram.com/c.sugvr');

        $this->assertSame('@c.sugvr', $lien->label);
        $this->assertSame('fa-instagram', $lien->icon);
    }

    public function testUnePublicationInstagramNAPasDePseudoALAfficher(): void
    {
        $this->assertSame('Instagram · publication', WebLink::from('https://www.instagram.com/p/DcjKmaTDLBq')->label);
    }

    public function testDeuxLiensSoundcloudDeLaMemeSoireeSeDistinguent(): void
    {
        $this->assertSame('flowerzinyourlife', WebLink::from('https://soundcloud.com/flowerzinyourlife')->label);
        $this->assertSame('bony-fly', WebLink::from('https://soundcloud.com/bony-fly')->label);
    }

    public function testUneVideoYoutubeNAnnoncePasSonTitreQuOnIgnore(): void
    {
        $lien = WebLink::from('https://youtu.be/ZoALkjrff2Q?si=x2c-0Ep5HaMVg1fB');

        $this->assertSame('YouTube · vidéo', $lien->label);
        $this->assertSame('fa-youtube-play', $lien->icon);
    }

    public function testUneChaineYoutubeGardeSonArobase(): void
    {
        $this->assertSame('@Tonada', WebLink::from('https://www.youtube.com/@Tonada')->label);
    }

    public function testUnEvenementFacebookEstAnnoncePourCeQuIlEst(): void
    {
        $lien = WebLink::from('https://www.facebook.com/events/1258580272614506');

        $this->assertSame('Facebook · événement', $lien->label);
        $this->assertSame('fa-facebook-official', $lien->icon);
    }

    public function testUnAlbumBandcampNommeLArtisteEtLAlbum(): void
    {
        $this->assertSame('districtfive · glut', WebLink::from('https://districtfive.bandcamp.com/album/glut')->label);
    }

    public function testUnPdfEstSignalePourCeQuIlEst(): void
    {
        $lien = WebLink::from('https://www.ssi-suisse.org/sites/default/files/inline-files/attef-cafe-expo.pdf');

        $this->assertSame('ssi-suisse.org/…/attef-cafe-expo.pdf', $lien->label);
        $this->assertSame('fa-file-pdf-o', $lien->icon);
    }

    public function testUnSiteInconnuNaPasDIcone(): void
    {
        $this->assertNull(WebLink::from('https://lagraviere.ch/evenement/oh-my-god-flowerz/')->icon);
    }

    // --- l'adresse elle-même ------------------------------------------------

    public function testUneUrlSansSchemaEnRecoitUn(): void
    {
        $this->assertSame('https://www.motelcampo.ch', WebLink::from('www.motelcampo.ch')->href);
    }

    /**
     * Le bug visible sur les fiches : « voir www.exemple.ch. » emportait le
     * point final dans le href, et le lien tombait sur un domaine inexistant.
     */
    public function testLePointFinalDeLaPhraseNAppartientPasAuLien(): void
    {
        $this->assertSame('https://www.labelsuisse.ch', WebLink::from('www.labelsuisse.ch.')->href);
    }

    public function testUneParentheseFermanteNonOuverteEstRendueAuTexte(): void
    {
        $this->assertSame(
            'https://www.petzi.ch/fr/events/57328-undertown-no-drama/',
            WebLink::from('https://www.petzi.ch/fr/events/57328-undertown-no-drama/)')->href
        );
    }

    public function testUneParentheseQuiAppartientALAdresseEstGardee(): void
    {
        $this->assertSame(
            'https://fr.wikipedia.org/wiki/Chose_(homonymie)',
            WebLink::from('https://fr.wikipedia.org/wiki/Chose_(homonymie)')->href
        );
    }

    public function testLeLienProduitEstEchappeEtPorteSonUrlComplete(): void
    {
        $html = WebLink::html('https://www.youtube.com/watch?v=ff11ul-xHyU&t=182s');

        $this->assertStringContainsString('href="https://www.youtube.com/watch?v=ff11ul-xHyU&amp;t=182s"', $html);
        $this->assertStringContainsString('title="https://www.youtube.com/watch?v=ff11ul-xHyU&amp;t=182s"', $html);
        $this->assertStringContainsString('>YouTube · vidéo</a>', $html);
    }

    public function testUneIconeParDefautHabilleLesSitesNonReconnus(): void
    {
        $html = WebLink::html('https://lagraviere.ch/evenement/oh-my-god-flowerz/', iconeParDefaut: 'fa-hand-o-right');

        $this->assertStringContainsString('fa-hand-o-right', $html);
    }
}
