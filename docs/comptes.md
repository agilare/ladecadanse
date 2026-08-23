# Comptes

Les quatre pages de compte — connexion, inscription, réinitialisation du mot de passe, déconnexion — vivent sous `user/`, avec le profil (`user/dashboard.php`). Elles partagent la même mécanique : traitement complet avant la première ligne de HTML, jeton de formulaire comparé par `hash_equals()`, pot de miel, erreurs rassemblées dans un récapitulatif en haut de page et champs fautifs marqués `champ_errone` / `aria-invalid`. La feuille `web/css/compte.css` leur donne largeur, centrage et grille libellé/champ communes.

| Page | Fichier |
| --- | --- |
| Connexion | `user/login.php` |
| Inscription | `user/register.php` |
| Mot de passe oublié (demande) | `user/reset.php` |
| Nouveau mot de passe (lien reçu) | `user/reset2.php` |
| Déconnexion | `user/logout.php` |
| Profil | `user/dashboard.php` |

## Mot de passe

Les règles sont portées par [`Ladecadanse\Utils\PasswordPolicy`](../librairies/Utils/PasswordPolicy.php) :

- de **10 à 100 caractères** ;
- **au moins un chiffre** ;
- les deux saisies identiques ;
- absent de la liste des mots de passe refusés.

La liste est `resources/bad_p.txt` : 19 999 entrées venant de [tarraschk/richelieu](https://github.com/tarraschk/richelieu) (mots de passe français les plus courants, issus de fuites publiques, CC BY 4.0), plus les quelques entrées propres au site qui n'y figuraient pas — dont les « qwertz » du clavier suisse. Elle n'est chargée qu'une fois par requête, et seulement si les règles précédentes sont satisfaites : elle ne dirait rien de plus sur un mot de passe déjà refusé. Si le fichier est illisible, les autres règles s'appliquent quand même et l'incident part dans le log — une inscription ne doit pas échouer là-dessus.

**Le profil fait exception** : `user-edit.php` ne reprend que la consultation de la liste. Ses bornes (8/30) et ses clés d'erreur diffèrent de celles de l'inscription et de la réinitialisation ; les aligner serait un changement de règle, à traiter à part.

Rien n'est vérifié rétroactivement : un mot de passe existant qui ne satisferait plus les règles reste valable jusqu'à son prochain changement.

## Réinitialisation

Une demande (`user/reset.php`) n'aboutit que pour un compte **actif**, c'est-à-dire `statut = 'actif'`. C'est le filtre que Sentry applique à la connexion : réinitialiser un autre compte laisserait la personne bloquée à l'écran suivant. Un compte désactivé ou encore en attente ne reçoit donc pas de lien, et aucune demande n'est enregistrée pour lui.

Le message affiché est le même dans tous les cas — compte trouvé ou non, actif ou non. La page ne dit jamais si un compte existe.

Le lien reçu vaut **24 h**. Sur `user/reset2.php` :

- la liste des comptes réinitialisables est établie une fois, avant le traitement, pour les deux formes de demande (par identifiant ou par email) ; affichage et contrôle du POST lisent la même liste et ne peuvent pas diverger ;
- un candidat unique s'impose de lui-même, sans champ caché à falsifier. Le choix n'est demandé — donc l'erreur possible — que si plusieurs comptes actifs partagent l'adresse ;
- liste vide (compte désactivé entre la demande et le clic) : message explicite, pas de formulaire ;
- jeton absent, mal formé (lien coupé par un client de messagerie) ou expiré : « demande invalide ». Ces trois cas se valent, et aucun ne lève d'erreur.

## Déconnexion

`user/logout.php` n'accepte que **POST**, avec un jeton. Toute autre requête repart en `303` vers l'accueil sans rien détruire, et sans dire si une session était ouverte.

Le point d'entrée reste nécessaire — la session est côté serveur, le cookie est `HttpOnly` — mais une déconnexion en GET partait toute seule au moindre préchargement de lien (navigateur, antivirus, scanner d'e-mail) et s'imposait depuis n'importe quel site tiers.

Le jeton `form_token_user_logout` est déposé en session par `_header.inc.php` et vaut **pour toute la session**, et non par affichage comme sur `user/login.php` : le bouton « Sortir » est rendu sur toutes les pages, donc dans tous les onglets ouverts, et un jeton à usage unique bloquerait la déconnexion depuis un onglet resté en arrière-plan.

`Sentry::logout()` efface aussi le cookie « Rester connecté-e ». Son `setcookie()` doit porter le `path` explicitement : sans lui, PHP retombe sur le répertoire du script appelant, donc `/user`, et le cookie posé sur `/` survit — Sentry rouvre alors une session à la requête suivante. Trois cas sont verrouillés dans `tests/Site/UserLogoutCest.php`, dont celui-là.
