<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Util;

use Kematjaya\CrudMakerBundle\Util\RateLimiterConfigWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class RateLimiterConfigWriterTest extends TestCase
{
    private const SOURCE = <<<'YAML'
# see https://symfony.com/doc/current/reference/configuration/framework.html
framework:
    secret: '%env(APP_SECRET)%'
    session: false

when@test:
    framework:
        test: true

YAML;

    public function testAddsRateLimiterBlockWhenRateLimiterKeyIsMissing(): void
    {
        $writer = new RateLimiterConfigWriter();

        $result = $writer->addLimiter(self::SOURCE, 'notes_export_data', 'fixed_window', 5, '1 minute');

        self::assertStringContainsString('# see https://symfony.com/doc/current/reference/configuration/framework.html', $result);
        self::assertStringContainsString("secret: '%env(APP_SECRET)%'", $result);

        $parsed = Yaml::parse($result);
        self::assertSame('fixed_window', $parsed['framework']['rate_limiter']['notes_export_data']['policy']);
        self::assertSame(5, $parsed['framework']['rate_limiter']['notes_export_data']['limit']);
        self::assertSame('1 minute', $parsed['framework']['rate_limiter']['notes_export_data']['interval']);
        self::assertTrue($parsed['when@test']['framework']['test']);
    }

    public function testAddsAnotherLimiterAlongsideAnExistingOne(): void
    {
        $writer = new RateLimiterConfigWriter();

        $withFirst = $writer->addLimiter(self::SOURCE, 'notes_export_data', 'fixed_window', 5, '1 minute');
        $withBoth = $writer->addLimiter($withFirst, 'tasks_export_data', 'fixed_window', 5, '1 minute');

        $parsed = Yaml::parse($withBoth);
        self::assertArrayHasKey('notes_export_data', $parsed['framework']['rate_limiter']);
        self::assertArrayHasKey('tasks_export_data', $parsed['framework']['rate_limiter']);
    }

    public function testIsIdempotentWhenLimiterAlreadyExists(): void
    {
        $writer = new RateLimiterConfigWriter();

        $withLimiter = $writer->addLimiter(self::SOURCE, 'notes_export_data', 'fixed_window', 5, '1 minute');
        $again = $writer->addLimiter($withLimiter, 'notes_export_data', 'fixed_window', 5, '1 minute');

        self::assertSame($withLimiter, $again);
    }

    public function testThrowsWhenFrameworkKeyIsMissing(): void
    {
        $writer = new RateLimiterConfigWriter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/framework/');

        $writer->addLimiter("doctrine:\n    dbal: ~\n", 'notes_export_data', 'fixed_window', 5, '1 minute');
    }
}
