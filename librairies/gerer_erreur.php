<?php
// La fonction de gestion des erreurs
function gerer_erreur($errno , $errstr , $errfile , $errline)
{

	global $rep_site;
    // Variables nécessaires au traitement des erreurs
    // Booléen permettant d'indiquer si on doit stopper ou non l'exécution du script
    // Par défaut on stop
    $stopper = true;

    // Booléen permettant d'indiquer si on doit ou non afficher le message d'erreur
    // Par défaut on masque
	if (MODE_DEBUG == true)
	{
		$afficher = true;
	}
	else
	{
		$afficher = false;
	}

    // On détermine le type d'erreur et on affecte les variables et le cas échéant
    switch ($errno)
    {
        case E_USER_NOTICE :
		{
			$type = "Notification utilisateur";
			break;
		}
        case E_NOTICE :
        {
            $stopper = false;
            $type = "Notification";
            break;
        }

        case E_COMPILE_WARNING :
        case E_CORE_WARNING :
        case E_USER_WARNING :  $type = "Avertissement"; break;
        case E_WARNING :
        {
            $stopper = false;
            $type = "Avertissement";
            break;
        }

        case E_PARSE :
        {
            $afficher = true;
            $type = "Syntaxe";
			break;
        }

        case E_COMPILE_ERROR : $type = "Erreur de compilation"; break;
        case E_CORE_ERROR : $type = "Erreur du noyeau"; break;
        case E_USER_ERROR :
        case E_ERROR :
        {
            $afficher = true;
            $type = "Erreur";
            break;
        }

        default :
        {
            //echo "Erreur inconnue : [" . $errno . "] => " . $errstr . "<br>";
            $afficher = true;
            $type = "Erreur inconnue";
            break;
        }
    }

    // Construction du message d'erreur
    $message = date ("H:i:s d m Y")." ".$type . " : " . $errstr;
    $message .= " dans le fichier " . $errfile . " à la ligne " . $errline;
	$message .= "\n";
	$headers = 'From: Webmaster <michel@ladecadanse.ch>';

	// On enregistre l'erreur dans le fichier '/var/php/erreurs.log'
	if (file_exists($rep_site."admin/.ht_erreurs.log"))
	{
		error_log($message , 3, $rep_site."admin/.ht_erreurs.log");
	}

	if ($errno == E_NOTICE || $errno == E_WARNING || $errno == E_USER_NOTICE || $errno == E_USER_WARNING )
	{
		if (!MODE_DEBUG)
		{
			//@mail($glo_email_admin, "[La décadanse] Avertissement", $message, $headers);
		}
		else
		{
			echo '<p style="background-color:#FFFF88;border:1px solid orange;line-height:1.3em">'.nl2br($message).'</p>';
		}

	}
 	else
	{
		if (!MODE_DEBUG)
		{
			@mail("michel@ladecadanse.ch", "[La décadanse] Erreur critique", $message, $headers);

			exit("Problème, veuillez repasser plus tard");
		}
		else
		{
			echo '<p style="background-color:yellow">'.nl2br($message).'</p>';
		}
	}
}

// Fin de la fonction

?>