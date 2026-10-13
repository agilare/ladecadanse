<?php

require_once("../../app/bootstrap.php");

use Ladecadanse\ContributorEdition;
use Ladecadanse\EventCategory;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Localite;
use Ladecadanse\Organisateur;
use Ladecadanse\Personne;
use Ladecadanse\Security\SecurityToken;
use Ladecadanse\UserLevel;
use Ladecadanse\Utils\Mailing;

/*
 * Profil d'organisateur d'événements d'un compte (#288) : rattachements, signature et valeurs
 * par défaut du formulaire d'ajout. Le compte lui-même (adresse, nom, groupe, statut) se
 * modifie dans user/edit.php, le mot de passe dans user/pwd-edit.php.
 */

// =============================================================================
// Accès
// =============================================================================

if (!$authorization->checkGroup(UserLevel::MEMBER))
{
    header("Location: /user/login.php");
    die();
}

$idP = (int) ($_GET['idP'] ?? 0);
$is_own_account = (int) $_SESSION['SidPersonne'] === $idP;

$contributor_form = new ContributorEdition();

/*
 * Rattachements à un lieu ou à un organisateur : ils donnent le droit de modifier ses fiches et
 * ses événements. Un ACTOR les voit mais ne les choisit pas — sans quoi il se rattacherait à
 * n'importe quelle fiche en postant « affiliations[]=orga:123 ».
 */
$contributor_form->setCanEditAffiliations((int) $_SESSION['Sgroupe'] <= UserLevel::AUTHOR);

// Chaque refus est rendu dans la page du site par _erreur_http.inc.php
$http_error = null;

if ($idP <= 0)
{
    $http_error = [400, 'Bad Request', "Aucun compte n'est désigné"];
}
// le compte d'un autre : le superadmin seul, comme pour user/edit.php
elseif (!$is_own_account && (int) $_SESSION['Sgroupe'] !== UserLevel::SUPERADMIN)
{
    $http_error = [403, 'Forbidden', "Vous n'avez pas les droit pour effectuer cette action"];
}
elseif (!$contributor_form->loadValues($idP))
{
    $http_error = [404, 'Not Found', "Cet utilisateur n'existe pas ou plus"];
}
// Le niveau lu est celui du compte, pas du visiteur : un MEMBER n'ajoute pas d'événements
elseif ((int) $contributor_form->getStored()['groupe'] > UserLevel::ACTOR)
{
    $http_error = [404, 'Not Found', "Ce compte n'ajoute pas d'événements"];
}

if ($http_error !== null)
{
    include("../_erreur_http.inc.php");
    exit;
}

// =============================================================================
// Traitement
// =============================================================================

if (isset($_POST['formulaire']) && $_POST['formulaire'] === 'ok')
{
    if (!SecurityToken::check($_POST['token'] ?? '', $_SESSION['token'] ?? ''))
    {
        $contributor_form->setErreur("global", "Le système de sécurité du site n'a pu authentifier votre action. Veuillez réafficher ce formulaire et réessayer");
    }
    elseif ($contributor_form->processSubmission($_POST, $_FILES))
    {
        $message = (string) $contributor_form->getResultMessage();

        // 0 comme Sentry quand le compte n'a pas de lieu : un lieu retiré cesse aussitôt de donner
        // ses droits, au lieu de les garder jusqu'à la prochaine connexion
        if ($is_own_account && $contributor_form->canEditAffiliations())
        {
            $_SESSION["Saffiliation_lieu"] = (int) $contributor_form->getValeur('lieu');
        }

        $logger->info('[user-contributor-edit] contributor profile updated', [
            'idP' => $idP,
            'by' => (int) $_SESSION['SidPersonne'],
            'idLieu' => (int) $contributor_form->getValeur('lieu'),
            'idOrganisateurs' => $contributor_form->getValeur('organisateurs'),
        ]);

        /*
         * Information du rattachement. L'inscription enregistre une déclaration, que l'équipe
         * vérifie avant de lier le compte ici : la personne ne l'apprendrait pas autrement. La
         * case dit l'intention, la comparaison décide : rien ne part si les rattachements n'ont
         * pas changé.
         */
        if (!$is_own_account && !empty($_POST['notify_affiliation']) && $contributor_form->affiliationsChanged())
        {
            $noms = $contributor_form->getAffiliationNames();
            $envoye = (new Mailing())->toUser(
                (string) $contributor_form->getStored()['email'],
                "Votre compte sur La décadanse",
                $tplEngine->render('user-affiliation-mail-body', [
                    'affiliations' => implode(', ', $noms),
                    'dashboard_url' => $site_full_url . "user/dashboard.php?idP=" . $idP,
                ])
            );

            $message .= $envoye
                ? " La personne en a été informée par e-mail."
                : " L'e-mail d'information n'a pas pu être envoyé.";

            $logger->info('[user-contributor-edit] affiliation notified', ['idP' => $idP, 'envoye' => $envoye]);
        }

        $_SESSION['user_flash_msg'] = $message;
        header("Location: /user/dashboard.php?idP=" . $idP);
        die();
    }
}

