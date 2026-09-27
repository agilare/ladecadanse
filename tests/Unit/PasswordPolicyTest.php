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

    /**
     * Les mots du contexte (resources/bad_p_context.txt) ne figurent dans aucune fuite
     * générale : c'est par leurs dérivés qu'on les refuse.
     *
     * @return array<string, array{string}>
     */
    public static function contextDerivatives(): array
    {
        return [
            'site name' => ['ladecadanse'],
            'case and year' => ['LaDecadanse2026'],
            'digits and symbol' => ['Servette1890!'],
            'leading digits' => ['1204geneve'],
            'accents' => ['Genève1204'],
            'leet inside' => ['p@quis2024'],
            'leet everywhere, trailing 3 included' => ['l4dec4dans3'],
            'leet and surrounding digits' => ['S3rv3tte1890'],
            'separators' => ['la-decadanse'],
            'spaces' => ['La Décadanse 26'],
            'root with apostrophe' => ["jet-d'eau-2026"],
            'two roots' => ['GeneveCarouge'],
            'two roots and digits' => ['servette_geneve_1890'],
            'repeated root' => ['genevegeneve'],
        ];
    }

    /**
     * @dataProvider contextDerivatives
     */
    public function testContextDerivativeIsRejected(string $password): void
    {
        $this->assertTrue(PasswordPolicy::isContextDerivative($password), $password);
        $this->assertTrue(PasswordPolicy::estRefuse($password), $password);
    }

    /**
     * Un dérivé qui passe la longueur et la règle du chiffre n'est arrêté que par le
     * contexte : c'est bien son message que le formulaire affiche.
     */
    public function testFormRejectsContextDerivative(): void
    {
        $errors = PasswordPolicy::erreurs('Servette1890!', 'Servette1890!');

        $this->assertArrayHasKey('motdepasse', $errors);
        $this->assertStringContainsString('trop facile à deviner', $errors['motdepasse']);
    }

    /**
     * Seul le mot de passe entier est comparé : contenir une racine ne suffit pas, sinon
     * toute phrase de passe qui nomme un lieu de la région serait refusée.
     *
     * @return array<string, array{string}>
     */
    public static function passwordsContainingARoot(): array
    {
        return [
            'sentence' => ['automne-a-geneve-sous-la-pluie'],
            'root and ordinary word' => ['servette-brouette'],
            'three roots' => ['geneve-carouge-paquis'],
            'root plus one letter' => ['genevex2024'],
        ];
    }

    /**
     * @dataProvider passwordsContainingARoot
     */
    public function testPasswordContainingARootIsAccepted(string $password): void
    {
        $this->assertFalse(PasswordPolicy::isContextDerivative($password), $password);
        $this->assertFalse(PasswordPolicy::estRefuse($password), $password);
    }

    /**
     * Les trois voies de normalisation, sur un cas où chacune est nécessaire.
     */
    public function testNormalizedForms(): void
    {
        // sans leet : 1890 disparaît au lieu de devenir des lettres
        $this->assertContains('servette', PasswordPolicy::normalizedForms('Servette1890'));
        // leet partout : le 3 final est un e
        $this->assertContains('ladecadanse', PasswordPolicy::normalizedForms('l4dec4dans3'));
        // bords retirés avant le leet : sinon 2024 laisserait « oa » derrière « paquis »
        $this->assertContains('paquis', PasswordPolicy::normalizedForms('p@quis2024'));
        // 1 se lit i ou l
        $forms = PasswordPolicy::normalizedForms('vi11e');
        $this->assertContains('viiie', $forms);
        $this->assertContains('ville', $forms);
    }

    /**
     * Un mot de passe sans aucune lettre ne produit pas de forme vide, qui ne doit
     * correspondre à rien.
     */
    public function testPasswordWithoutLettersYieldsNoEmptyForm(): void
    {
        $this->assertNotContains('', PasswordPolicy::normalizedForms('2024-1890-%%'));
        $this->assertFalse(PasswordPolicy::isContextDerivative('2024-1890-%%'));
    }
}
