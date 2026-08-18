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
}
