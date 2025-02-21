<?php


namespace Ladecadanse;

class Document
{
    public static function getFilename(string $nom_original, int $id, string $type = '', string $date_time = ''): string
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
