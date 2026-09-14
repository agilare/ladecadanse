<?php

use Tests\Support\SiteTester;

use Codeception\Util\HttpCode;

/**
 * Flux RSS (event/rss.php).
 *
 * Lecture seule : uniquement des GET, aucune écriture.
 *
 * L'enjeu de ces tests est le contrat de statut du point d'entrée. Il porte la moitié du trafic
 * du script et sert de cible à un fuzzing constant du paramètre `type` : un relâchement de la
 * whitelist ou une régression sur le 410 ne se verrait autrement que dans les logs Apache.
 */
class RssCest
{
    private const TYPES_VALIDES_SANS_ID = ['evenements_auj', 'evenements_ajoutes'];

    /**
     * Un flux retiré répond 410 et non 404 : le 410 amène les lecteurs à marquer l'abonnement en
     * erreur et les crawlers à retirer l'URL de leur index. `evenement_commentaires` a été
     * supprimé avec la fonction commentaires il y a environ trois ans et representait encore
     * 30,5% des requêtes des flux.
     */
    public function retiredFeedIsGone(SiteTester $I)
    {
        $I->amOnPage('/event/rss.php?type=evenement_commentaires');
        $I->seeResponseCodeIs(HttpCode::GONE);
    }

    /**
     * Les réponses d'erreur sont émises avant le chargement de l'application.
     *
     * L'absence de cookie de session en est la preuve observable : bootstrap.php appelle
     * session_start(), qui pose un Set-Cookie. Sans cette assertion, déplacer la validation
     * après le require passerait inaperçu — le code de statut, lui, resterait le bon, le switch
     * servant de filet. C'est pourtant tout l'intérêt du changement : ces requêtes représentent
     * la moitié du trafic du script et payaient deux connexions à la base chacune.
     */
    public function rejectedRequestsDoNotBootTheApplication(SiteTester $I)
    {
        foreach (['evenement_commentaires', 'inconnu'] as $type)
        {
            $I->amOnPage('/event/rss.php?type=' . $type);
            $I->assertSame(
                '',
                $I->grabResponseHeader('Set-Cookie'),
                "La réponse pour « $type » ne doit pas démarrer de session"
            );
        }
    }

    /**
     * La réponse d'un flux retiré ne dépend d'aucun autre paramètre.
     */
    public function retiredFeedIgnoresOtherParameters(SiteTester $I)
    {
        $I->amOnPage('/event/rss.php?type=evenement_commentaires&id=42&page=3');
        $I->seeResponseCodeIs(HttpCode::GONE);
    }

