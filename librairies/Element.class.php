<?php
/**
 * Lance la session et vérifie le login du visiteur
 *
 *
 * PHP versions 4 and 5
 *
 * @category   librairie
 * @author     Michel Gaudry <michel@ladecadanse.ch>
 * @see        SystemComponent.php
 */


class Element
{

   /**
 *
 * @var string
 */
 	var $id;
	var $valeurs = array();
	var $connector;
	var $table;

 /**
 	 * Démarre la session et inclut un en-tête interdisant de stocker le mot
	 * de passe dans le cache de l'utilisateur
   * @access public
   */
	function __construct()
	{
		global $connector;
		$this->connector = $connector;
	}

 
        
	function setId($id)
	{
		$this->id = $id;
	}

	function getId()
	{
		return $this->id;
	}

	function setValue($nom, $valeur)
	{
		$this->valeurs[$nom] = $valeur;
	}

	function getValue($nom)
	{
		return $this->valeurs[$nom];
	}

	function setValues($tab)
	{
		$this->valeurs = $tab;
	}

	function getValues()
	{
		return $this->valeurs;
	}

	function load()
	{
		$sql = "SELECT * FROM ".$this->table." WHERE id".ucfirst($this->table)."=".$this->id;
//		echo $sql;

		$res = $this->connector->query($sql);
		$this->valeurs = $this->connector->fetchAssoc($res);

	}

	function insert()
	{
		$sql = "INSERT INTO ".$this->table." SET ";
		foreach ($this->valeurs as $nom => $val)
		{
			$sql .= $nom."='".$this->connector->sanitize($val)."', ";
		}

		$sql = mb_substr($sql, 0, -2);


		//echo $sql;

		if ($this->connector->query($sql))
		{
			$this->id= $this->connector->getInsertId();
			return true;
		}
		else

		{
			return false;
		}
	}

	function update()
	{
		$sql = "UPDATE ".$this->table." SET ";
		foreach ($this->valeurs as $nom => $val)
		{
			$sql .= $nom."='".$this->connector->sanitize($val)."', ";
		}

		$sql = mb_substr($sql, 0, -2);
		$sql .= " WHERE id".ucfirst($this->table)."=".$this->id;

		//echo $sql;

		if ($this->connector->query($sql))
		{
			//echo "ok update";
			return true;
		}
		else
		{
			return false;
		}
	}

	function delete()
	{
		$this->connector->query("DELETE FROM ".$this->table." WHERE id".ucfirst($this->table)."=".$this->id);
	}

	function getMaxId()
	{
		$req_max_id = $this->connector->query("SELECT MAX(id".ucfirst($this->table).") AS max_id FROM ".$this->table);
		$tab = $this->connector->fetchArray($req_max_id);
		return $tab['max_id'];
	}

	function getHtmlValue($nom)
	{
		return securise_string($this->valeurs[$nom]);
	}

	function securise_string($chaine)
	{
		//$chaine = trim($chaine);
		//return $chaine;
		//return trim(str_replace(array("&gt;", "&lt;", "&quot;"), array(">", "<", "\""), $chaine));
		return trim(htmlspecialchars($chaine));
	//enlevï¿½ "&" "&amp;"
	//$chaine = str_replace(';','&#x3B',$chaine);
	}

}