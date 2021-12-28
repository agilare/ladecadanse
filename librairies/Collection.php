<?php

namespace Ladecadanse;

class Collection {

	var $nom;
	var $elements = array();
	var $connector;

	function __construct()
	{
		global $connector;
		$this->connector = $connector;
	}

	function setElement($id, $element)
	{
		$this->elements[$id] = $element;
	}

	function getElement($id)
	{
		return $this->elements[$id];
	}

	function setElements($elements)
	{
		$this->elements = $elements;
	}

	function getElements()
	{
		return $this->elements;
	}

	function getNbElements()
	{
		return count($this->elements);
	}
}
