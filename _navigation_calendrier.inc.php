<?php

//$get ?? [];

if (empty($get['courant'])) {
    $get['courant'] = $glo_auj_6h;
}

if (empty($get['genre'])) {
    $get['genre'] = '';
}

if (empty($get['tri_agenda'])) {
    $get['tri_agenda'] = "dateAjout";
}

if (empty($get['sem'])) {
    $get['sem'] = 0;
}

$tab_date_courant = explode("-", (string) $get['courant']);

$jour_courant = $tab_date_courant[2];
$mois_courant = $tab_date_courant[1];
$annee_courant = (int) $tab_date_courant[0];

$nb_jours_mois = (int) date("t", mktime(0, 0, 0, $mois_courant, $jour_courant, $annee_courant));

$jour_mois_prec = $jour_mois_suiv = $jour_courant;
if ((int) $jour_courant === $nb_jours_mois)
{
	$jour_mois_prec = date("t", mktime(0, 0, 0, (int) $mois_courant - 1, 1, $annee_courant));
    $jour_mois_suiv = date("t", mktime(0, 0, 0, (int) $mois_courant + 1, 1, $annee_courant));
}
$mois_prec = date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant - 1, $jour_mois_prec, $annee_courant));
$mois_suiv = date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant + 1, $jour_mois_suiv, $annee_courant));
?>

<div id="navigation_calendrier" >

    <table id="calendrier">
<!--        <caption>Calendrier pour choisir une date du mois</caption>-->
        <tr id="mois">
		<th>
		<?php
		if (mktime(0, 0, 0, (int) $mois_courant, $jour_courant, $annee_courant) > mktime(12, 0, 0, 9, 1, 2005))
        {
		?>
            <a href="<?php echo "/evenement-agenda.php?" . $url_query_region_et . "courant=" . $mois_prec . "&amp;genre=" . $get['genre'] . "&amp;sem=" . $get['sem'] . "&amp;tri_agenda=" . $get['tri_agenda']; ?>" title="mois précédent" >
                    <i class="fa fa-backward"></i></a>
		<?php
		}
		?>
		</th>
        <th id="mois_courant" colspan="6">
            <?php echo ucfirst((string) mois2fr($mois_courant)) . " " . $annee_courant ?>
        </th>
        <th>
            <a href="<?php echo "/evenement-agenda.php?" . $url_query_region_et . "courant=" . $mois_suiv . "&amp;sem=" . $get['sem'] . "&amp;genre=" . $get['genre'] . "&amp;tri_agenda=" . $get['tri_agenda']; ?>" title="mois suivant" >
                <i class="fa fa-forward"></i></a>
        </th>
    </tr>

	<tr id="jours">
        <th style="height: 18px;"></th>
        <th>lun</th><th>mar</th><th>mer</th><th>jeu</th><th>ven</th><th>sam</th><th>dim</th>
    </tr>

	<?php
	$nb_jours_mois = (int) date("t", mktime(0, 0, 0, (int) $mois_courant, 01, $annee_courant));

    $no_premier_jour_sem = date("w", mktime(0, 0, 0, (int) $mois_courant, 01, $annee_courant));
if ($no_premier_jour_sem == 0)
	{
		$no_premier_jour_sem = 7;
	}

	$no_dernier_jour_sem = date("w", mktime(0, 0, 0, (int) $mois_courant, $nb_jours_mois, $annee_courant));

if ($no_dernier_jour_sem == 0)
	{
		$no_dernier_jour_sem = 7;
	}

	$no_prem_sem_mois = date("W", mktime(0, 0, 0, (int) $mois_courant, 01, $annee_courant));
$no_dern_sem_mois = date("W", mktime(0,0,0,$mois_courant,$nb_jours_mois,$annee_courant));


    $tab_no_jour_sem = ["0", "1", "2", "3", "4", "5", "6", "0"];

	$pas = 1;
	$no_jour = "01";
	$cpt_sem = 1;
	$nb_jour_mois_avant = date("t", mktime(0, 0, 0, (int) $mois_courant - 1, 01, $annee_courant));
