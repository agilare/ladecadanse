<?php
// make a copy of this file with name 'env.php'

define("ENV", "dev"); // or "prod"
define("MODE_DEBUG", true); // display errors

// URL canonique du site, sans slash final. Décommenter en développement pour que les balises
// d'autodiscovery RSS et les liens vers les flux pointent sur cet environnement plutôt que sur
// la production. Laissée commentée, app/config.php retient https://www.ladecadanse.ch
//define("SITE_CANONICAL_URL", 'http://localhost:7777');

// database connection
define("DB_HOST", '');
define("DB_NAME", '');
define("DB_USERNAME", '');
define("DB_PASSWORD", '');

// SMTP config and credentials to send emails to site admin and users
define("EMAIL_AUTH_HOST", '');
define("EMAIL_SMTPAUTH", true);
define("EMAIL_AUTH_USERNAME", '');
define("EMAIL_AUTH_PASSWORD", '');
define("EMAIL_AUTH_SMTPSECURE", 'TLS');
define("EMAIL_AUTH_PORT", '587');
define("EMAIL_AUTH_SMTPDEBUG", '0'); // https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting#enabling-debug-output


// mail accounts
define("EMAIL_SITE", ''); // sender of automatic site emails (could be a noreply ?)
define("EMAIL_SITE_NAME", 'La décadanse');
define("EMAIL_ADMIN", ''); // recipient of site activity to watch, users requests (contact form, new event prop...) to process
define("EMAIL_ADMIN_NAME", 'La décadanse');

// envoie à EMAIL_ADMIN une copie de chaque mail adressé à un utilisateur, avec un sujet préfixé "[COPY]"
// (destiné à de courtes périodes de monitoring : suivre la circulation des messages et leur rendu)
define("EMAIL_COPY_TO_ADMIN", false);

// external services
define("TINYMCE_API_KEY", ''); // rich text editor for presentations of lieux and organisateurs

define("MATOMO_ENABLED", false); // statistics tool (enabled only in prod)
define("MATOMO_URL", '');
define("MATOMO_SITE_ID", '');

// front-end errors logger
//
// SUSPENDU le 2026-09-29 dans le cadre de la conformité nLPD/RGPD (issue #93). À
// réactiver ponctuellement, le temps d'un diagnostic, plutôt qu'en permanence.
//
// Avant de le rallumer : basculer le DSN sur l'instance européenne (voir ci-dessous).
// Ajuster aussi les domaines de la CSP dans app/bootstrap.php (script-src, connect-src).
//
// Ce que l'activation déclenche, pour TOUS les visiteurs : _footer.inc.php charge le
// bundle Sentry depuis browser.sentry-cdn.com, et chaque erreur JavaScript part chez le
// tiers avec l'URL de la page, la pile d'appels et le User-Agent. L'adresse IP est vue
// par le serveur qui reçoit l'envoi. Vérifier que l'URL transmise ne peut pas porter de
// jeton (user/reset2.php) avant de laisser tourner sur l'ensemble du site.
define("GLITCHTIP_ENABLED", false);

// Le service propose deux hébergements : app.glitchtip.com (DigitalOcean New York) et
// eu.glitchtip.com (Francfort). C'est le premier qui était en place jusqu'au 2026-09-29 ;
// prendre le second, qui évite un transfert hors d'Europe. Il faut y recréer le projet,
// le DSN étant propre à l'instance — la clé de l'ancien n'y vaut rien.
//
//   define("GLITCHTIP_DSN", "https://<cle-publique>@eu.glitchtip.com/<id-projet>");
//
// La clé publique fait 32 caractères hexadécimaux, l'identifiant de projet est un nombre ;
// GlitchTip donne le DSN complet à la création du projet.
define("GLITCHTIP_DSN", "");

