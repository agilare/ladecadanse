<?php

require_once("../app/bootstrap.php");

// « Protection des données » plutôt que « Confidentialité » : c'est le terme du droit
// suisse — la LPD est la loi sur la protection des données, et l'autorité de recours que
// la page cite est le Préposé fédéral à la protection des données. Le nom du fichier ne
// suit pas : l'URL n'apprend rien au lecteur, et la changer imposerait une redirection.
$page_titre = "Protection des données";
$page_description = "Quelles données personnelles La décadanse collecte, pourquoi, combien de temps, et comment exercer vos droits";
include("../_header.inc.php");
?>

<main id="contenu" class="colonne">

    <header id="entete_contenu">
        <h1>Protection des données</h1>
        <div class="spacer"></div>
    </header>

    <article class="rubrique">

        <p>La&nbsp;décadanse est un agenda culturel indépendant, tenu par une personne, sans but commercial. Aucune donnée n’y est vendue, louée, échangée ni utilisée à des fins publicitaires. Cette page décrit ce qui est collecté malgré tout, pourquoi, et ce que vous pouvez demander.</p>

        <p>Elle est écrite au regard de la loi fédérale sur la protection des données (LPD) et du règlement européen (RGPD), ce dernier parce que le site publie aussi des événements en France voisine.</p>

        <h2>Qui est responsable</h2>

        <p>Michel&nbsp;Gaudry, Genève, Suisse.<br />
        Pour toute question ou demande&nbsp;: <a href="/misc/contacteznous.php">la page de contact</a>, qui donne aussi l’adresse électronique.</p>

        <h2>Si vous consultez simplement l’agenda</h2>

        <p>Vous n’avez rien à fournir&nbsp;: la consultation ne demande ni compte, ni inscription, ni acceptation de quoi que ce soit. Quelques traces subsistent néanmoins.</p>

        <h3>La mesure d’audience</h3>

        <p>Pour savoir quelles rubriques servent, le site compte ses visites avec <a href="https://matomo.org" rel="external" target="_blank">Matomo</a>, un logiciel de statistiques de fréquentation installé sur nos propres serveurs en Suisse. Rien ne part chez un tiers.</p>

        <p>Deux cookies (<code>_pk_id</code> et <code>_pk_ses</code>) distinguent les visites, et sont retenus les pages vues, la page d’où vous venez, votre navigateur et la taille de votre écran. Aucun profil individuel n’est constitué, aucune donnée n’est recoupée avec autre chose. Votre adresse&nbsp;IP est amputée de ses deux derniers nombres avant d’être enregistrée, y compris avant d’en déduire votre pays&nbsp;: nous savons d’où vous venez à l’échelle du pays, jamais de la ville.</p>

        <?php /* Le div est hors de tout <p> : un bloc dans un paragraphe en ferme la balise
                 d'office, ce qui laissait deux paragraphes vides dans l'article. Matomo remplit
                 ce div, le script lui passant son id. En HTTP il affiche en rouge qu'il ne
                 répond de rien, ce qui ne concerne que le poste de développement.

                 Le texte qu'il contient est un repli : le script l'écrase par `innerHTML`, donc
                 il ne se voit que s'il n'a pas tourné. `tools.ladecadanse.ch` est un domaine
                 distinct de `www.ladecadanse.ch`, donc un tiers pour le navigateur, et un
                 fichier nommé `matomo.js` figure dans les listes de blocage courantes — la
                 protection renforcée de Firefox suffit à couper les deux scripts. Sans ce
                 repli, ces personnes lisaient « la case ci-dessus » devant un trou. */ ?>
        <div id="matomo-opt-out">
            <p>La case de refus ne s’est pas affichée&nbsp;: votre navigateur bloque les scripts de mesure d’audience, dont le nôtre. Vos visites ne sont donc déjà pas comptées.</p>
        </div>
        <script src="https://tools.ladecadanse.ch/matomo/index.php?module=CoreAdminHome&amp;action=optOutJS&amp;divId=matomo-opt-out&amp;language=auto&amp;backgroundColor=fdfdfd&amp;fontColor=5e5e5f&amp;fontSize=13px&amp;fontFamily=Verdana&amp;showIntro=1"></script>
        <p>Cocher cette case dépose un marqueur qui exclut vos visites. Il vit dans votre navigateur, et disparaît donc si vous effacez vos cookies ou changez d’appareil. Le signal <em>Do Not Track</em>, lorsqu’un navigateur l’envoie encore, est respecté lui aussi.</p>
        <h3>Le suivi des robots</h3>

        <p>Des robots d’indexation et d’aspiration visitent le site souvent, et peuvent peser lourd sur un hébergement modeste. Pour les gérer, chaque visite non identifiée est enregistrée avec son adresse&nbsp;IP, le nom du navigateur et le nombre de pages vues&nbsp;; les visiteurs humains sont compris, les deux ne se distinguant pas d’avance. Ces lignes s’effacent après 90&nbsp;jours, un an pour les adresses qui ignorent délibérément nos règles d’accès, et ne sont communiquées à personne.</p>

        <h3>Ce que voient des serveurs extérieurs</h3>

        <p>Tout serveur sollicité voit l’adresse&nbsp;IP qui le sollicite, et le site n’en sollicite qu’un&nbsp;: sur les fiches de lieu qui ont des coordonnées, la carte est faite d’images demandées à <a href="https://www.openstreetmap.org" rel="external" target="_blank">OpenStreetMap</a>, fondation britannique. Les fiches sans coordonnées n’appellent aucune carte, et tout le reste est servi depuis nos propres machines.</p>

        <h3>Les journaux de l’hébergeur</h3>

        <p>Le site est hébergé par <a href="https://www.infomaniak.com" rel="external" target="_blank">Infomaniak</a>, en Suisse. Comme tout serveur web, le sien conserve un journal des requêtes avec l’adresse&nbsp;IP et la page demandée, pour des raisons techniques et de sécurité. Ces journaux ne vivent que quelques jours, et nous n’en gardons pas de copie.</p>

        <h2>Si vous nous écrivez</h2>

        <p>Le <a href="/misc/contacteznous.php">formulaire de contact</a> transmet votre adresse, votre sujet et votre message par courriel, sans rien enregistrer dans le site&nbsp;; le message vit dans une boîte mail, supprimé au plus tard deux ans après le dernier échange. La fonction «&nbsp;envoyer à quelqu’un&nbsp;» d’un événement, elle, expédie un courriel une fois à l’adresse que vous indiquez, sans la conserver ni l’inscrire sur aucune liste.</p>

        <h2>Si vous annoncez un événement sans compte</h2>

        <p>Le formulaire demande votre adresse électronique, nécessaire pour vérifier l’annonce et vous répondre. Ni elle ni la remarque adressée à l’administrateur ne sont publiées, et toutes deux s’effacent deux ans après la date de l’événement&nbsp;: plus rien ne relie alors l’annonce à vous. L’annonce, elle, reste dans l’agenda, qui fait archive.</p>

        <h2>Si vous avez un compte</h2>

        <p>Un compte retient votre adresse électronique, votre mot de passe sous forme chiffrée, un nom d’utilisateur si vous en choisissez un, votre région, votre affiliation éventuelle à un lieu ou à un organisateur, ainsi que les dates de création, de modification et de dernière connexion. Ces informations servent à rattacher vos annonces à votre compte et à vous joindre à leur sujet.</p>

        <p>Le champ «&nbsp;adresse électronique&nbsp;» n’est jamais publié&nbsp;: seuls les administrateurs du site le voient. Votre nom d’utilisateur, en revanche, signe par défaut les événements que vous annoncez — le «&nbsp;Ajouté par…&nbsp;» au bas de la fiche —, et il est donc public&nbsp;: <strong>n’y mettez pas votre adresse électronique</strong>. Votre profil permet de ne plus signer du tout&nbsp;; pour changer le nom lui-même, écrivez-nous.</p>

        <p>Les cookies déposés pour un compte sont ceux de la session, celui de l’option «&nbsp;rester connecté-e&nbsp;» (trente jours, si vous la cochez) et celui qui retient la région choisie. Tous sont techniques.</p>

        <p>Un message peut vous être adressé lorsqu’une évolution du site vous concerne. Ce ne sera jamais de la publicité, ni pour nous ni pour un tiers, et chaque message rappelle comment demander à ne plus en recevoir.</p>

        <p>La durée de conservation d’un compte est de trois ans sans connexion. Au terme de ce délai, un avertissement est envoyé, puis le compte anonymisé un mois plus tard&nbsp;: adresse et identifiant effacés, sans retour possible. Ce nettoyage est en cours de mise en route. Une seule connexion suffit à repartir pour trois ans, et les événements annoncés restent dans l’agenda, détachés du compte.</p>

        <h2>Combien de temps</h2>

        <dl>
            <dt>Compte</dt>
            <dd>trois ans après votre dernière connexion&nbsp;; effacé plus tôt sur demande</dd>
            <dt>Événements publiés</dt>
            <dd>conservés, l’agenda faisant archive&nbsp;; les coordonnées d’une annonce faite sans compte sont effacées deux ans après la date de l’événement, et celles d’un compte le sont sur demande</dd>
            <dt>Messages reçus</dt>
            <dd>deux ans après le dernier échange</dd>
            <dt>Suivi des robots</dt>
            <dd>90 jours, ou un an après un accès abusif</dd>
            <dt>Mesure d’audience</dt>
            <dd>cookie et données détaillées de visite&nbsp;: treize mois&nbsp;; au-delà, seules les statistiques agrégées subsistent</dd>
            <dt>Journaux techniques</dt>
            <dd>quelques jours chez l’hébergeur&nbsp;; quatorze mois pour ceux du site, qui ne retiennent pas d’adresse&nbsp;IP</dd>
        </dl>

        <h2>Vos droits</h2>

        <p>Vous pouvez demander&nbsp;:</p>

        <ul>
            <li>à savoir quelles données vous concernant sont détenues, et en obtenir une copie&nbsp;;</li>
            <li>la correction de ce qui est inexact&nbsp;;</li>
            <li>la suppression de votre compte et des données qui s’y rattachent&nbsp;;</li>
            <li>de vous opposer à la mesure d’audience ou au suivi décrits plus haut — pour la mesure d’audience, le lien donné plus haut suffit, sans rien demander à personne.</li>
        </ul>

        <p>Passez par <a href="/misc/contacteznous.php">la page de contact</a>. La réponse vous parviendra dans les trente jours, gratuitement.</p>

        <p>Les événements déjà publiés ne sont pas effacés lorsqu’un compte l’est&nbsp;: ce sont des annonces publiques que l’agenda conserve comme archive. Le lien entre l’événement et vous, lui, est supprimé.</p>

        <p>Si une réponse ne vous satisfait pas, vous pouvez saisir le <a href="https://www.edoeb.admin.ch" rel="external" target="_blank">Préposé fédéral à la protection des données et à la transparence</a> en Suisse, ou l’autorité de protection des données de votre pays si vous résidez dans l’Union européenne.</p>

        <h2>Ce que le site ne fait pas</h2>

        <ul>
            <li>aucune revente, location ou cession de données&nbsp;;</li>
            <li>aucune publicité, aucun traceur publicitaire, aucun bouton de réseau social&nbsp;;</li>
            <li>aucune décision automatisée vous concernant&nbsp;;</li>
            <li>aucune collecte de données sensibles (santé, opinions, convictions, orientation)&nbsp;;</li>
            <li>aucun compte ni formulaire destiné aux enfants — l’agenda annonce des événements pour le jeune public, mais ce sont des adultes qui les y inscrivent.</li>
        </ul>

        <h2>Sécurité</h2>

        <p>Le site est servi exclusivement en HTTPS. Les mots de passe sont stockés hachés, jamais en clair. L’accès à l’administration est restreint et journalisé, et l’hébergeur assure les sauvegardes. En cas de fuite de données présentant un risque élevé, les personnes concernées et l’autorité compétente seront prévenues.</p>

        <h2>Modifications</h2>

        <p>Cette page peut évoluer avec le site. Sa dernière mise à jour date du <time datetime="2026-10-04">4 octobre 2026</time>.</p>

    </article>

</main>

<?php /* Sans le calendrier des autres pages d'articles : une page qu'on lit d'un bout à
         l'autre n'appelle pas à sauter à une date. La colonne reste, vide, pour que le
         texte garde sa place et sa largeur. */ ?>
<div id="colonne_gauche" class="colonne">
</div>

<div id="colonne_droite" class="colonne">
</div>

<?php
include("../_footer.inc.php");
?>
