<?php
namespace Ladecadanse;

/**
  * An abstract class implementing generic functionality for processing user's input
  *
  * This class encapsulates generic functions for working
  * with data coming from user forms. Descendants must only override certain
  * functions that perform context-specific tasks, like custom checking of
  * data, storing correct data, etc.

  */
  class Edition
  {
	public $id;
    public $supprimer = [];
    public $erreurs = [];
	public $action;

	public $message;
    public $connector;

    /*
     * Le nom de l'entité éditée ('lieu', 'organisateur', 'salle') était un quatrième
     * paramètre, que rien ne lisait : les classes filles nomment leur table par leur
     * propre contrat (FicheEdition::table()). Le marquer @deprecated n'était pas tenable,
     * l'analyse signalant alors sa propre écriture ici.
     */
    function __construct(public $valeurs, public $fichiers)
    {
		global $connector;

		$this->connector = $connector;

      	$this->erreurs = array_merge($this->valeurs, $this->fichiers);
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
    function processSubmission(array $postGlobal, array $filesGlobal)
    {
        foreach ($this->valeurs as $nom => $val)
    	{
    		if (isset($postGlobal[$nom]))
    		{
                $this->valeurs[$nom] = $postGlobal[$nom];
    		}
        }

    	foreach ($this->fichiers as $nom => $val)
    	{
    		// un champ fichier peut ne pas figurer dans $_FILES : le formulaire ne
    		// l'affiche pas pour tous les niveaux d'utilisateur. On garde alors la
    		// valeur par défaut déclarée.
    		if (isset($filesGlobal[$nom]))
    		{
    			$this->fichiers[$nom] = $filesGlobal[$nom];
    		}
    	}

    	if (isset($postGlobal['supprimer']))
    	{
    			$this->supprimer[] = $postGlobal['supprimer'];
    	}
    }

    function validate()
    {
		/*
		 * Les vérifications par les classes filles se font ici
		 */

    }

	/**
	 * Charge l'enregistrement à modifier.
	 *
	 * @return bool false si l'identifiant ne désigne rien : à la page de répondre 404
	 *              plutôt que d'afficher un formulaire vide.
	 */
	function loadValues(int $id): bool
    {
		return false;
	}

    function upsert()
    {

    }


    function getErreur(string $champ): string
    {
    	$erreur = $this->erreurs[$champ] ?? '';

    	return is_string($erreur) ? $erreur : '';
    }

    /**
     * Nombre de champs réellement en erreur.
     *
     * $erreurs est initialisé dans le constructeur avec toutes les clés de
     * champs du formulaire, valeur vide ; seules celles que la vérification a
     * remplies d'un message comptent comme des erreurs.
     */
    function getNbErreurs(): int
    {
    	return count(array_filter($this->erreurs, static fn($erreur): bool => !empty($erreur)));
    }

    function getHtmlErreur(string $champ): ?string
    {
    	if (empty($this->erreurs[$champ]))
    	{
    		return null;
    	}

    	return '<div class="msg">'.$this->erreurs[$champ].'</div>';
    }

    function setAction($action)
    {
    	$this->action = $action;
    }

    /** Ce que l'enregistrement a produit, à afficher en message flash après redirection. */
    function getResultMessage(): ?string
    {
    	return $this->message;
    }

	function getSupprimer()
	{
		return $this->supprimer;
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
