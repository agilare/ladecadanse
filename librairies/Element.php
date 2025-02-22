<?php

namespace Ladecadanse;

/**
 * Extended by Description, Evenement, Lieu, Organisateur
 */
class Element
{

    public $id;
    public $valeurs = [];
    public $connector;
	public $table;

	function __construct()
	{
		global $connector;
		$this->connector = $connector;
	}

    function setId($id): void
    {
		$this->id = $id;
	}

	function getId(): int
    {
		return $this->id;
	}

	function setValue($nom, $valeur): void
    {
		$this->valeurs[$nom] = $valeur;
	}

	function getValue($nom)
	{
        return $this->valeurs[$nom] ?? '';
	}

	function setValues($tab): void
    {
		$this->valeurs = $tab;
	}

	function getValues()
	{
		return $this->valeurs;
	}

	function load(): void
    {
		$sql = "SELECT * FROM " . $this->table . " WHERE id" . ucfirst($this->table) . "=" . $this->id;

        $res = $this->connector->query($sql);
		$this->valeurs = $this->connector->fetchAssoc($res);
    }

	function insert(): bool
    {
		$sql = "INSERT INTO ".$this->table." SET ";
		foreach ($this->valeurs as $nom => $val)
		{
			$sql .= $nom."='".$this->connector->sanitize($val)."', ";
		}

		$sql = mb_substr($sql, 0, -2);

		if ($this->connector->query($sql))
		{
			$this->id= $this->connector->getInsertId();
			return true;
		}

		return false;
    }

	function update(): bool
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
            return true;
		}

        return false;
    }

    function getMaxId()
	{
		$req_max_id = $this->connector->query("SELECT MAX(id".ucfirst($this->table).") AS max_id FROM ".$this->table);
		$tab = $this->connector->fetchArray($req_max_id);
		return $tab['max_id'];
	}

	function getHtmlValue($nom): string
	{
        if (isset($this->valeurs[$nom]))
		{
            return sanitizeForHtml($this->valeurs[$nom]);
        }
		return '';
	}

}