$b = $no_premier_jour_sem - 2;

	while ($pas <= $nb_jours_mois)
	{

		$classe ="";
		if ($cpt_sem == 1)
		{
            $pas_jour = $pas < 10 ? "0" . $pas : $pas;
        $date_deb_sem = $annee_courant . "-" . $mois_courant . "-" . $pas_jour;

        $lundim_cour = date_iso2lundim($get['courant']);
        $lundim_pas = date_iso2lundim($annee_courant . "-" . $mois_courant . "-" . $pas_jour);

        echo "<tr";
			//if (($get['sem'] == 1 && ($date_deb_sem >= $lundim_cour[0] && $date_deb_sem <= $lundim_cour[1])))
			if ($get['sem'] == 1 && $lundim_cour[0] == $lundim_pas[0] && $lundim_cour[1] == $lundim_pas[1])
			{
				echo " class=\"semaine semaine_ici\"";
			}
			else
			{
				echo " class=\"semaine\"";
			}

			echo "><td><a href=\"/evenement-agenda.php?" . $url_query_region_et . "courant=" . $date_deb_sem . "&amp;sem=1&amp;tri_agenda=" . $get['tri_agenda'] . "\" title=\"Semaine\"><i class=\"fa fa-caret-right\"></i>
</a></td>";

		}

	    if (Date("w", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant)) == $tab_no_jour_sem[$cpt_sem])
	    {
			$afficheJour = Date("j", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant));

			if (Date("Y-m-d", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant)) == Date("Y-m-d"))
            {
            	$classe = ' class="auj';
            	//echo Date("Y-m-d", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant));
            }

            if (Date("w", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant)) == 6 || Date("w", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant)) == 0) {
            if ($classe != '') {
					$classe .= ' sam';
				}
				else
				{
					$classe .= ' class="sam';
				}
				//echo Date("w", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant));
			}

        if (date("Y-m-d", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant)) < date("Y-m-d")) {
            if ($classe != '') {
                $classe .= ' past';
            }
            else {
                $classe .= ' class="past';
            }
        }

        if ($classe != '')
			{
				$classe .= '"';
			}



        if (date("Y-m-d", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant)) == date("Y-m-d", mktime(0, 0, 0, $mois_courant, $jour_courant, $annee_courant)) && $get['sem'] != 1) {
				$classe .= ' id="cal_ici"';
            }

			$proch_date = Date("Y-m-d", mktime(0, 0, 0, $mois_courant, $pas, $annee_courant));

			echo "<td".$classe.">";
			echo "<a href=\"/evenement-agenda.php?" . $url_query_region_et . "courant=" . $proch_date . "&amp;sem=0&amp;tri_agenda=" . $get['tri_agenda'] . "\" title=\"" . date_fr($proch_date, "annee", "", "", false) . "\">" . $pas . "</a></td>";

        $pas++;
    	}
    	else
    	{
			$jour_mois_avant = $nb_jour_mois_avant - $b;

			$proch_date = Date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant - 1, $jour_mois_avant, $annee_courant));
        echo '<td class="autre_mois';
    		if (Date("w", mktime(0, 0, 0, (int) $mois_courant - 1, $jour_mois_avant, $annee_courant)) == 6)
        {
				echo ' sam';
    		}
    		echo '">';
			echo "<a href=\"/evenement-agenda.php?" . $url_query_region_et . "courant=" . $proch_date . "&amp;genre=" . $get['genre'] . "&amp;sem=0&amp;tri_agenda=" . $get['tri_agenda'] . "\" title=\"\">" . $jour_mois_avant . "</a>";
        echo  "</td>";
    		$b--;
    	}

		if ($pas > $nb_jours_mois)
		{
			$i = $cpt_sem;
			$n = 1;
			while ($i < 7)
			{
				$proch_date = Date("Y-m-d", mktime(0, 0, 0, (int) $mois_courant + 1, $n, $annee_courant));
            echo '<td class="autre_mois';
	    		$no_jour_sem_suiv = Date("w", mktime(0, 0, 0, (int) $mois_courant + 1, $n, $annee_courant));
            if ($no_jour_sem_suiv == 6 || $no_jour_sem_suiv == 0)
	    		{
					echo ' sam';
	    		}
	    		echo '">';
				echo "<a href=\"/evenement-agenda.php?".$url_query_region_et."courant=".$proch_date."&amp;genre=".$get['genre']."&amp;sem=0&amp;tri_agenda=".$get['tri_agenda']."\" title=\"\">".$n."</a>";
				echo  "</td>\n";
				$i++;
				$n++;
			}
			echo "</tr>\n";

		}
		 if ($cpt_sem == 7 && $pas <= $nb_jours_mois)
		 {
		 	echo "</tr>\n";
		 	$cpt_sem = 1;
		 }
		 else
		 {
		 	$cpt_sem++;
		 }
	}


	?>

    </table>

    <ul id="menu_calendrier">
        <li id="demain">
            <?php $tab_auj = explode("-", (string) $glo_auj); ?>
            <a href="<?php echo "/evenement-agenda.php?" . $url_query_region_et . "courant=" . date("Y-m-d", mktime(12, 0, 0, $tab_auj[1], ((int) $tab_auj[2]) + 1, $tab_auj[0])) . "&amp;genre=" . $get['genre'] . "&amp;tri_agenda=" . $get['tri_agenda'] ?>" >Demain</a>
        </li>
        <li id="cette_semaine">
            <a href="<?php echo "/evenement-agenda.php?" . $url_query_region_et . "courant=" . $auj . "&amp;genre=" . $get['genre'] . "&amp;tri_agenda=" . $get['tri_agenda'] . "&amp;sem=1" ?>" >
                Cette semaine
            </a>
        </li>
        <li>
            <form action="/evenement-agenda.php" method="get" >
                <fieldset>
                    <input type="hidden" name="genre" value="<?php echo $get['genre'] ?>" />
                    <input type="hidden" name="sem" value="<?php echo $get['sem'] ?>" />
                    <input type="hidden" name="tri_agenda" value="<?php echo $get['tri_agenda'] ?>" />

                <input type="text" name="courant" size="8" placeholder="jj.mm.aaaa" style="width:6em;" /><input type="submit" class="submit" name="formulaire" value="OK" />

            </fieldset>
            </form>
            </li>
    </ul>

    <div class="spacer"></div>

</div>
<!-- Fin navigation_calendrier -->
