<?php

require_once("../app/bootstrap.php");

$page_titre = "Confidentialité";
$page_description = "Quelles données personnelles La décadanse collecte, pourquoi, combien de temps, et comment exercer vos droits";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Confidentialité</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique">

        <p>La&nbsp;décadanse est un agenda culturel indépendant, tenu par une personne, sans but commercial. Aucune donnée n’y est vendue, louée, échangée ni utilisée à des fins publicitaires. Cette page décrit ce qui est collecté malgré tout, pourquoi, et ce que vous pouvez demander.</p>

        <p>Elle est écrite au regard de la loi fédérale sur la protection des données (LPD) et du règlement européen (RGPD), ce dernier parce que le site publie aussi des événements en France voisine.</p>

        <h2>Qui est responsable</h2>

        <p>Michel&nbsp;Gaudry, Genève, Suisse.<br />
        Pour toute question ou demande&nbsp;: <a href="/misc/contacteznous.php">le formulaire de contact</a> ou <a href="mailto:info@ladecadanse.ch">info@ladecadanse.ch</a>.</p>

        <h2>Si vous consultez simplement l’agenda</h2>

        <p>Vous n’avez rien à fournir&nbsp;: la consultation ne demande ni compte, ni inscription, ni acceptation de quoi que ce soit. Quelques traces subsistent néanmoins.</p>

        <h3>La mesure d’audience</h3>

        <p>Le site utilise <a href="https://matomo.org" rel="external" target="_blank">Matomo</a>, installé sur nos propres serveurs en Suisse. Aucune donnée de fréquentation ne part chez un tiers, et il n’y a ni Google Analytics, ni bouton de réseau social, ni régie publicitaire.</p>

        <p>Matomo dépose deux cookies (<code>_pk_id</code> et <code>_pk_ses</code>) pour distinguer les visites, et retient les pages vues, la page d’où vous venez, votre navigateur et la taille de votre écran. Le but est de savoir quelles rubriques servent, rien de plus&nbsp;: aucun profil individuel n’est constitué, aucune donnée n’est recoupée avec autre chose. Si votre navigateur envoie le signal <em>Do Not Track</em>, il est respecté.</p>

        <h3>Le suivi des robots</h3>

        <p>Le site est très fréquenté par des robots d’indexation et d’aspiration, qui pèsent lourd sur un hébergement modeste. Pour les mesurer et les freiner, chaque visite non identifiée est enregistrée dans une table qui retient l’adresse&nbsp;IP, le nom du navigateur (ou du robot) et le nombre de pages vues. Cela concerne aussi les visiteurs humains&nbsp;: on ne peut pas distinguer les deux avant d’avoir regardé.</p>

        <p>Ces lignes sont effacées automatiquement après 90&nbsp;jours, ou après une année pour les adresses surprises à ignorer délibérément nos règles d’accès. Elles ne servent qu’à cela et ne sont communiquées à personne.</p>

        <h3>Ce que voient des serveurs extérieurs</h3>

        <p>Afficher une page suppose d’aller chercher quelques fichiers ailleurs que chez nous, et tout serveur sollicité voit l’adresse&nbsp;IP qui le sollicite. Sur La&nbsp;décadanse, cela concerne&nbsp;:</p>

        <ul>
            <li><b>GlitchTip</b>, qui nous signale les erreurs d’affichage pour qu’elles soient corrigées&nbsp;; il reçoit le message d’erreur, l’adresse de la page et le nom de votre navigateur&nbsp;;</li>
            <li>les <b>bibliothèques jQuery et Leaflet</b>, servies par des répertoires publics de code (jquery.com, unpkg.com), que nous prévoyons d’héberger nous-mêmes.</li>
        </ul>

        <p>Un outil de détection des robots d’intelligence artificielle, <b>Known Agents</b>, a été en service jusqu’en septembre 2026. Il transmettait davantage&nbsp;: en plus de l’adresse&nbsp;IP, quelques caractéristiques du navigateur. Il est désactivé, et le suivi décrit plus haut, tenu sur nos propres serveurs, le remplace.</p>

        <p>Sur les fiches de lieu, les cartes proviennent d’<a href="https://www.openstreetmap.org" rel="external" target="_blank">OpenStreetMap</a> (fondation britannique)&nbsp;: afficher une carte suppose de demander ses images, qui voit alors votre adresse&nbsp;IP. Les fiches sans coordonnées n’appellent aucune carte.</p>

        <h3>Les journaux de l’hébergeur</h3>

        <p>Le site est hébergé par <a href="https://www.infomaniak.com" rel="external" target="_blank">Infomaniak</a>, en Suisse. Comme tout serveur web, le sien conserve un journal des requêtes avec l’adresse&nbsp;IP et la page demandée, pour des raisons techniques et de sécurité.</p>

        <h2>Si vous nous écrivez</h2>

        <p>Le <a href="/misc/contacteznous.php">formulaire de contact</a> transmet votre adresse, votre sujet et votre message directement par courriel. Rien n’est enregistré dans le site&nbsp;; le message vit dans une boîte mail, et y est supprimé au plus tard deux ans après le dernier échange.</p>

        <p>La fonction «&nbsp;envoyer à quelqu’un&nbsp;» d’un événement expédie un courriel à l’adresse que vous indiquez, une fois. Cette adresse n’est pas conservée, ne rejoint aucune liste et ne recevra aucun autre envoi de notre part.</p>

        <h2>Si vous annoncez un événement sans compte</h2>

        <p>Le formulaire demande votre adresse électronique&nbsp;: elle est nécessaire pour vérifier l’annonce et vous répondre. Elle est enregistrée avec l’événement et n’est jamais publiée. La remarque libre adressée à l’administrateur ne l’est pas davantage.</p>

        <h2>Si vous avez un compte</h2>

        <p>Un compte retient votre identifiant, votre adresse électronique, votre mot de passe sous forme chiffrée, votre région, votre affiliation éventuelle à un lieu ou à un organisateur, ainsi que les dates de création, de modification et de dernière connexion. Ces informations servent à publier vos annonces sous votre nom et à vous joindre à leur sujet.</p>

        <p>Votre adresse électronique n’est pas publique. Elle est visible des administrateurs du site, et des autres personnes rattachées au même organisateur ou au même lieu que vous&nbsp;: les membres d’une même structure ont besoin de pouvoir se joindre.</p>

        <p>Les cookies déposés pour un compte sont ceux de la session, celui de l’option «&nbsp;rester connecté-e&nbsp;» (quinze jours, si vous la cochez) et celui qui retient la région choisie. Tous sont techniques.</p>

        <p>Un message peut vous être adressé lorsqu’une évolution du site vous concerne. Ce ne sera jamais de la publicité, ni pour nous ni pour un tiers, et chaque message rappelle comment demander à ne plus en recevoir.</p>

        <h2>Combien de temps</h2>

        <dl>
            <dt>Compte</dt>
            <dd>tant qu’il existe&nbsp;; supprimé sur demande</dd>
            <dt>Événements publiés</dt>
            <dd>conservés, l’agenda faisant archive&nbsp;; l’adresse de l’auteur en est détachée sur demande</dd>
            <dt>Messages reçus</dt>
            <dd>deux ans après le dernier échange</dd>
            <dt>Suivi des robots</dt>
            <dd>90 jours, ou un an après un accès abusif</dd>
            <dt>Mesure d’audience</dt>
            <dd>cookie et données détaillées de visite&nbsp;: treize mois&nbsp;; au-delà, seules les statistiques agrégées subsistent</dd>
            <dt>Journaux techniques</dt>
            <dd>selon les réglages du serveur et de l’application</dd>
        </dl>

        <h2>Vos droits</h2>

        <p>Vous pouvez demander&nbsp;:</p>

        <ul>
            <li>à savoir quelles données vous concernant sont détenues, et en obtenir une copie&nbsp;;</li>
            <li>la correction de ce qui est inexact&nbsp;;</li>
            <li>la suppression de votre compte et des données qui s’y rattachent&nbsp;;</li>
            <li>de vous opposer à la mesure d’audience ou au suivi décrits plus haut.</li>
        </ul>

        <p>Écrivez à <a href="mailto:info@ladecadanse.ch">info@ladecadanse.ch</a> ou passez par <a href="/misc/contacteznous.php">le formulaire</a>. La réponse vous parviendra dans les trente jours, gratuitement.</p>

        <p>Les événements déjà publiés ne sont pas effacés lorsqu’un compte l’est&nbsp;: ce sont des annonces publiques que l’agenda conserve comme archive. Le lien entre l’événement et vous, lui, est supprimé.</p>

        <p>Si une réponse ne vous satisfait pas, vous pouvez saisir le <a href="https://www.edoeb.admin.ch" rel="external" target="_blank">Préposé fédéral à la protection des données et à la transparence</a> en Suisse, ou l’autorité de protection des données de votre pays si vous résidez dans l’Union européenne.</p>

        <h2>Ce que le site ne fait pas</h2>

        <ul>
            <li>aucune revente, location ou cession de données&nbsp;;</li>
            <li>aucune publicité, aucun traceur publicitaire, aucun bouton de réseau social&nbsp;;</li>
            <li>aucune décision automatisée vous concernant&nbsp;;</li>
            <li>aucune collecte de données sensibles (santé, opinions, convictions, orientation)&nbsp;;</li>
            <li>aucun service destiné aux enfants.</li>
        </ul>

        <h2>Sécurité</h2>

        <p>Le site est servi exclusivement en HTTPS. Les mots de passe sont stockés hachés, jamais en clair. L’accès à l’administration est restreint et journalisé, et l’hébergeur assure les sauvegardes. En cas de fuite de données présentant un risque élevé, les personnes concernées et l’autorité compétente seront prévenues.</p>

        <h2>Modifications</h2>

        <p>Cette page peut évoluer avec le site. Sa dernière mise à jour date du <time datetime="2026-09-20">20 septembre 2026</time>.</p>

    </article>

</main>

<div id="colonne_gauche" class="colonne">
    <?php include("../event/_navigation_calendrier.inc.php"); ?>
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
?>
