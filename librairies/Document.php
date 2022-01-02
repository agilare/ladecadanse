<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace Ladecadanse;

/**
 * Description of File
 *
 * @author michel
 */
class Document
{
    public static function getFilename(int $id, string $type = '', string $date_time = '', string $nom_original): string
    {
        $suffixe = mb_strrchr($nom_original, '.');

        $date = '';
        if ($date_time != '')
        {
            $dateAjoutTab = explode(" ", $date_time);
            $date = $dateAjoutTab[0];
        }

        return $id . "_" . $type . $date . $suffixe;
    }    
}
