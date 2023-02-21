<?php

namespace Ladecadanse;

/**
 * Extented by DescriptionCollection, EvenementCollection, LieuCollection, OrganisateurCollection
 */
class Collection {

	var $nom;
	var $elements = array();
	var $connector;

	function __construct()
	{
		global $connector;
		$this->connector = $connector;
	}

	function setElement($id, $element): void
    {
		$this->elements[$id] = $element;
	}

	function getElement($id)
	{
		return $this->elements[$id];
	}

	function setElements($elements): void
    {
		$this->elements = $elements;
	}

	function getElements()
	{
		return $this->elements;
	}

	function getNbElements(): int
    {
		return count($this->elements);
	}
}
