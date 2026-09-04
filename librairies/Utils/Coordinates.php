<?php

declare(strict_types=1);

namespace Ladecadanse\Utils;

/**
 * Latitude et longitude d'un lieu, de la saisie du formulaire jusqu'à la colonne.
 *
 * Les règles qui les gouvernent vivaient en trois endroits : la virgule décimale était
 * retirée par LieuEdition, les bornes vérifiées par sa verification(), et le « 0 en base
 * veut dire pas de coordonnées » par une closure du gabarit. Le plan n'étant affiché que
 * si les deux sont connues, elles ne valent que par paire : d'où un seul objet plutôt que
 * deux chaînes voyageant côte à côte.
 *
 * La saisie est conservée telle qu'elle a été normalisée, et non convertie en float : un
 * ré-affichage après erreur doit remontrer ce qui a été tapé, fût-ce « quarante-six ».
 */
final class Coordinates
{
    /** Latitudes hors de ±90 et longitudes hors de ±180 ne désignent aucun point du globe. */
    private const float LAT_MAX = 90.0;
    private const float LNG_MAX = 180.0;

    private function __construct(
        private readonly string $lat,
        private readonly string $lng,
    )
    {
    }

    /**
     * Coordonnées telles que le formulaire les a postées.
     */
    public static function fromInput(mixed $lat, mixed $lng): self
    {
        return new self(self::normaliser($lat), self::normaliser($lng));
    }

    /**
     * Coordonnées telles que la base les rend.
     *
     * La colonne a longtemps été NOT NULL DEFAULT 0 : un lieu sans coordonnées y porte
     * donc 0.0000000, qu'il faut lire comme « pas de coordonnées » et non comme un point
     * au large du golfe de Guinée. NULL dit désormais la même chose, sans l'ambiguïté.
     */
    public static function fromDatabase(mixed $lat, mixed $lng): self
    {
        return new self(self::depuisColonne($lat), self::depuisColonne($lng));
    }

    /**
     * Valeur à réafficher dans le champ, y compris une saisie invalide.
     */
    public function latSaisie(): string
    {
        return $this->lat;
    }

    public function lngSaisie(): string
    {
        return $this->lng;
    }

    public function estVide(): bool
    {
        return $this->lat === '' && $this->lng === '';
    }

    /**
     * Valeur à écrire dans la colonne : NULL quand le lieu n'a pas de coordonnées.
     *
     * N'a de sens qu'une fois erreurs() vide ; sur une saisie non numérique la méthode
     * rend NULL plutôt que d'écrire un 0 qui se ferait passer pour un point réel.
     */
    public function latPourBase(): ?float
    {
        return is_numeric($this->lat) ? (float) $this->lat : null;
    }

    public function lngPourBase(): ?float
    {
        return is_numeric($this->lng) ? (float) $this->lng : null;
    }

    /**
     * Messages d'erreur par champ, vides si la paire est acceptable.
     *
     * @return array<string, string> clés 'lat' et/ou 'lng'
     */
    public function erreurs(): array
    {
        $erreurs = [];

        // Les deux ensemble ou aucune des deux : le plan n'est affiché que si les deux
        // sont renseignées, une seule ne servirait donc à rien.
        if (($this->lat === '') !== ($this->lng === ''))
        {
            $erreurs[$this->lat === '' ? 'lat' : 'lng'] = "Veuillez indiquer la latitude et la longitude, ou laisser les deux champs vides";
        }

        if ($this->lat !== '' && (!is_numeric($this->lat) || abs((float) $this->lat) > self::LAT_MAX))
        {
            $erreurs['lat'] = "La latitude doit être un nombre entre -90 et 90 (ex. 46.2043907)";
        }

        if ($this->lng !== '' && (!is_numeric($this->lng) || abs((float) $this->lng) > self::LNG_MAX))
        {
            $erreurs['lng'] = "La longitude doit être un nombre entre -180 et 180 (ex. 6.1431577)";
        }

        return $erreurs;
    }

    /**
     * Espaces superflus et virgule décimale, que produisent les pavés numériques de la
     * plupart des claviers européens.
     */
    private static function normaliser(mixed $valeur): string
    {
        return str_replace(',', '.', trim(is_scalar($valeur) ? (string) $valeur : ''));
    }

    /**
     * Une colonne vide, nulle ou à zéro devient une chaîne vide ; le reste est ramené à
     * sa forme la plus courte — DECIMAL(10,7) rend « 46.2043907 » avec ses zéros de queue.
     */
    private static function depuisColonne(mixed $valeur): string
    {
        $normalisee = self::normaliser($valeur);

        if (!is_numeric($normalisee) || (float) $normalisee === 0.0)
        {
            return '';
        }

        return (string) (float) $normalisee;
    }
}
