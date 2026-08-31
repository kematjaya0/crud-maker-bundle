<?php

namespace Kematjaya\CrudMakerBundle\Twig\Formatter;

use Symfony\Component\Process\Process;

/**
 * Runs a generated file through PHP-CS-Fixer, when it's available in the host project,
 * so template whitespace quirks never leak into the generated code. Silently does
 * nothing when the binary can't be found, so it's safe to call unconditionally.
 *
 * @package App\Maker\Formatter
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class CodeFormatter
{
    private const RULES = '{"@PSR12":true,"array_syntax":{"syntax":"short"},"trailing_comma_in_multiline":true}';

    public function __construct(private string $projectDir)
    {
    }

    public function format(string $absoluteFilePath): void
    {
        $binary = $this->locateBinary();
        if (null === $binary || !is_file($absoluteFilePath)) {
            return;
        }

        $process = new Process([$binary, 'fix', $absoluteFilePath, '--rules=' . self::RULES, '--using-cache=no', '--quiet']);
        $process->run();
    }

    private function locateBinary(): ?string
    {
        $candidate = $this->projectDir . '/vendor/bin/php-cs-fixer';

        return is_file($candidate) ? $candidate : null;
    }
}
