<?php

namespace Ladecadanse\Utils;

/**
 * Lecture des champs simples d'un formulaire soumis.
 *
 * Chaque formulaire recopiait la même garde, `isset($_POST[$c]) && is_scalar($_POST[$c])`, en
 * boucle ou champ par champ. is_scalar() écarte un « nom[]=x » forgé, qui déclencherait sinon une
 * conversion de tableau en chaîne, ou passerait un tableau à une validation qui attend du texte.
 */
class PostedFields
{
    /**
     * Les valeurs postées des champs nommés, en chaînes, sans retouche. Un champ absent ou
     * non scalaire est omis : à l'appelant de garder sa valeur précédente, ou d'en décider.
     *
     * Rien n'est trimé : un mot de passe garde ses espaces, et l'appelant trime ce qui doit l'être.
     *
     * @param array<string, mixed> $posted le contenu de $_POST
     * @param list<string>         $names  les champs attendus
     * @return array<string, string> champ => valeur, pour les seuls champs postés et scalaires
     */
    public static function scalars(array $posted, array $names): array
    {
        $values = [];

        foreach ($names as $name)
        {
            if (isset($posted[$name]) && is_scalar($posted[$name]))
            {
                $values[$name] = (string) $posted[$name];
            }
        }

        return $values;
    }
}
