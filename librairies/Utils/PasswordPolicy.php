<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

/**
 * Règles du mot de passe choisi par un membre, et liste des mots de passe refusés.
 *
 * Les trois formulaires qui fixent un mot de passe (inscription, réinitialisation,
 * profil) recopiaient les mêmes règles et relisaient `resources/bad_p.txt` chacun à
 * sa façon, par un chemin relatif au script — qui casse dès qu'une page change de
 * répertoire.
 *
 * La liste vient de tarraschk/richelieu (CC BY 4.0), mots de passe français issus de
 * fuites publiques ; voir l'en-tête du fichier.
 *
 * Une seconde liste, `resources/bad_p_context.txt`, porte les mots propres au site et à
 * sa région (Genève, Vaud, France voisine) qu'aucune fuite générale ne contient. Elle
 * n'est pas comparée telle quelle mais par ses dérivés : voir isContextDerivative().
 */
final class PasswordPolicy
{
    public const LONGUEUR_MIN = 10;
    public const LONGUEUR_MAX = 100;

    private const LEAKED_FILE = '/resources/bad_p.txt';
    private const CONTEXT_FILE = '/resources/bad_p_context.txt';

    /**
     * Une racine plus courte, une fois normalisée, ferait surtout des faux positifs par
     * concaténation (« ge » + « neve »…).
     */
    private const ROOT_MIN_LENGTH = 3;

    /**
     * Substitutions « leet » courantes. 1 et ! se lisent i ou l : les deux lectures sont
     * essayées.
     */
    private const LEET_COMMON = ['@' => 'a', '4' => 'a', '3' => 'e', '0' => 'o', '$' => 's', '5' => 's', '7' => 't'];
    private const LEET_AMBIGUOUS = ['1', '!', '|'];

    /**
     * Sans ext-intl, que composer.json n'exige pas : les lettres accentuées du français et
     * de l'allemand suffisent pour les noms de la région.
     */
    private const ACCENTS = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ý' => 'y',
        'ñ' => 'n',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    /**
     * Racine du projet déduite de l'emplacement de la classe (librairies/Utils/) plutôt
     * que de la constante __ROOT__ : les tests unitaires ne chargent que l'autoloader,
     * pas app/config.php.
     */
    private static function filePath(string $file): string
    {
        return dirname(__DIR__, 2) . $file;
    }

    /**
     * Liste chargée une fois par requête : le fichier fait 20 000 lignes.
     *
     * @var string[]|null
     */
    private static ?array $leaked = null;

    /**
     * Racines normalisées, en clés pour un accès direct.
     *
     * @var array<string, true>|null
     */
    private static ?array $roots = null;

    /**
     * Vérifie un mot de passe, et sa confirmation quand le formulaire en demande une.
     *
     * La règle « au moins un chiffre » a été retirée : le SP 800-63B-4 du NIST proscrit les
     * règles de composition, qui mènent surtout à des mots de passe courts et prévisibles,
     * et s'en remet à la longueur et au filtrage des mots de passe déjà fuités — ce que
     * cette classe fait déjà avec bad_p.txt.
     *
     * @param string|null $confirmation null quand le formulaire n'a qu'un champ, ce qui est
     *                                  désormais le cas de l'inscription et de la
     *                                  réinitialisation
     * @return array<string, string> erreurs indexées par nom de champ, vide si tout va bien.
     *                               Les clés sont celles attendues par les formulaires
     *                               (`motdepasse`, `motdepasse_inegaux`).
     */
    public static function erreurs(string $motdepasse, ?string $confirmation = null): array
    {
        $erreurs = [];

        if ($motdepasse === '')
        {
            $erreurs['motdepasse'] = "Veuillez saisir un mot de passe.";
        }
        else if (mb_strlen($motdepasse) < self::LONGUEUR_MIN || mb_strlen($motdepasse) > self::LONGUEUR_MAX)
        {
            $erreurs['motdepasse'] = "Votre mot de passe doit faire entre " . self::LONGUEUR_MIN
                . " et " . self::LONGUEUR_MAX . " caractères.";
        }
        // la liste ne dit rien de plus que la règle ci-dessus tant qu'elle n'est pas
        // satisfaite : inutile de la charger pour un mot de passe déjà refusé
        else if (self::estRefuse($motdepasse))
        {
            $erreurs['motdepasse'] = "Ce mot de passe est trop courant ou trop facile à deviner, veuillez en choisir un autre.";
        }

        if ($confirmation !== null && $confirmation !== $motdepasse)
        {
            $erreurs['motdepasse_inegaux'] = "Les 2 mots de passe doivent être identiques.";
        }

        return $erreurs;
    }

