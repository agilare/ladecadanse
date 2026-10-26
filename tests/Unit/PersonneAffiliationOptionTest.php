<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Personne;

/**
 * Valeurs de la liste fusionnée des rattachements (user/edit.php, user/register.php) : le type
 * décide de la table, il n'en reconnaît donc que deux.
 */
final class PersonneAffiliationOptionTest extends Unit
{
    public function testLieuAndOrganisateurAreRead(): void
    {
        $this->assertSame(['type' => 'lieu', 'id' => 42], Personne::parseAffiliationOption('lieu:42'));
        $this->assertSame(['type' => 'orga', 'id' => 17], Personne::parseAffiliationOption('orga:17'));
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->assertNull(Personne::parseAffiliationOption('personne:3'));
        $this->assertNull(Personne::parseAffiliationOption('42'));
    }

    public function testMissingOrNonPositiveIdIsRejected(): void
    {
        $this->assertNull(Personne::parseAffiliationOption('lieu:'));
        $this->assertNull(Personne::parseAffiliationOption('lieu:0'));
        $this->assertNull(Personne::parseAffiliationOption('orga:-5'));
        $this->assertNull(Personne::parseAffiliationOption('orga:abc'));
    }

    public function testNonScalarIsRejected(): void
    {
        $this->assertNull(Personne::parseAffiliationOption(['lieu:42']));
        $this->assertNull(Personne::parseAffiliationOption(null));
    }
}
