<?php
namespace Ladecadanse;

use Ladecadanse\Element;

class Description extends Element 
{

	function __construct()
	{
		$this->table = 'descriptionlieu';
	}

	function loadByType($type)
	{
		$sql = "SELECT * FROM ".$this->table." WHERE id".ucfirst($this->table)."=".$this->id." AND type='".$this->connector->sanitize($type)."'";
		//echo $sql;
		$res = $this->connector->query($sql);
		$this->valeurs = $this->connector->fetchAssoc($res);
	}
}