// service to monitor and moderate bots (AI, crawlers, etc.)
//
// SUSPENDU le 2026-09-20 dans le cadre de la conformité nLPD/RGPD (issue #93), au profit
// du suivi interne ci-dessous (BOT_MONITORING_ENABLED). À ne réactiver qu'à titre
// exceptionnel, et pas avant d'avoir lu ce qui suit.
//
// Le service s'appelle désormais Known Agents (darkvisitors.com redirige vers
// knownagents.com) ; les constantes ont gardé l'ancien nom. La CSP de app/bootstrap.php
// autorise déjà knownagents.com.
//
// Ce que l'activation déclenche, pour TOUS les visiteurs, personnes connectées comprises :
//   - app/bootstrap.php envoie au tiers, à chaque page vue, l'URI demandée et les en-têtes
//     de la requête. Cookie, Authorization et Proxy-Authorization en sont retirés (ne pas
//     défaire ce filtre), mais il reste à faire avant de réactiver :
//       · n'envoyer que le chemin : REQUEST_URI part avec sa query string, donc avec le
//         jeton de user/reset2.php?token=… ; l'en-tête Referer, transmis lui aussi, porte
//         l'URL complète de la page précédente et demande le même traitement
//       · exclure les sessions connectées, comme le fait le suivi interne
//         (empty($_SESSION['logged']))
//   - cet envoi se fait au shutdown, timeout de 200 ms : le visiteur n'attend pas, mais
//     chaque page vue retient son processus PHP le temps de l'appel, ce qui aggrave une
//     surcharge au lieu d'aider à la diagnostiquer
//   - _header.inc.php charge knownagents.com/tracker.js sur toutes les pages : le tiers
//     reçoit l'adresse IP et le User-Agent de chaque visiteur. Le script (relevé le
//     2026-09-20, il peut changer sans préavis) poste en plus le chemin AVEC sa query
//     string, le referrer et une empreinte du navigateur (écran, navigator.*) pour les
//     navigateurs d'allure automatisée et pour les personnes arrivant d'un chat IA
//     (chatgpt.com, claude.ai, perplexity.ai…). C'est un script tiers exécuté jusque sur
//     les pages de connexion et de réinitialisation : ne pas l'y charger
//
// Côté conformité : c'est une transmission de données personnelles à un tiers, dont le
// service tournait le 2026-09-20 sur Google Cloud Run us-central1 (États-Unis). L'inscrire
// à l'inventaire de l'issue #93 et à la politique de confidentialité.
//
// Ce qu'il apporte réellement : un annuaire tenu à jour d'agents DÉCLARÉS (reconnus par
// leur User-Agent), des séries temporelles, la détection d'un faux Googlebot. Il ne voit
// pas mieux que le suivi interne les bots à faux User-Agent ni les sondeurs de failles :
// pour ceux-là, l'access log de l'hébergeur reste l'outil.
define("DARKVISITORS_ENABLED", false);
define("DARKVISITORS_PROJECT_KEY", '');
define("DARKVISITORS_ACCESS_TOKEN", '');

// suivi interne des bots et IP suspectes (table bot_monitor + admin/bots.php)
// la table est créée et complétée par `composer db:migrate` (migrations Version20260716000000
// et Version20261003000000), à passer avant d'activer
define("BOT_MONITORING_ENABLED", false);
define("BOT_MONITORING_SUSPECT_THRESHOLD", 150); // seuil de hits pour "humains suspects" dans le dashboard

// avertissement puis anonymisation des comptes sans connexion depuis trois ans
// (librairies/InactiveAccountRetention.php + admin/inactive-accounts.php)
//
// La colonne personne.inactivity_notified_at est posée par `composer db:migrate`
// (migration Version20261001000000), à passer avant d'activer.
//
// Ce drapeau sert à la mise en route, et non à laisser le traitement à l'arrêt : l'arriéré
// des comptes déjà au-dessus du seuil s'avertit à la main par admin/mailing.php, le canal
// automatique ne prenant la suite qu'ensuite. Au 01.10.2026 il représentait 5 464 comptes,
// soit 55 jours d'envois au rythme d'un avertissement par passage, aux dépens de la
// réputation d'expéditeur du domaine. Marche à suivre dans UPGRADE.md.
//
// Une durée de conservation ne se désactive pas durablement (LPD art. 6 al. 4) : à false,
// l'écran d'administration affiche l'arrêt plutôt que de le taire.
define("ACCOUNT_RETENTION_ENABLED", false);

// accepter les PDF dans les champs flyer et image d'un événement, dont seule la
// 1re page est gardée, convertie en WebP (evenement-edit.php, admin/events.php)
//
// Deux voies, dont une seule demande quelque chose au serveur :
//   - champ fichier : le navigateur convertit (web/js/pdf-to-image.js), rien à
//     installer, mais ~3,4 Mo de pdf.js à télécharger au premier PDF déposé
//   - « ou coller une URL » : le serveur convertit (Utils\PdfToImage), ce qui
//     demande l'extension imagick ET Ghostscript. Sans eux cette voie refuse les
//     PDF et renvoie vers le champ fichier, le reste continuant de fonctionner.
//     Voir « Convertir les PDF collés en URL » dans le README.
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       les champs n'annoncent pas le PDF, ne l'acceptent pas, et
//               pdf.js n'est jamais chargé
//   'preview'   réservé aux administrateurs, pour éprouver la fonctionnalité en
//               ligne sans l'ouvrir au public ; le texte d'aide le signale, une
//               préversion qui ne se voit pas se croit livrée
//   true        ouvert à tous
//
// La chaîne littérale, et non FeatureFlag::PREVIEW : ce fichier est chargé par
// app/bootstrap.php avant l'autoloader, aucune classe n'y est encore connue.
define("PDF_CONVERSION_ENABLED", false);

