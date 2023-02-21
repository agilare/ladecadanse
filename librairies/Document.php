<?php


namespace Ladecadanse;

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
