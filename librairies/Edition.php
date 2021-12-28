<?php
namespace Ladecadanse;

use Ladecadanse\Validateur;

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
    var $nom;
    var $firstTime;
	var $id;
    var $valeurs = array();
    var $fichiers = array();
    var $supprimer = array();
    var $erreurs = array();

    var $verif;
	var $action;

	var $message;
    var $connector;


    function __construct($nom, $champs, $fichiers)
    {
		global $connector;

		$this->connector = $connector;
      	$this->nom = $nom;
      	//$this->wizardPage = $wPage;

      	$this->valeurs = $champs;
      	$this->fichiers = $fichiers;

      	$this->erreurs = array_merge($champs, $fichiers);

    }

    function traitement($post, $files)
    {

   		foreach ($this->valeurs as $nom => $val)
    	{
    		if (isset($post[$nom]))
    		{
				if (get_magic_quotes_gpc())
				{
					$this->valeurs[$nom] = stripslashes($post[$nom]);
				}
				else
				{
					$this->valeurs[$nom] = $post[$nom];
				}
    		}

    	}

    	foreach ($this->fichiers as $nom => $val)
    	{
    		$this->fichiers[$nom] = $files[$nom];
    	}

    	if (isset($post['supprimer']))
    	{
    			$this->supprimer[] = $post['supprimer'];
    	}

        //$GLOBALS['wizardPage'] = $this->NextWizardPage();


    }

    function IsCompleted() {
      return (!$this->FirstTime && count($this->Errors)<=0);
    }

    function verification()
    {
		/*
		 * Les vérifications par les classes filles se font ici
		 */

    }

	function loadValeurs($id)
	{


	}

    function enregistrer()
    {

    }


    function NextWizardPage() {}
    //abstract

    function Additional() {
      if ($this->wizardPage) :
    ?>
    <input type="Hidden" name="wizardPage" value="<?php echo $this->wizardPage?>">
    <?php endif;
    }

    function Set($Name, $Value) {
      $this->$Name = $Value;
    }

    function getErreur($champ)
    {
    	$erreur = $this->erreurs[$champ];
    	return $erreur;

    }

    function getNbErreurs()
    {

    	return count($this->erreurs);

    }
    function getHtmlErreur($champ)
    {
    	if ($this->erreurs[$champ] != '')
    	{
    		return '<div class="msg">'.$this->erreurs[$champ].'</div>';
    	}
    }

    function ErrorReport($Name) {
      if (isset($this->Errors[$Name]))
        printf($this->ErrorMessageFormat, $this->Errors[$Name]);
    }

    function GetInitialValue($Name) {
      if (isset($this->Values[$Name]))
        return $this->Values[$Name];
      else
        return false;
    }

    function InitialValue($Name) {
      echo $this->GetInitialValue($Name);
    }

    function setAction($action)
    {
    	$this->action = $action;
    }

    function getAction()
    {
    	return $this->action;

    }

    function setMessage($message)
    {
    	$this->message = $message;
    }

    function getMessage()
    {
    	return $this->message;

    }

	function getSupprimer()
	{
		return $this->supprimer;
	}

    function setSupprimer($sup)
    {
    	$this->supprimer = $sup;
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

	function getValeurs()
	{
		return $this->valeurs;
	}

}
