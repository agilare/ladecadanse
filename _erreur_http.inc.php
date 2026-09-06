<?php
/**
 * Refus applicatif rendu dans la page du site : le statut HTTP qui va avec, puis le
 * message dans le gabarit habituel.
 *
 * Un message HTML nu au-dessus d'une page vide n'offrait ni retour à l'accueil, ni au
 * client le moyen de distinguer un refus d'une réponse normale. Les deux formulaires de
 * fiche en portaient chacun leur copie ; les prochains appelants n'auront qu'à inclure
 * ce fichier.
 *
 * À ne pas confondre avec misc/error.php, qui est l'`ErrorDocument` d'Apache : celui-là
 * lit `$_SERVER['REDIRECT_STATUS']` et répond aux erreurs du serveur, pas à un refus que
 * l'application vient de décider.
 *
 * L'appelant sort lui-même après l'inclusion — un `exit` caché dans un gabarit se voit
 * mal depuis la page qui l'inclut.
 *
 * @var array{0: int, 1: string, 2: string} $http_error code, raison HTTP, message affiché
 */

use Ladecadanse\HtmlShrink;

[$status_code, $status_reason, $error_message] = $http_error;

header($_SERVER["SERVER_PROTOCOL"] . " $status_code $status_reason");

$page_titre = "erreur $status_code";
include(__DIR__ . "/_header.inc.php");
HtmlShrink::msgErreur($error_message);
include(__DIR__ . "/_footer.inc.php");
