<?php

declare(strict_types=1);

namespace Ladecadanse\Security;

use Ladecadanse\UserLevel;

/**
 * La personne qui remplit un formulaire d'édition, et ce que son niveau l'autorise à y
 * changer.
 *
 * Les pages calculaient ces droits chacune de leur côté — le même
 * `$_SESSION['Sgroupe'] <= UserLevel::ADMIN` écrit dans les deux formulaires de fiche —
 * puis les passaient au formulaire par trois appels distincts. Oublier l'un des trois se
 * lisait comme un refus, sans que rien ne le signale.
 *
 * En lecture seule : un formulaire ne redéfinit pas les droits de qui le remplit, il les
 * consulte. C'est ce qui permet à FicheEdition de reprendre en base ce que le POST n'a
 * pas le droit de décider (statusToWrite(), fillEditorsFieldsValuesIfNotAllowed()).
 */
final class CurrentUserEditing
{
    private function __construct(
        /** 0 pour un contenu sans auteur — voir FicheEdition::insert(). */
        public readonly int $idPersonne,
        /** Publier ou dépublier une fiche reste une décision de modération. */
        public readonly bool $canChangeStatus,
        /**
         * Le nom, la préposition, les catégories et les organisateurs d'un lieu engagent
         * tous les événements qui s'y déroulent : réservés aux éditeurs.
         */
        public readonly bool $canEditEditorFields,
    )
    {
    }

    /**
     * @param array<string, mixed> $session le contenu de $_SESSION, lu sans être modifié
     */
    public static function fromSession(array $session, Authorization $authorization): self
    {
        return new self(
            (int) ($session['SidPersonne'] ?? 0),
            isset($session['Sgroupe']) && $session['Sgroupe'] <= UserLevel::ADMIN,
            $authorization->isPersonneEditor($session),
        );
    }

    /**
     * Aucun droit : ce que porte un formulaire tant que la page ne lui a rien dit.
     *
     * Refuser par défaut plutôt qu'accorder — un formulaire instancié hors requête HTTP,
     * dans un test par exemple, ne doit pas se retrouver plus permissif que celui qu'un
     * visiteur a sous les yeux.
     */
    public static function withoutRights(): self
    {
        return new self(0, false, false);
    }
}
