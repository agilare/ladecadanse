<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Ladecadanse\Utils\QueryParamValidator;

/**
 * Couvre le validateur de paramètres d'URL extrait de Validateur (issue #153).
 * Le comportement doit rester identique à l'ancienne méthode statique, car
 * elle est appelée dans une trentaine de pages.
 */
final class QueryParamValidatorTest extends Unit
{
    public function testIntAcceptsNumericAndReturnsValue(): void
    {
        $this->assertSame('5', QueryParamValidator::validateUrlQueryValue('5', 'int', 1));
    }

    public function testIntRejectsNonNumeric(): void
    {
        $this->expectException(\Exception::class);
        QueryParamValidator::validateUrlQueryValue('abc', 'int', 1);
    }

    public function testRequiredEmptyThrows(): void
    {
        $this->expectException(\Exception::class);
        QueryParamValidator::validateUrlQueryValue('', 'int', 1);
    }

    public function testOptionalEmptyReturnsNull(): void
    {
        $this->assertNull(QueryParamValidator::validateUrlQueryValue('', 'int', 0));
    }

    public function testEmptyWithDefaultReturnsDefault(): void
    {
        $this->assertSame('ajouter', QueryParamValidator::validateUrlQueryValue('', 'enum', 'ajouter', ['coller']));
    }

    public function testEnumAcceptsListedValue(): void
    {
        $this->assertSame('coller', QueryParamValidator::validateUrlQueryValue('coller', 'enum', 1, ['coller']));
    }

    public function testEnumRejectsUnlistedValue(): void
    {
        $this->expectException(\Exception::class);
        QueryParamValidator::validateUrlQueryValue('x', 'enum', 1, ['coller']);
    }

    public function testDateAcceptsIsoFormat(): void
    {
        $this->assertSame('2026-08-18', QueryParamValidator::validateUrlQueryValue('2026-08-18', 'date', 1));
    }

    public function testDateRejectsOtherFormat(): void
    {
        $this->expectException(\Exception::class);
        QueryParamValidator::validateUrlQueryValue('2026/08/18', 'date', 1);
    }

    public function testAlphaNumericAcceptsWordChars(): void
    {
        $this->assertSame('abc123', QueryParamValidator::validateUrlQueryValue('abc123', 'alpha_numeric', 1));
    }

    public function testAlphaNumericRejectsPunctuation(): void
    {
        $this->expectException(\Exception::class);
        QueryParamValidator::validateUrlQueryValue('abc-123', 'alpha_numeric', 1);
    }

    public function testTrimsWhitespace(): void
    {
        $this->assertSame('7', QueryParamValidator::validateUrlQueryValue(' 7 ', 'int', 1));
    }

    public function testIsAcceptedReturnsTrueForValid(): void
    {
        $this->assertTrue(QueryParamValidator::isAcceptedUrlQueryValue('5', 'int'));
    }

    public function testIsAcceptedReturnsFalseForInvalid(): void
    {
        $this->assertFalse(QueryParamValidator::isAcceptedUrlQueryValue('abc', 'int'));
    }

    public function testIsAcceptedEnum(): void
    {
        $this->assertTrue(QueryParamValidator::isAcceptedUrlQueryValue('faux', 'enum', ['faux', 'email_ambigu']));
        $this->assertFalse(QueryParamValidator::isAcceptedUrlQueryValue('autre', 'enum', ['faux', 'email_ambigu']));
    }

    public function testPageFromQueryReturnsInt(): void
    {
        $this->assertSame(3, QueryParamValidator::pageFromQuery('3'));
    }

    public function testPageFromQueryFallsBackOnGarbage(): void
    {
        // url abîmée par un bot : "?page=3&region=vd" recopié en "?page=3&reg;ion=vd"
        $this->assertSame(1, QueryParamValidator::pageFromQuery("3\u{00AE}ion=vd"));
        $this->assertSame(1, QueryParamValidator::pageFromQuery(''));
        $this->assertSame(1, QueryParamValidator::pageFromQuery(null));
        $this->assertSame(1, QueryParamValidator::pageFromQuery(['3']));
    }

    public function testPageFromQueryClampsToOne(): void
    {
        $this->assertSame(1, QueryParamValidator::pageFromQuery('0'));
        $this->assertSame(1, QueryParamValidator::pageFromQuery('-4'));
    }

    public function testPageFromQueryUsesGivenDefault(): void
    {
        $this->assertSame(12, QueryParamValidator::pageFromQuery('', 12));
        $this->assertSame(12, QueryParamValidator::pageFromQuery('abc', 12));
        $this->assertSame(2, QueryParamValidator::pageFromQuery('2', 12));
    }

    /** @var list<string> */
    private const array ACTIONS = ['ajouter', 'insert', 'editer', 'update'];

    public function testEnumFromQueryAcceptsAListedValue(): void
    {
        $this->assertSame('editer', QueryParamValidator::enumFromQuery('editer', self::ACTIONS, 'ajouter'));
        $this->assertSame('editer', QueryParamValidator::enumFromQuery(' editer ', self::ACTIONS, 'ajouter'));
    }

    /**
     * Une url qui ne précise rien retombe sur le défaut ; une url qui précise n'importe
     * quoi rend null, à l'appelant de répondre 400 plutôt que de laisser filer une
     * exception en 500.
     */
    public function testEnumFromQuerySeparatesTheMissingFromTheUnknown(): void
    {
        $this->assertSame('ajouter', QueryParamValidator::enumFromQuery(null, self::ACTIONS, 'ajouter'));
        $this->assertSame('ajouter', QueryParamValidator::enumFromQuery('', self::ACTIONS, 'ajouter'));
        $this->assertSame('ajouter', QueryParamValidator::enumFromQuery('   ', self::ACTIONS, 'ajouter'));

        $this->assertNull(QueryParamValidator::enumFromQuery('editerr', self::ACTIONS, 'ajouter'));
        $this->assertNull(QueryParamValidator::enumFromQuery('DROP TABLE lieu', self::ACTIONS, 'ajouter'));
    }

    /** La comparaison est stricte : « 0 » ne vaut aucune des valeurs attendues. */
    public function testEnumFromQueryComparesStrictly(): void
    {
        $this->assertNull(QueryParamValidator::enumFromQuery('0', self::ACTIONS, 'ajouter'));
        $this->assertSame('EDITER', QueryParamValidator::enumFromQuery('EDITER', ['EDITER'], null));
        $this->assertNull(QueryParamValidator::enumFromQuery('EDITER', self::ACTIONS, null));
    }

    /** « ?action[]=x » ne doit pas déclencher de conversion de tableau en chaîne. */
    public function testEnumFromQueryIgnoresANonScalarValue(): void
    {
        $this->assertSame('ajouter', QueryParamValidator::enumFromQuery(['editer'], self::ACTIONS, 'ajouter'));
    }
}
