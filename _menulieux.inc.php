<?php

use Ladecadanse\Utils\Utils;
use Ladecadanse\Utils\Validateur;

$de = "0";
$vers = "l";

$tab_statuts = array("actif", "inactif", "ancien");
$get['statut'] = "actif";
if (isset($_GET['statut']))
{
    try
    {
        $get['statut'] = Validateur::validateUrlQueryValue($_GET['statut'], "enum", 1, $tab_statuts);
    } catch (Exception $e)
    {
        exit;
    }
}

$tab_vues = array("az", "genre", "quartier");
if (isset($_GET['vue']))
{
    try
    {
        $get['vue'] = Validateur::validateUrlQueryValue($_GET['vue'], "enum", 1, $tab_vues);
    } catch (Exception $e)
    {
        exit;
    }
    if ($get['vue'] != "az")
    {
        $de = "0";
        $vers = "z";
    }
}
else
{
    $get['vue'] = "az";
}


$tab_tranches = array_merge(array_merge(array("ak", "lz", "tout"), array_keys($glo_categories_lieux)), $glo_tab_quartiers);

if (isset($_GET['tranche']))
{
    try
    {
        $get['tranche'] = Validateur::validateUrlQueryValue($_GET['tranche'], "enum", 1, $tab_tranches);
    } catch (Exception $e)
    {
        exit;
    }

    if ($get['vue'] == "az")
    {


        if ($get['tranche'] == "ak")
        {
            $de = "0";
            $vers = "l";
        }
        else if ($get['tranche'] == "lz")
        {

            $de = "l";
            $vers = "z";
        }
        else if ($get['tranche'] == "tout")
        {
            $de = "0";
            $vers = "z";
        }
    }
    else if ($get['vue'] == "genre")
    {

    }
    else if ($get['vue'] == "quartier")
    {

    }
}
else
{
    if ($get['vue'] == "az")
    {
        $get['tranche'] = "ak";
    }
    else if ($get['vue'] == "genre")
    {
        $get['tranche'] = "bistrot";
    }
    else if ($get['vue'] == "quartier")
    {
        $get['tranche'] = "Centre";
    }
}

$url_tranche = "&amp;tranche=" . $get['tranche'];

$url_idLieu = "";
if (isset($get['idL']))
{
    $url_idLieu = "&amp;idL=" . $get['idL'];
}

$aff_menulieux = '<div id="menu_lieux"><ul class="selon">
	<li';
if ($get['statut'] == "actif")
{
    $aff_menulieux .= " class=\"ici\"";
}
$aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=actif&amp;vue=az' . $url_idLieu . '">Actuels</a></li><li';
if ($get['statut'] == "ancien")
{
    $aff_menulieux .= " class=\"ici\"";
}
$aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=ancien&amp;vue=az' . $url_idLieu . '" >Anciens</a></li>';

$aff_menulieux .= '
<div class="spacer"><!-- --></div></ul>
    ';

$aff_menulieux .= '
<ul class="selon">
	<li';
if ($get['vue'] == "az")
{
    $aff_menulieux .= " class=\"ici\"";
}
$aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=' . $get['statut'] . '&amp;vue=az' . $url_idLieu . '" title="Liste alphabétique">A-Z</a></li><li';
if ($get['vue'] == "genre")
{
    $aff_menulieux .= " class=\"ici\"";
}
$aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=' . $get['statut'] . '&amp;vue=genre' . $url_idLieu . '" title="Liste par genre">Type</a></li>';

$aff_menulieux .= '
<div class="spacer"><!-- --></div></ul>
		';

$sql_vue = "";

if ($get['vue'] == "az")
{
    $aff_menulieux .= '
				<ul id="tranches">
				<li';
    if ($get['tranche'] == "ak")
    {
        $aff_menulieux .= " class=\"ici\"";
    }
    $aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=' . $get['statut'] . '&amp;vue=az' . $url_idLieu . '&amp;tranche=ak" title="Liste alphabétique">a-k</a></li>
				<li';
    if ($get['tranche'] == "lz")
    {
        $aff_menulieux .= " class=\"ici\"";
    }
    $aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=' . $get['statut'] . '&amp;vue=az' . $url_idLieu . '&amp;tranche=lz" title="Liste alphabétique">l-z</a></li>
				<li';
    if ($get['tranche'] == "tout")
    {
        $aff_menulieux .= " class=\"ici\"";
    }
    $aff_menulieux .= '><a href="' . $_SERVER['PHP_SELF'] . '?' . $url_query_region_et . 'statut=' . $get['statut'] . '&amp;vue=az' . $url_idLieu . '&amp;tranche=tout" title="Liste alphabétique">tout</a></li>
				<div class="spacer"><!-- --></div></ul>';

    $sql_vue = "AND TRIM(LEADING 'l\'' FROM (TRIM(LEADING 'les ' FROM (TRIM(LEADING 'la ' FROM (TRIM(LEADING 'le ' FROM LOWER(nom)))))))) >=  LOWER('" . $de . "%')";

    if ($get['tranche'] == 'ak')
    {
        $sql_vue .= "AND TRIM(LEADING 'l\'' FROM (TRIM(LEADING 'les ' FROM (TRIM(LEADING 'la ' FROM (TRIM(LEADING 'le ' FROM lower(nom)))))))) <= LOWER('" . $vers . "%')";
    }
}
else if ($get['vue'] == "genre")
{
    $aff_menulieux .= "
				<form action=\"" . $_SERVER['PHP_SELF'] . "\" method=\"get\">

				<input type=\"hidden\" name=\"vue\" value=\"genre\" />
				<input type=\"hidden\" name=\"idL\" value=\"" . $get['idL'] . "\" />
				<input type=\"hidden\" name=\"statut\" value=\"" . $get['statut'] . "\" />
				<select name=\"tranche\" onChange=\"javascript:this.form.submit();\">";
    foreach ($glo_categories_lieux as $c => $c_nom)
    {


        $aff_menulieux .= "<option value=\"" . $c . "\"";
        if ($c == $get['tranche'])
        {
            $aff_menulieux .= " selected=\"selected\"";
        }
        $aff_menulieux .= ">" . $c_nom . "</option>";
    }
    $aff_menulieux .= "</select><input class=\"submit\" type=\"submit\" value=\"ok\" size=\"1\" />

				</form>";

    $sql_vue .= "AND categorie LIKE '%" . $get['tranche'] . "%'";
}