// afficher le calendrier du champ date d'un événement (evenement-edit.php) déployé
// en permanence sous le champ, au lieu du calendrier surgissant au clic
// (Zebra_DatePicker, mode always_visible).
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       le champ date se comporte comme partout ailleurs sur le site :
//               le calendrier ne s'ouvre qu'au clic
//   'preview'   réservé aux administrateurs, le temps d'éprouver la mise en page
//               que le calendrier impose au reste du formulaire ; une mention
//               sous le calendrier rappelle qu'il n'est pas public
//   true        ouvert à tous
define("DATEPICKER_ALWAYS_VISIBLE_ENABLED", false);

// replier le bloc de saisie manuelle du lieu (evenement-edit.php, « Si et seulement si
// vous n'avez pas trouvé le lieu dans la liste ») dans un <details> fermé par défaut.
// Ce bloc occupe la moitié du fieldset Lieu alors que la plupart des personnes n'y
// saisissent jamais rien ; celles qui s'en servent le retrouvent ouvert grâce au cookie
// event_form_lieu_manual_open.
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       le bloc reste déplié en permanence, comme avant
//   'preview'   réservé aux administrateurs, le temps d'éprouver le repli ; une mention
//               sous le résumé rappelle qu'il n'est pas public
//   true        ouvert à tous
define("LIEU_MANUAL_COLLAPSIBLE_ENABLED", false);

// situer chaque événement de l'agenda (index.php) par rapport à l'heure de chargement
// de la page : à venir, en cours, terminé. La journée du jour seulement, seule où la
// comparaison apprend quelque chose.
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       l'agenda est celui d'avant : aucun repère de temporalité
//   'preview'   réservé aux administrateurs, le temps d'éprouver ce que ces repères
//               apportent une fois posés sur de vrais horaires ; une mention en tête
//               de liste rappelle qu'ils ne sont pas publics
//   true        ouvert à tous
define("EVENT_TIME_STATUS_ENABLED", false);

// proposer les catégories d'événement encore en préversion — aujourd'hui la seule
// « cours/ateliers/stages » — à la saisie, au filtrage de l'agenda et à l'affichage. La
// liste est Ladecadanse\EventCategory::PREVIEW_FALLBACKS ; « concerts », passée par là,
// est ouverte à tous et ne dépend plus de ce drapeau.
//
// Trois états, comme tout drapeau passant par Ladecadanse\FeatureFlag :
//   false       un événement déjà classé en « cours » s'affiche et se range en « divers »
//   'preview'   réservé aux administrateurs, le temps d'éprouver le classement sur de
//               vrais événements ; pour tous les autres le repli s'applique, et une
//               mention au-dessus de l'agenda comme sous le champ Catégorie le rappelle
//   true        ouvert à tous
//
// Le repli n'efface rien : evenement.genre reste un varchar(20), et rétrograder le
// drapeau ne perd aucune donnée — les événements reclassés retrouvent leur catégorie
// dès qu'il remonte.
define("EVENT_NEW_CATEGORIES_ENABLED", false);

define("PAYPAL_HOSTED_BUTTON_ID", "");

// to allow access to events API (api.php)
define("LADECADANSE_API_ENABLED", false);
define("LADECADANSE_API_USER", '');
define("LADECADANSE_API_KEY", '');

// small modules

// in homepage, closable announcements by site admin; [] = none
// 'date' identifies the announcement: changing it shows the banner again to those who closed it
// 'type': info | warn | danger ; 'audience': tous | contributeurs (ACTOR level and above)
// 'titre' and 'contenu' are output as HTML
define("HOME_BANNERS", [
    // [
    //     'date' => '2026-09-27 14:00',
    //     'type' => 'warn',
    //     'audience' => 'tous',
    //     'titre' => "Title of my announcement",
    //     'contenu' => "My announcement...",
    // ],
]);

// after crash of 22.10.2024 and then existence of 2 database versions, allow restart of application with limited edition on current db version to avoid conflict qui backed up db
// removed its usage in code the 15.02.2025
define("PARTIAL_EDIT_MODE", false);
define("PARTIAL_EDIT_FROM_DATETIME", "2024-10-22 23:21:00");
define("PARTIAL_EDIT_MODE_MSG", "Le site est actuellement partiellement fonctionel, ainsi certains éléments comme celui-ci ne peuvent être modifiés");