    /**
     * Un type absent ou hors whitelist est refusé avant toute autre opération.
     */
    public function unknownTypeIsRejected(SiteTester $I)
    {
        $I->amOnPage('/event/rss.php');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->amOnPage('/event/rss.php?type=inconnu');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    /**
     * La valeur reçue n'est jamais réaffichée : un endpoint qui renvoie l'écho des sondes
     * continue d'attirer les scanners, et c'est la seule voie qui menait vers un en-tête HTTP.
     *
     * La charge évite `<script>` et `<svg>` : le pare-feu 8G du .htaccess refuse ces motifs dans
     * la query string et répond 403 avant même d'atteindre PHP. Le test ne mesurerait alors plus
     * le contrat du script mais celui d'Apache, et échouerait partout où le site est servi
     * derrière lui. Une évasion d'attribut suivie d'une balise non filtrée passe, elle, jusqu'au
     * script — c'est là que se joue l'assertion.
     */
    public function rejectedTypeIsNeverReflected(SiteTester $I)
    {
        $charge = 'evenements_auj"><img src=x onerror=alert(1)>';

        $I->amOnPage('/event/rss.php?type=' . urlencode($charge));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        // sur la source, pas via dontSee() : ce dernier compare le texte rendu et laisserait
        // passer une réflexion enfouie dans un attribut ou un commentaire
        $source = $I->grabPageSource();
        $I->assertStringNotContainsString('onerror', $source);
        $I->assertStringNotContainsString('evenements_auj', $source);
    }

    /**
     * Les flux portant sur une entité exigent un identifiant entier strictement positif.
     */
    public function parameterizedFeedRequiresAValidId(SiteTester $I)
    {
        foreach (['lieu_evenements', 'organisateur_evenements'] as $type)
        {
            foreach (['', '&id=', '&id=abc', '&id=0', '&id=-1', '&id=1.9'] as $suffixe)
            {
                $I->amOnPage('/event/rss.php?type=' . $type . $suffixe);
                $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            }
        }
    }

    /**
     * Les flux légitimes répondent un XML valide, avec les métadonnées obligatoires du channel.
     */
    public function validFeedsServeWellFormedXml(SiteTester $I)
    {
        foreach (self::TYPES_VALIDES_SANS_ID as $type)
        {
            $I->amOnPage('/event/rss.php?type=' . $type);
            $I->seeResponseCodeIs(HttpCode::OK);

            $xml = $I->grabPageSource();

            $document = new DOMDocument();
            $I->assertTrue($document->loadXML($xml), "Le flux $type doit être un XML bien formé");

            $canal = $document->getElementsByTagName('channel')->item(0);
            $I->assertNotNull($canal, "Le flux $type doit exposer un channel");

            foreach (['title', 'description', 'pubDate', 'lastBuildDate'] as $balise)
            {
                $valeur = trim($canal->getElementsByTagName($balise)->item(0)?->textContent ?? '');
                $I->assertNotSame('', $valeur, "Le channel de $type doit renseigner <$balise>");
            }

            // pubDate figeait la date de l'ajout le plus ancien : sur « événements du jour »,
            // le flux annonçait une date vieille de cinq mois
            $pubDate = $canal->getElementsByTagName('pubDate')->item(0)->textContent;
            $I->assertNotFalse(strtotime($pubDate), "Le pubDate de $type doit être en RFC 822");
        }
    }

    /**
     * Le rel="self" est reconstruit à partir des paramètres validés : il ne doit pas refléter
     * les paramètres parasites de l'URL appelée, sous peine d'un self-link par variante.
     */
    public function selfLinkIsCanonical(SiteTester $I)
    {
        $I->amOnPage('/event/rss.php?type=evenements_auj&utm_source=parasite');

        $document = new DOMDocument();
        $document->loadXML($I->grabPageSource());

        $self = '';
        foreach ($document->getElementsByTagName('link') as $lien)
        {
            if ($lien->getAttribute('rel') === 'self')
            {
                $self = $lien->getAttribute('href');
            }
        }

        // absolu : un rel="self" relatif se résout chez le lecteur, pas contre le site. C'est
        // aussi ce qui distingue une URL reconstruite d'un simple écho de REQUEST_URI
        $I->assertStringStartsWith('http', $self, 'Le rel="self" doit être une URL absolue');
        $I->assertStringNotContainsString('utm_source', $self);
        $I->assertStringEndsWith('/event/rss.php?type=evenements_auj', $self);
    }

    /**
     * Le bloc <style> embarqué dans chaque description est du poids mort chez les lecteurs qui
     * assainissent le HTML, et déborde sur les autres flux chez ceux qui ne le font pas.
     */
    public function itemsCarryNoStyleBlock(SiteTester $I)
    {
        $I->amOnPage('/event/rss.php?type=evenements_ajoutes');

        // sur la source, pas via dontSee() : ce dernier compare le texte rendu, où une balise
        // enfouie dans une section CDATA n'apparaît pas — l'assertion serait toujours vraie
        $I->assertStringNotContainsString('<style', $I->grabPageSource());
    }

    /**
     * Conditional GET : un lecteur qui repasse avec l'ETag reçu obtient un 304 sans corps.
     * C'est tout l'objet du cache, la fréquence d'interrogation des lecteurs restant inchangée.
     */
    public function knownEtagYieldsNotModified(SiteTester $I)
    {
        $I->amOnPage('/event/rss.php?type=evenements_auj');
        $I->seeResponseCodeIs(HttpCode::OK);

        $etag = $I->grabResponseHeader('ETag');
        $I->assertNotEmpty($etag, 'Le flux doit émettre un ETag');

        $I->haveHttpHeader('If-None-Match', $etag);
        $I->amOnPage('/event/rss.php?type=evenements_auj');
        $I->seeResponseCodeIs(HttpCode::NOT_MODIFIED);
        $I->assertSame('', trim($I->grabPageSource()), 'Un 304 ne doit pas avoir de corps');

        $I->deleteHeader('If-None-Match');
    }
}