$aff_menulieux .= '<div class="spacer"><!-- --></div>';
$aff_menulieux .= '<table summary="Menu des lieux"><tr><th><img src="' . $url_images_interface_icons . 'building.png" alt="Lieu"  />';

$aff_menulieux .= '</th>
		<th><img src="' . $url_images_interface_icons . 'page_white_text.png" alt="Description" title="Description" /></th>
		<th><img src="' . $url_images_interface_icons . 'calendar.png" alt="Nombre d\'événements agendés" title="Nombre d\'événements agendés" /></th></tr>';

/*
 * Requète SQL vers table 'lieu' selon choix de listage (AK ou LZ) et pour les lieux
 * actif ou non
 */
$sql_rf = "";
if ($_SESSION['region'] == 'ge')
    $sql_rf = " 'rf', ";

$sql_menu_lieux = "
SELECT idLieu, nom
FROM lieu
WHERE statut='" . $get['statut'] . "'  " . $sql_vue . " AND region IN ('" . $connector->sanitize($_SESSION['region']) . "', " . $sql_rf . " 'hs')
ORDER BY TRIM(LEADING 'l\'' FROM (TRIM(LEADING 'les ' FROM (TRIM(LEADING 'la ' FROM (TRIM(LEADING 'le ' FROM lower(nom)))))))) COLLATE utf8mb4_unicode_ci";

$req_lieux = $connector->query($sql_menu_lieux);

$nb_lieux = $connector->getNumRows($req_lieux);

$pair = 0;
$prec = "";
$url_prec = "";
$url_suiv = "";
$nomDuLieu = "";
$id_passe = 0;

while (list ($id, $nom) = mysqli_fetch_row($req_lieux))
{
    $nb_evenements = 0;
    $aumoins1des = "";
    $req_des = $connector->query("SELECT idLieu FROM descriptionlieu WHERE idLieu=" . $id . " AND type='description'");

    $nomDuLieu = $nom;

    // Précision pour dire si le lieu a une ou plusieurs descriptions
    if ($connector->getNumRows($req_des) > 0)
    {
        $aumoins1des = "*";
    }

    $sql_even = "SELECT titre FROM evenement WHERE idLieu=" . $id . " AND dateEvenement >= '" . date("Y-m-d") . "' AND statut='actif'";

    //echo $sql_even;

    $req_even = $connector->query($sql_even);
    // Précision pour dire si le lieu a une ou plusieurs descriptions

    $nb_evenements = $connector->getNumRows($req_even);

    $aff_menulieux .= "<tr ";
    if ($id == $get['idL'])
    {
        $aff_menulieux .= "id=\"ici\"";

        $url_prec = $prec;
        $id_passe = 1;
    }
    else if ($pair % 2 != 0)
    {
        $aff_menulieux .= "class=\"impair\"";
    }



    $aff_menulieux .= ">
	<td><a href=\"/lieu.php?idL=" . $id . "&amp;" . Utils::urlQueryArrayToString($get, array("idL", "type_description")) . "\">";

    if (preg_match("/^(Le |La |Les |L')(.*)/", $nomDuLieu, $matches))
    {
        $aff_menulieux .= $matches[2];
        $aff_menulieux .= '<span style="font-size:1em">, ' . $matches[1] . '</span>';
    }
    else
    {
        $aff_menulieux .= $nomDuLieu;
    }

    $aff_menulieux .= "</a></td>
	<td class=\"nb_desc_lieu\">" . $aumoins1des . "</td>";
    $nb_aff = "";
    if ($nb_evenements > 0)
    {
        $nb_aff = $nb_evenements;
    }
    $aff_menulieux .= "
	<td class=\"nb_even_lieu\">" . $nb_aff . "</td>
	</tr>";

    $prec = "/lieu.php?vue=" . $get['vue'] . "&amp;idL=" . $id . $url_tranche . "";

    if ($id_passe && $url_suiv == "" && $id != $get['idL'])
    {
        if ($pair != ($nb_lieux))
        {
            $url_suiv = $prec;
        }
    }
    $pair++;
}

$aff_menulieux .= "
	<tr><td></td></tr>
		</table>


	</div>";
?>
<!-- Fin menu_lieux -->