    /**
     * Le mot de passe figure-t-il parmi les plus courants, ou dérive-t-il d'un mot du
     * contexte du site ?
     */
    public static function estRefuse(string $motdepasse): bool
    {
        return in_array($motdepasse, self::leakedList(), true)
            || self::isContextDerivative($motdepasse);
    }

    /**
     * Le mot de passe se réduit-il à une ou deux racines du contexte (« Servette1890 »,
     * « p@quis2024! », « GeneveCarouge ») ?
     *
     * La comparaison porte sur le mot de passe entier une fois normalisé, jamais sur une
     * racine qu'il contiendrait : une phrase comme « automne-a-geneve-sous-la-pluie » reste
     * acceptée.
     */
    public static function isContextDerivative(string $password): bool
    {
        $roots = self::roots();

        if ($roots === [])
        {
            return false;
        }

        foreach (self::normalizedForms($password) as $form)
        {
            if (isset($roots[$form]))
            {
                return true;
            }

            // deux racines accolées ; une seule coupe suffit à conclure
            for ($i = self::ROOT_MIN_LENGTH, $n = strlen($form); $i <= $n - self::ROOT_MIN_LENGTH; $i++)
            {
                if (isset($roots[substr($form, 0, $i)], $roots[substr($form, $i)]))
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Les lectures possibles d'un mot de passe, réduites aux lettres a-z.
     *
     * Trois voies, parce que les chiffres jouent deux rôles :
     *  - chiffres et symboles retirés partout, sans leet : « servette1890 » → « servette »
     *    (en leet, 1890 deviendrait des lettres parasites) ;
     *  - leet appliqué à tout : « l4dec4dans3 » → « ladecadanse » (le 3 final est une lettre) ;
     *  - bords retirés, puis leet : « p@quis2024 » → « paquis ».
     *
     * @return string[]
     */
    public static function normalizedForms(string $password): array
    {
        $base = strtr(mb_strtolower($password), self::ACCENTS);
        $inner = preg_replace('/^[^a-z]+|[^a-z]+$/', '', $base) ?? '';

        $forms = [self::lettersOnly($base)];

        foreach (['i', 'l'] as $reading)
        {
            $leet = self::LEET_COMMON + array_fill_keys(self::LEET_AMBIGUOUS, $reading);
            $forms[] = self::lettersOnly(strtr($base, $leet));
            $forms[] = self::lettersOnly(strtr($inner, $leet));
        }

        return array_values(array_unique(array_filter($forms, static fn (string $f): bool => $f !== '')));
    }

    private static function lettersOnly(string $text): string
    {
        return preg_replace('/[^a-z]+/', '', $text) ?? '';
    }

    /**
     * Les racines passent par la même normalisation que les mots de passe, sans leet :
     * le fichier peut garder l'orthographe naturelle (« Jet d'eau », « Pâquis »).
     *
     * @return array<string, true>
     */
    private static function roots(): array
    {
        if (self::$roots !== null)
        {
            return self::$roots;
        }

        self::$roots = [];

        foreach (self::readList(self::CONTEXT_FILE) as $line)
        {
            $root = self::lettersOnly(strtr(mb_strtolower($line), self::ACCENTS));

            if (strlen($root) >= self::ROOT_MIN_LENGTH)
            {
                self::$roots[$root] = true;
            }
        }

        return self::$roots;
    }

    /**
     * @return string[]
     */
    private static function leakedList(): array
    {
        return self::$leaked ??= self::readList(self::LEAKED_FILE);
    }

    /**
     * @return string[] lignes du fichier, sans les vides ni les commentaires
     */
    private static function readList(string $file): array
    {
        $lines = @file(self::filePath($file), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // fichier absent ou illisible : les autres règles s'appliquent toujours, une
        // inscription ne doit pas échouer là-dessus
        if ($lines === false)
        {
            error_log("PasswordPolicy : " . self::filePath($file) . " illisible");
            $lines = [];
        }

        return array_values(array_filter(
            $lines,
            static fn (string $line): bool => $line !== '' && !str_starts_with($line, '#')
        ));
    }
}
