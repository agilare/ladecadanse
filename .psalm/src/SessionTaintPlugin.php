<?php

declare(strict_types=1);

/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2026 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

namespace Ladecadanse\Psalm;

use PhpParser\Node\Expr\Variable;
use Psalm\Plugin\EventHandler\AddTaintsInterface;
use Psalm\Plugin\EventHandler\Event\AddRemoveTaintsEvent;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Psalm\Type\TaintKindGroup;
use SimpleXMLElement;

use function is_string;

/**
 * Ajoute $_SESSION aux sources de teinte de Psalm.
 *
 * Psalm ne tient pour sources que $_GET, $_POST, $_COOKIE et $_REQUEST
 * (Psalm\Internal\Analyzer\Statements\Expression\Fetch\VariableFetchAnalyzer::taintVariable).
 * $_SESSION est reconnue comme superglobale mais reçoit un tableau de teintes vide.
 *
 * Sur ce dépôt c'est un angle mort qui coûte : le site range des préférences d'affichage
 * en session — `user_prefs_agenda_order`, `user_prefs_lieux_order`, `user_prefs_lieux_statut` —
 * puis les concatène dans du SQL et du HTML. Le POC progpilot a trouvé là deux `ORDER BY`
 * interpolés que Psalm ne signalait pas (voir .progpilot/rapport-poc.md, signalements 1 et 2) ;
 * la cause mesurée était exactement celle-ci.
 *
 * Les teintes ajoutées sont les mêmes que celles de $_GET (TaintKindGroup::ALL_INPUT) : ce qui
 * transite par la session vient soit de la requête, soit de la base, et les deux méritent le
 * même traitement — un XSS stocké n'est pas moins un XSS.
 *
 * Contrepartie assumée : la session porte aussi le pseudo et le niveau de l'utilisateur
 * connecté, affichés sur toutes les pages. Ces sites-là remontent désormais, et sont triés
 * dans psalm-taint-baseline.xml.
 */
final class SessionTaintPlugin implements PluginEntryPointInterface, AddTaintsInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        $registration->registerHooksFromClass(self::class);
    }

    /**
     * @return list<string>
     */
    public static function addTaints(AddRemoveTaintsEvent $event): array
    {
        $expr = $event->getExpr();

        if (!$expr instanceof Variable || !is_string($expr->name) || $expr->name !== '_SESSION')
        {
            return [];
        }

        return TaintKindGroup::ALL_INPUT;
    }
}
