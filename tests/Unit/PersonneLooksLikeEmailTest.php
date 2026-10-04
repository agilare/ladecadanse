<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Personne;

/**
 * Un nom d'utilisateur qui est une adresse ne signe pas les annonces : la signature est le
 * seul endroit public où ce champ paraisse (traitement T1 de 40_Donnees_personnelles.md,
 * dépôt ladecadanse-docs).
 */
final class PersonneLooksLikeEmailTest extends Unit
{
    public function testUneAdresseEstReconnue(): void
    {
        $this->assertTrue(Personne::looksLikeEmail('jean.dupont@example.ch'));
    }

    /**
     * Plus large que FILTER_VALIDATE_EMAIL à dessein : une saisie mal formée reste lisible
     * par un moissonneur, et doit donc être écartée elle aussi.
     */
    public function testUneAdresseMalFormeeEstReconnueAussi(): void
    {
        $this->assertTrue(Personne::looksLikeEmail('jean..dupont@@example.ch'));
        $this->assertFalse(filter_var('jean..dupont@@example.ch', FILTER_VALIDATE_EMAIL) !== false);
    }

    public function testUnNomDUtilisateurOrdinairePasse(): void
    {
        $this->assertFalse(Personne::looksLikeEmail('dj_machin'));
        $this->assertFalse(Personne::looksLikeEmail('Le Petit Bar'));
    }

    /**
     * Le point après l'arobase est ce qui distingue une adresse d'un pseudonyme à la mode
     * des réseaux sociaux, que rien ne justifie d'écarter.
     */
    public function testUnHandleGardeSaSignature(): void
    {
        $this->assertFalse(Personne::looksLikeEmail('@dj_machin'));
        $this->assertFalse(Personne::looksLikeEmail('@lepetitbar'));
    }

    public function testLesEspacesAutourNeTrompentPas(): void
    {
        $this->assertTrue(Personne::looksLikeEmail('  jean@example.ch  '));
    }

    public function testUneChaineVideNEstPasUneAdresse(): void
    {
        $this->assertFalse(Personne::looksLikeEmail(''));
        $this->assertFalse(Personne::looksLikeEmail('   '));
    }
}
