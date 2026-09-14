<?php
namespace Ladecadanse;

/**
 * Contrat des classes qui traitent un formulaire d'édition.
 *
 * Ne porte que ce que toutes partagent : les valeurs saisies, les champs fichier,
 * l'intention (« insert » ou « update »), le message à afficher après enregistrement —
 * et les quatre temps du cycle de vie, déclarés sans corps parce qu'aucune fille n'a
 * jamais réutilisé celui d'ici.
 *
 * Elle portait aussi, jusqu'à la 3.13.0, un connecteur mysqli pris dans une globale et
 * jamais relu, un tableau d'erreurs que trois `validate()` remplissaient sans que
 * personne le lise, et quatre implémentations par défaut dont deux rendaient
 * silencieusement `false` ou `null` : une classe fille qui aurait oublié de les
 * surcharger n'aurait rien chargé ni rien validé, sans un mot. D'où `abstract`.
 *
 * Implémentée par FicheEdition (lieu, organisateur) et SalleEdition.
 */
abstract class Edition
{
    /** « insert » ou « update », telle que la page l'a décidée. */
    public ?string $action = null;

    /** Ce que l'enregistrement a produit, à afficher après la redirection. */
    public ?string $message = null;

    /**
     * @param array<string, mixed> $valeurs champs du formulaire, avec leur valeur initiale
     * @param array<string, mixed> $fichiers champs de type fichier
     */
    function __construct(public array $valeurs, public array $fichiers)
    {
    }

    /**
     * Traite un formulaire soumis.
     *
     * Les deux paramètres portent des superglobales : la page passe $_POST et $_FILES
     * tels quels, aucune valeur n'y est encore validée.
     *
     * @param array<string, mixed> $postGlobal contenu de $_POST
     * @param array<string, mixed> $filesGlobal contenu de $_FILES
     */
    abstract public function processSubmission(array $postGlobal, array $filesGlobal): bool;

    /** @return bool false dès qu'un champ est en erreur ; les messages vont au Validateur. */
    abstract public function validate(): bool;

    /** Insère ou met à jour, selon l'intention passée à setAction(). */
    abstract public function upsert(): bool;

    /**
     * Charge l'enregistrement à modifier.
     *
     * @return bool false si l'identifiant ne désigne rien : à la page de répondre 404
     *              plutôt que d'afficher un formulaire vide.
     */
    abstract public function loadValues(int $id): bool;

    function setAction(?string $action): void
    {
    	$this->action = $action;
    }

    function getResultMessage(): ?string
    {
    	return $this->message;
    }

	function getValeur($nom)
	{
		if (isset($this->valeurs[$nom]))
		{
			return $this->valeurs[$nom];
		}
		else
		{
			return NULL;
		}
	}

    function setValeur($nom, $val)
    {
    	$this->valeurs[$nom] = $val;
    }
}