// =============================================================================
// Préparation du rendu : toutes les lectures en base se font ici, avant l'en-tête
// =============================================================================

$can_edit_affiliations = $contributor_form->canEditAffiliations();
$stored = $contributor_form->getStored();
$ev_defaults = $contributor_form->getEventDefaults();

/*
 * Rattachements sélectionnés, sous la forme que poste la liste : « lieu:42 », « orga:17 ». Au
 * ré-affichage après une erreur, la sélection postée, et elle seule — y ajouter ce que porte la
 * base ferait revenir ce que l'utilisateur vient de retirer.
 */
$affiliations_selected = [];

if ((int) $contributor_form->getValeur('lieu') > 0)
{
    $affiliations_selected[] = 'lieu:' . (int) $contributor_form->getValeur('lieu');
}

foreach ($contributor_form->getValeur('organisateurs') as $idOrg)
{
    $affiliations_selected[] = 'orga:' . (int) $idOrg;
}

$stmt = $connectorPdo->prepare("SELECT idLieu, nom FROM lieu WHERE statut = 'actif'
    ORDER BY TRIM(LEADING 'L\'' FROM (TRIM(LEADING 'Les ' FROM (TRIM(LEADING 'La ' FROM (TRIM(LEADING 'Le ' FROM nom))))))) COLLATE utf8mb4_unicode_ci");
$stmt->execute();
$lieux_affiliables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mêmes organisateurs et même tri pour la liste des rattachements et pour les valeurs par défaut
$organisateurs_actifs = Organisateur::getActifsPourSelect();

// Mêmes lieux et même tri que le formulaire d'ajout d'événement, sans les salles (le réglage se
// fait au niveau du lieu)
$lieux_defaut = Lieu::getActifsPourSelect();

// Le lieu rattaché, lu même inactif : lien sous la liste désactivée, et nom de la signature
$lieu_rattache = null;

if ((int) $contributor_form->getValeur('lieu') > 0)
{
    $stmt = $connectorPdo->prepare("SELECT idLieu, nom FROM lieu WHERE idLieu = ?");
    $stmt->execute([(int) $contributor_form->getValeur('lieu')]);
    $lieu_rattache = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$organisateurs_rattaches = $can_edit_affiliations ? [] : Personne::getOrganisateurs($idP);

/*
 * Aperçu de la signature, selon les règles de Personne::getSignatureHtml() : un nom
 * d'utilisateur en forme d'adresse ne signe pas, et le nom du lieu l'emporte sur le texte
 * libre. Rendu ici pour le premier affichage ; web/js/forms.js le tient à jour pendant la saisie.
 */
$signature_pseudo = trim((string) $stored['pseudo']);
$signature_signable_pseudo = Personne::looksLikeEmail($signature_pseudo) ? '' : $signature_pseudo;
$signature_affiliation = $lieu_rattache['nom'] ?? trim((string) $contributor_form->getValeur('affiliation'));

$signature_preview_html = $contributor_form->getValeur('signature') === 'pseudo' && $signature_signable_pseudo !== ''
    ? '<strong>' . sanitizeForHtml($signature_signable_pseudo) . '</strong>'
    : '';

if ($contributor_form->getValeur('avec_affiliation') === 'oui' && $signature_affiliation !== '')
{
    $signature_preview_html .= ($signature_preview_html === '' ? '' : ' ') . '(' . sanitizeForHtml($signature_affiliation) . ')';
}

// =============================================================================
// Rendu
// =============================================================================

$page_titre = "profil d'organisateur d'événements";
// la mise en page des formulaires de compte, commune avec user/edit.php
$extra_css = ["formulaires", "user/edit"];
include("../_header.inc.php");
?>

<main id="contenu" class="colonne user-edit">

    <header id="entete_contenu">
        <h1>Organisateur d'événements</h1>
        <div class="spacer"></div>
    </header>

    <?php if (!$is_own_account) : ?>
    <p>Compte de <a href="/user/dashboard.php?idP=<?= $idP ?>"><?= sanitizeForHtml(Personne::displayName($stored['pseudo'], $stored['email'])) ?></a></p>
    <?php endif; ?>

    <?php
    if ($contributor_form->hasErrors())
    {
        HtmlShrink::msgErreur("Il y a " . $contributor_form->getErrorCount() . " erreur(s)");
        // les erreurs qu'aucun champ ne porte, comme le refus du jeton CSRF
        echo $contributor_form->getHtmlErreur("global");
    }
    ?>

    <form method="post" id="ajouter_editer" class="js-submit-freeze-wait" action="/user/contributor-edit.php?idP=<?= $idP ?>">

        <fieldset id="references">

            <legend>Affiliation(s)</legend>

            <?php /* Un seul affichage pour tous : désactivés pour qui ne peut pas les changer, ces
                     champs ne sont pas postés, et ContributorEdition garde ce que dit la base. */ ?>
            <p>
                <label for="affiliations">Lieu et organisateurs</label>
                <?php /* Un seul lieu peut être retenu, autant d'organisateurs qu'on veut ; vérifié au traitement */ ?>
                <select name="affiliations[]" id="affiliations" multiple class="js-select2-affiliations"
                        data-placeholder="Tapez un nom de lieu ou d'organisateur..."<?= $can_edit_affiliations ? '' : ' disabled' ?>>
                    <optgroup label="Lieux">
                    <?php foreach ($lieux_affiliables as $lieu_affiliable) : ?>
                        <?php $valeur = 'lieu:' . (int) $lieu_affiliable['idLieu']; ?>
                        <option value="<?= $valeur ?>"<?= in_array($valeur, $affiliations_selected, true) ? ' selected="selected"' : '' ?>><?= sanitizeForHtml($lieu_affiliable['nom']) ?></option>
                    <?php endforeach ?>
                    </optgroup>
                    <optgroup label="Organisateurs">
                    <?php foreach ($organisateurs_actifs as $organisateur_actif) : ?>
                        <?php $valeur = 'orga:' . (int) $organisateur_actif['idOrganisateur']; ?>
                        <option value="<?= $valeur ?>"<?= in_array($valeur, $affiliations_selected, true) ? ' selected="selected"' : '' ?>><?= sanitizeForHtml($organisateur_actif['nom']) ?></option>
                    <?php endforeach ?>
                    </optgroup>
                </select>
                <?= $contributor_form->getHtmlErreur("affiliations"); ?>
            </p>

            <?php if ($can_edit_affiliations) : ?>
            <div class="guideChamp">
                Un seul lieu, autant d'organisateurs que nécessaire. Le compte pourra modifier leurs fiches et les événements qui s'y rattachent
            </div>
            <?php else : ?>
            <?php /* Les fiches restent atteignables : la liste désactivée ne mène nulle part */ ?>
            <?php if ($lieu_rattache !== null || $organisateurs_rattaches !== []) : ?>
            <div class="guideChamp rattachements-liens">
                Voir :
                <?php $liens = []; ?>
                <?php if ($lieu_rattache !== null) { $liens[] = '<a href="/lieu/lieu.php?idL=' . (int) $lieu_rattache['idLieu'] . '">' . sanitizeForHtml($lieu_rattache['nom']) . '</a>'; } ?>
                <?php foreach ($organisateurs_rattaches as $organisateur_rattache) { $liens[] = '<a href="/organisateur/organisateur.php?idO=' . (int) $organisateur_rattache['idOrganisateur'] . '">' . sanitizeForHtml($organisateur_rattache['nom']) . '</a>'; } ?>
                <?= implode(', ', $liens) ?>.
                Vous pouvez modifier ces fiches et les événements qui s'y rattachent.
            </div>
            <?php endif; ?>
            <div class="guideChamp">Si vous souhaitez modifier ces rattachements, merci de nous <a href="/misc/contacteznous.php">contacter</a></div>
            <?php endif; ?>

            <p>
                <label for="affiliation">Si pas dans la liste</label>
                <input type="text" name="affiliation" id="affiliation" size="30" maxlength="<?= Personne::FIELDS['affiliation']['max'] ?>" value="<?= sanitizeForHtml((string) $contributor_form->getValeur('affiliation')) ?>"<?= $can_edit_affiliations ? '' : ' disabled' ?> />
                <?= $contributor_form->getHtmlErreur("affiliation"); ?>
            </p>

            <div class="guideChamp">Nom du lieu ou de l'organisateur représenté, en texte libre ; repris par la signature si aucun lieu n'est choisi</div>

            <?php
            /*
             * L'inscription de l'utilisateur enregistre une déclaration, puis l'équipe la vérifie
             * et peut ici lier le compte et enfin notifier l'utilisateur. Case dans la colonne des
             * champs, libellé après elle : le texte est trop long pour la colonne des libellés.
             */
            if ($can_edit_affiliations && !$is_own_account) :
            ?>
            <p class="case-seule">
                <input type="checkbox" name="notify_affiliation" id="notify_affiliation" value="1" class="checkbox" />
                <label for="notify_affiliation" class="continu">Prévenir par e-mail du rattachement</label>
            </p>
            <?php endif; ?>

        </fieldset>

        <fieldset id="vos-evenements">

        <legend>Vos événements</legend>

        <fieldset id="signature">

            <legend>Signature en bas de la page d'un événement</legend>

            <fieldset class="groupe-radios">
                <legend>Afficher</legend>
                <ul class="radio mobile-vertical">
                    <?php
                    /* Un compte sans nom d'utilisateur n'a rien à signer : pas de case, et sa
                       signature en base reste telle quelle. Le témoin dit au traitement que la
                       case a été rendue, une case décochée ne postant rien. */
                    if ($signature_pseudo !== '') : ?>
                    <li>
                        <input type="hidden" name="signature_shown" value="1" />
                        <input type="checkbox" name="signature" value="pseudo" id="signature_pseudo" class="checkbox"
                            <?php if ($contributor_form->getValeur('signature') === 'pseudo') : ?> checked="checked" <?php endif; ?> />
                        <label class="continu" for="signature_pseudo">votre identifiant « <b><?= sanitizeForHtml($signature_pseudo) ?></b> »</label>
                    </li>
                    <?php endif; ?>
                    <li>
                        <input type="checkbox" name="avec_affiliation" value="oui" id="avec_affiliation" class="checkbox"
                            <?php if ($contributor_form->getValeur('avec_affiliation') === 'oui') : ?> checked="checked" <?php endif; ?> />
                        <label class="continu" for="avec_affiliation">avec votre affiliation (ci-dessus)</label>
                    </li>
                </ul>
            </fieldset>

            <p class="signature-apercu">
                <label>Aperçu</label>
                <output id="signature-preview" for="signature_pseudo avec_affiliation affiliations affiliation" aria-live="polite"
                    data-pseudo="<?= sanitizeForHtml($signature_signable_pseudo) ?>"
                    data-lieu="<?= sanitizeForHtml($can_edit_affiliations ? '' : (string) ($lieu_rattache['nom'] ?? '')) ?>"><?= $signature_preview_html === '' ? '<i>aucune signature</i>' : $signature_preview_html ?></output>
            </p>

        </fieldset>

        <fieldset id="evenement-defauts">
            <legend>Champs préremplis dans le formulaire « Ajouter un événement »</legend>

            <div class="guideForm">Vous n'aurez plus besoin de saisir ces valeurs à chaque nouvel événement</div>
            <p>
                <label for="ev_defaults_genre">Catégorie</label>
                <select name="ev_defaults_genre" id="ev_defaults_genre">
                    <option value=""></option>
                    <?php foreach (EventCategory::selectable(EventCategory::isEnabled()) as $genre_cle => $genre_label) : ?>
                    <option value="<?= sanitizeForHtml($genre_cle) ?>" <?php if ((string) $genre_cle === $ev_defaults['genre']) { echo 'selected="selected"'; } ?>><?= sanitizeForHtml($genre_label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $contributor_form->getHtmlErreur("ev_defaults_genre"); ?>
            </p>

            <p>
                <label for="ev_defaults_horaire_debut">Début</label>
                <input type="time" name="ev_defaults_horaire_debut" id="ev_defaults_horaire_debut" size="5" value="<?= sanitizeForHtml($ev_defaults['horaire_debut']); ?>" />
                <?= $contributor_form->getHtmlErreur("ev_defaults_horaire_debut"); ?>
            </p>

            <p>
                <label for="ev_defaults_horaire_fin">Fin</label>
                <input type="time" name="ev_defaults_horaire_fin" id="ev_defaults_horaire_fin" size="5" value="<?= sanitizeForHtml($ev_defaults['horaire_fin']); ?>" />
                <?= $contributor_form->getHtmlErreur("ev_defaults_horaire_fin"); ?>
            </p>

            <p>
                <label for="ev_defaults_idLieu">Lieu</label>
                <select name="ev_defaults_idLieu" id="ev_defaults_idLieu" class="js-select2-options-with-style" data-placeholder="">
                    <option value=""></option>
                    <?php
                    $canton_courant = null;

                    foreach ($lieux_defaut as $lieu_defaut)
                    {
                        $canton = (string) $lieu_defaut['canton'];

                        if ($canton !== $canton_courant)
                        {
                            if ($canton_courant !== null) { echo '</optgroup>'; }
                            echo '<optgroup label="' . (Localite::CANTONS[$canton] ?? sanitizeForHtml($canton)) . '">';
                            $canton_courant = $canton;
                        }

                        $selectionne = ((int) $lieu_defaut['idLieu'] === $ev_defaults['idLieu']) ? ' selected="selected"' : '';
                        echo '<option value="' . (int) $lieu_defaut['idLieu'] . '"' . $selectionne . '>' . sanitizeForHtml($lieu_defaut['nom']) . '</option>';
                    }

                    if ($canton_courant !== null) { echo '</optgroup>'; }
                    ?>
                </select>
                <?= $contributor_form->getHtmlErreur("ev_defaults_idLieu"); ?>
            </p>

            <p>
                <label for="ev_defaults_organisateurs">Organisateur(s)</label>
                <select name="ev_defaults_organisateurs[]" id="ev_defaults_organisateurs" class="js-select2-options-with-style" multiple data-placeholder="Tapez les noms des organisateurs">
                    <?php foreach ($organisateurs_actifs as $orga_defaut) : ?>
                    <option value="<?= (int) $orga_defaut['idOrganisateur'] ?>"<?= in_array((int) $orga_defaut['idOrganisateur'], $ev_defaults['idOrganisateurs'], true) ? ' selected="selected"' : '' ?>><?= sanitizeForHtml($orga_defaut['nom']) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $contributor_form->getHtmlErreur("ev_defaults_organisateurs"); ?>
            </p>

            <p>
                <label for="ev_defaults_prix">Prix</label>
                <input type="text" name="ev_defaults_prix" id="ev_defaults_prix" size="45" maxlength="100" value="<?= sanitizeForHtml($ev_defaults['prix']); ?>" />
                <?= $contributor_form->getHtmlErreur("ev_defaults_prix"); ?>
            </p>
        </fieldset>

        </fieldset>

        <p class="piedForm">
            <input type="hidden" name="formulaire" value="ok" />
            <input type="hidden" name="token" value="<?= SecurityToken::getToken(); ?>" />
            <input type="submit" value="Enregistrer" class="submit submit-big" />
        </p>

    </form>

</main>

<div id="colonne_gauche" class="colonne"></div>

<?php
include("../_footer.inc.php");
?>
