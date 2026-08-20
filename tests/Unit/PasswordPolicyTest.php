<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\PasswordPolicy;

/**
 * Règles du mot de passe et liste des mots de passe refusés (resources/bad_p.txt),
 * partagées par les trois formulaires qui en fixent un.
 */
final class PasswordPolicyTest extends Unit
{
    public function testUnMotDePasseTropCourantEstRefuse(): void
    {
        // « marseille13 » passe la longueur et la règle du chiffre : sans la liste,
        // le site l'accepterait
        $this->assertTrue(PasswordPolicy::estRefuse('marseille13'));
        $this->assertArrayHasKey('motdepasse', PasswordPolicy::erreurs('marseille13', 'marseille13'));
    }

    /**
     * La comparaison est stricte, et c'est voulu : la liste vient de fuites réelles et
     * porte ses propres variantes de casse (« marseille13 » et « Marseille13 » y sont
     * tous deux). Une variante absente n'est pas refusée par cette règle.
     */
    public function testLaCasseEstSignificative(): void
    {
        $this->assertTrue(PasswordPolicy::estRefuse('Marseille13'));
        $this->assertFalse(PasswordPolicy::estRefuse('MaRsEiLlE13'));
    }

    /**
     * L'en-tête d'attribution du fichier ne doit pas se retrouver dans la liste.
     */
    public function testLesLignesDeCommentaireNeSontPasDesMotsDePasse(): void
    {
        $this->assertFalse(PasswordPolicy::estRefuse('#'));
        $this->assertFalse(PasswordPolicy::estRefuse('# Mots de passe refusés à l\'inscription et au changement de mot de passe.'));
    }

    public function testLongueurHorsBornes(): void
    {
        $this->assertArrayHasKey('motdepasse', PasswordPolicy::erreurs('court1', 'court1'));
        $this->assertArrayHasKey('motdepasse', PasswordPolicy::erreurs('', ''));
        $this->assertArrayHasKey(
            'motdepasse',
            PasswordPolicy::erreurs(str_repeat('a1', 51), str_repeat('a1', 51))
        );
    }

    public function testChiffreObligatoire(): void
    {
        $this->assertArrayHasKey('motdepasse', PasswordPolicy::erreurs('brouettenuage', 'brouettenuage'));
    }

    public function testConfirmationDifferente(): void
    {
        $erreurs = PasswordPolicy::erreurs('Kf7-brouette-nuage', 'Kf7-brouette-nuages');

        $this->assertArrayHasKey('motdepasse_inegaux', $erreurs);
        $this->assertArrayNotHasKey('motdepasse', $erreurs);
    }

    public function testUnMotDePasseValideNeProduitAucuneErreur(): void
    {
        $this->assertSame([], PasswordPolicy::erreurs('Kf7-brouette-nuage', 'Kf7-brouette-nuage'));
    }
}
