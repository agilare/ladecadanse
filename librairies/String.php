<?php

namespace Ladecadanse;

class String
{

	function arguments_URI($get, $sauf = "")
	{
		$afficher = "";

		foreach ($get as $nom => $valeur)
		{
			if ($nom != $sauf)
			{
				$afficher .= $nom."=".$valeur."&amp;";
			}
		}

		$afficher = mb_substr($afficher, 0, -5);

		return $afficher;

	}

	function verif_get($get, $type, $statut, $tab = '')
	{
		global $iconeErreur;

		$erreur = "";

		if ($get == '')
		{
			if ($statut == 1)
			{
				$erreur = "Ce paramètre est obligatoire";
			}
			else if ($statut != 0)
			{
				return $statut;
			}
			else
			{
				return;
			}

		}
		else
		{
			$get = trim($get);

			if ($type == "int")
			{
				if (is_numeric($get))
				{
					return $get;
				}
				else
				{
					$erreur = "Ce n'est pas un entier";
				}

			}
			else if ($type == "string")
			{
				if (is_string($get))
				{
					return $get;
				}
				else
				{
					$erreur = "Ce n'est pas une chaine";
				}
			}
			else if ($type == "date")
			{

				if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $get))
				{
					return $get;
				}
				else
				{
					$erreur = "Ce n'est pas une date";
				}
			}
			else if ($type == "enum")
			{
				if (in_array($get, $tab))
				{
					return $get;
				}
				else
				{
					$erreur = "Ce n'est pas une valeur acceptée";
				}
			}
			else if ($type == "alpha_numeric")
			{
				if (alpha_numeric($get))
				{
					return $get;
				}
				else
				{
					$erreur = "Ce n'est pas une valeur acceptée";
				}

			}


		}
	//
	//	trigger_error($iconeErreur.$erreur, E_USER_ERROR);
	//	exit;

	}



	function pw_encode($password)
	{
	   for ($i = 1; $i <= 10; $i++)
	       $seed .= mb_substr('0123456789abcdef', rand(0,15), 1);
	   return sha1($seed.$password.$seed).$seed;
	}

	function pw_check($password, $stored_value)
	{
	   if (mb_strlen($stored_value) != 50)
	      return FALSE;
	   $stored_seed = mb_substr($stored_value,40,10);
	   if (sha1($stored_seed.$password.$stored_seed).$stored_seed == $stored_value)
	     return TRUE;
	   else
	     return FALSE;
	}



	function replace_char_spec($name)
	{
	$name = strtr($name, 'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ',
	'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');

	$name = preg_replace('/([^.a-z0-9]+)/i', '-', $name);

	return $name;
	}

	function replace_accents($str)
	{
	  $str = htmlentities($str);
	  $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/','$1',$str);
	  return html_entity_decode($str);
	}

	function generatePassword($length=9, $strength=0) {
	    $vowels = 'aeuy';
	    $consonants = 'bdghjmnpqrstvz';
	    if ($strength & 1) {
	        $consonants .= 'BDGHJLMNPQRSTVWXZ';
	    }
	    if ($strength & 2) {
	        $vowels .= "AEUY";
	    }
	    if ($strength & 4) {
	        $consonants .= '23456789';
	    }
	    if ($strength & 8) {
	        $consonants .= '@#$%';
	    }

	    $password = '';
	    $alt = time() % 2;
	    for ($i = 0; $i < $length; $i++) {
	        if ($alt == 1) {
	            $password .= $consonants[(rand() % mb_strlen($consonants))];
	            $alt = 0;
	        } else {
	            $password .= $vowels[(rand() % mb_strlen($vowels))];
	            $alt = 1;
	        }
	    }
	    return $password;
	}


	function alpha_numeric ( $str )
	{
		return ( ! preg_match ( "/^([-a-z0-9])+$/i", $str ) ) ? FALSE : TRUE;
	}

}

?>