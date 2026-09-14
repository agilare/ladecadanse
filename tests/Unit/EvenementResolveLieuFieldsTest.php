<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Evenement;

/**
 * Couvre la recopie des champs de lieu dans un événement, faite à l'enregistrement des
 * formulaires d'événement (evenement-edit.php) et de la modification en lot (admin/events.php).
 *
 * `lieu.URL` est la seule colonne nullable parmi celles que la méthode lit : sa valeur nulle
 * atteignait DbConnector::sanitize(), dont le paramètre est typé string, et faisait échouer
 * l'enregistrement sur une erreur fatale (log de production du 09.09.2026).
 *
 * Le connecteur est doublé : la méthode ne fait que lire, et la suite unit n'a pas de base.
 */
final class EvenementResolveLieuFieldsTest extends Unit
{
    private const array LIEU = [
        'nom' => 'Temple des Pâquis',
        'adresse' => 'rue de Berne 1',
        'quartier' => 'Pâquis',
        'localite_id' => 44,
        'region' => 'ge',
        'URL' => null,
    ];

    protected function _after(): void
    {
        unset($GLOBALS['connector']);
    }

    /**
     * Faux DbConnector : rend la ligne fournie quelle que soit la requête.
     *
     * @param array<string, mixed> $ligne
     */
    private function connecteurRendant(array $ligne): object
    {
        return new class ($ligne) {
            /** @param array<string, mixed> $ligne */
            public function __construct(private readonly array $ligne)
            {
            }

            public function query(string $sql): string
            {
                return $sql;
            }

            /** @return array<string, mixed> */
            public function fetchArray(mixed $result): array
            {
                return $this->ligne;
            }
        };
    }

    public function testUneUrlNulleDevientUneChaineVide(): void
    {
        $GLOBALS['connector'] = $this->connecteurRendant(self::LIEU);

        [$champs, $lieu_modifie] = Evenement::resolveLieuFields(['idLieu' => 180, 'urlLieu' => '']);

        $this->assertSame('', $champs['urlLieu']);
        $this->assertTrue($lieu_modifie);
    }

    public function testUneUrlRenseigneeEstRecopieeTelleQuelle(): void
    {
        $GLOBALS['connector'] = $this->connecteurRendant(['URL' => 'https://exemple.ch'] + self::LIEU);

        [$champs] = Evenement::resolveLieuFields(['idLieu' => 180, 'urlLieu' => '']);

        $this->assertSame('https://exemple.ch', $champs['urlLieu']);
    }

    public function testLesAutresChampsDuLieuSontRecopies(): void
    {
        $GLOBALS['connector'] = $this->connecteurRendant(self::LIEU);

        [$champs] = Evenement::resolveLieuFields(['idLieu' => 180, 'urlLieu' => '']);

        $this->assertSame('Temple des Pâquis', $champs['nomLieu']);
        $this->assertSame('rue de Berne 1', $champs['adresse']);
        $this->assertSame('Pâquis', $champs['quartier']);
        $this->assertSame(44, $champs['localite_id']);
        $this->assertSame('ge', $champs['region']);
    }
}
