<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\FeatureFlag;
use Ladecadanse\UserLevel;

/**
 * Drapeaux à trois états, dont la préversion réservée aux administrateurs.
 *
 * Les constantes de test sont définies ici plutôt que dans l'amorce de suite :
 * une constante ne se redéfinit pas, et chaque état a besoin de la sienne.
 */
final class FeatureFlagTest extends Unit
{
    protected function _before(): void
    {
        if (!defined('TEST_FLAG_FAUX'))
        {
            define('TEST_FLAG_FAUX', false);
            define('TEST_FLAG_VRAI', true);
            define('TEST_FLAG_PREVIEW', FeatureFlag::PREVIEW);
        }

        unset($_SESSION['Sgroupe']);
    }

    protected function _after(): void
    {
        unset($_SESSION['Sgroupe']);
    }

    public function testConstanteAbsenteVautDesactive(): void
    {
        // le drapeau manque des app/env.php antérieurs à la fonctionnalité :
        // son absence ne doit jamais l'activer
        $this->assertFalse(FeatureFlag::estActive('DRAPEAU_QUI_NEXISTE_PAS'));
        $this->assertFalse(FeatureFlag::estEnPreview('DRAPEAU_QUI_NEXISTE_PAS'));
    }

    public function testFauxResteFermeMemePourUnAdministrateur(): void
    {
        $_SESSION['Sgroupe'] = UserLevel::SUPERADMIN;

        $this->assertFalse(FeatureFlag::estActive('TEST_FLAG_FAUX'));
    }

    public function testVraiOuvreATousYComprisSansSession(): void
    {
        $this->assertTrue(FeatureFlag::estActive('TEST_FLAG_VRAI'));
        $this->assertTrue(FeatureFlag::estOuverteATous('TEST_FLAG_VRAI'));
        $this->assertFalse(FeatureFlag::estEnPreview('TEST_FLAG_VRAI'));
    }

    public function testPreviewEstFermeeAuVisiteurAnonyme(): void
    {
        $this->assertFalse(FeatureFlag::estActive('TEST_FLAG_PREVIEW'));
        $this->assertFalse(FeatureFlag::estEnPreview('TEST_FLAG_PREVIEW'));
    }

    public function testPreviewEstFermeeAUnMembre(): void
    {
        $_SESSION['Sgroupe'] = UserLevel::MEMBER;

        $this->assertFalse(FeatureFlag::estActive('TEST_FLAG_PREVIEW'));
    }

    public function testPreviewEstFermeeAUnAuteur(): void
    {
        // la limite est ADMIN : un auteur, qui publie pourtant des événements,
        // ne doit pas voir la fonctionnalité
        $_SESSION['Sgroupe'] = UserLevel::AUTHOR;

        $this->assertFalse(FeatureFlag::estActive('TEST_FLAG_PREVIEW'));
    }

    public function testPreviewEstOuverteAUnAdministrateur(): void
    {
        $_SESSION['Sgroupe'] = UserLevel::ADMIN;

        $this->assertTrue(FeatureFlag::estActive('TEST_FLAG_PREVIEW'));
        $this->assertTrue(FeatureFlag::estEnPreview('TEST_FLAG_PREVIEW'));
    }

    public function testPreviewEstOuverteAUnSuperadministrateur(): void
    {
        // les niveaux décroissent avec les privilèges : SUPERADMIN vaut 1
        $_SESSION['Sgroupe'] = UserLevel::SUPERADMIN;

        $this->assertTrue(FeatureFlag::estActive('TEST_FLAG_PREVIEW'));
    }

    public function testPreviewNestPasOuverteATous(): void
    {
        $_SESSION['Sgroupe'] = UserLevel::SUPERADMIN;

        // estOuverteATous() ignore l'utilisateur : c'est la question que pose un
        // script de maintenance, pour qui « l'utilisateur courant » n'existe pas
        $this->assertFalse(FeatureFlag::estOuverteATous('TEST_FLAG_PREVIEW'));
    }
}
