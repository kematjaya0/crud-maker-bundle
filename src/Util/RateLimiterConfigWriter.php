<?php

namespace Kematjaya\CrudMakerBundle\Util;

use Symfony\Component\Yaml\Yaml;

/**
 * Idempotently adds a `framework.rate_limiter.<name>` entry to an EXISTING
 * `config/packages/framework.yaml`, via targeted line/indent-based text insertion rather than
 * a full {@see Yaml::parse()}+{@see Yaml::dump()} round trip — a full round trip would
 * reformat the whole file and strip comments (real-world `framework.yaml` files are full of
 * them, e.g. the `# see https://symfony.com/...` header), the same reasoning that keeps
 * {@see EntityAttributeWriter} on an AST-preserving printer instead of naive regeneration.
 *
 * {@see Yaml::parse()} is still used, read-only, to check whether the limiter already exists
 * (structure-aware, so it isn't fooled by e.g. a limiter name appearing inside a comment) —
 * only the actual edit is done as targeted text insertion.
 *
 * Idempotent: if a limiter with the given name already exists anywhere under
 * `framework.rate_limiter`, the source is returned UNCHANGED rather than throwing — re-running
 * `make:kmj-api-crud` for the same entity (same limiter name, derived from permission-prefix)
 * is a no-op, not an error.
 *
 * Throws only when the file's structure doesn't match what's expected (no top-level
 * `framework:` key) — callers fall back to printing the manual paste-in-by-hand instructions
 * in that case, same resilience pattern as {@see EntityAttributeWriter}.
 *
 * @package Kematjaya\CrudMakerBundle\Util
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class RateLimiterConfigWriter
{
    public function addLimiter(string $sourceCode, string $limiterName, string $policy, int $limit, string $interval): string
    {
        $parsed = Yaml::parse($sourceCode);
        if (!is_array($parsed) || !isset($parsed['framework']) || !is_array($parsed['framework'])) {
            throw new \RuntimeException('Could not find a top-level "framework:" key in framework.yaml.');
        }

        $existingLimiters = $parsed['framework']['rate_limiter'] ?? [];
        if (is_array($existingLimiters) && array_key_exists($limiterName, $existingLimiters)) {
            return $sourceCode;
        }

        $lines = explode("\n", $sourceCode);

        $frameworkIdx = null;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^framework:\s*$/', $line)) {
                $frameworkIdx = $i;
                break;
            }
        }
        if (null === $frameworkIdx) {
            throw new \RuntimeException('Could not locate the top-level "framework:" line in framework.yaml.');
        }

        // Detect framework's direct-child indent from the first indented, non-blank line
        // after "framework:"; the framework block ends at the first later line indented
        // less than that (a new top-level key, or EOF).
        $childIndent = null;
        $blockEnd = count($lines);
        for ($i = $frameworkIdx + 1; $i < count($lines); ++$i) {
            $line = $lines[$i];
            if ('' === trim($line)) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            if (null === $childIndent) {
                if (0 === $indent) {
                    break; // "framework:" has no children at all
                }
                $childIndent = $indent;
            } elseif ($indent < $childIndent) {
                $blockEnd = $i;
                break;
            }
        }
        $childIndent ??= 4;

        $indent1 = str_repeat(' ', $childIndent);
        $indent2 = str_repeat(' ', $childIndent * 2);
        $indent3 = str_repeat(' ', $childIndent * 3);

        $rateLimiterIdx = null;
        for ($i = $frameworkIdx + 1; $i < $blockEnd; ++$i) {
            if (preg_match('/^'.$indent1.'rate_limiter:\s*$/', $lines[$i])) {
                $rateLimiterIdx = $i;
                break;
            }
        }

        $newLimiterBlock = [
            $indent2.$limiterName.':',
            $indent3.'policy: '.$policy,
            $indent3.'limit: '.$limit,
            $indent3.'interval: '.$this->quoteScalar($interval),
        ];

        if (null !== $rateLimiterIdx) {
            array_splice($lines, $rateLimiterIdx + 1, 0, $newLimiterBlock);
        } else {
            array_splice($lines, $frameworkIdx + 1, 0, array_merge([$indent1.'rate_limiter:'], $newLimiterBlock));
        }

        return implode("\n", $lines);
    }

    private function quoteScalar(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
