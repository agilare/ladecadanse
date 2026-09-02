<?php

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2026 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse\Utils;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Nettoie le HTML rédigé hors du site avant de l'enregistrer.
 *
 * Un texte de présentation arrive de partout : tapé dans TinyMCE, collé depuis un
 * traitement de texte, un site, un courriel, ou posté directement sans passer par
 * l'éditeur. Il porte alors des balises et des styles que rien ne garantit sûrs, et
 * que le site ne saurait de toute façon pas rendre.
 *
 * Ce qui est gardé : les éléments sûrs de Symfony, plus h3, blockquote et les liens.
 * Le reste est déballé ou retiré. Le nettoyage côté navigateur (web/js/edition.js)
 * fait le même travail à titre d'aperçu, pour que l'auteur voie tout de suite ce qui
 * sera conservé ; c'est ici, et ici seulement, que la décision est prise.
 *
 * La configuration était recopiée à l'identique dans OrganisateurEdition et dans
 * lieu-text-edit.php : deux endroits où assouplir la règle, un seul où penser à la
 * resserrer.
 */
final class UserHtmlSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('h3')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'title', 'target'])
            // TinyMCE (remove_script_host) écrit les liens internes en relatif (/lieu/lieu.php?idL=1),
            // sans ceci le href serait supprimé
            ->allowRelativeLinks(true)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer'));
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
