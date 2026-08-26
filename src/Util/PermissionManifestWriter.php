<?php

namespace Kematjaya\CrudMakerBundle\Util;

use Symfony\Component\Yaml\Yaml;

/**
 * Adds or completes an item in an EXISTING `config/permissions/default.yaml` (the
 * `kematjaya/access-control-bundle` menu/permission manifest — see
 * `PermissionManifestLoader`), via targeted line/indent-based text insertion rather than a
 * full {@see Yaml::parse()}+{@see Yaml::dump()} round trip, so hand-written formatting and
 * ordering elsewhere in the file survive untouched — same reasoning as
 * {@see RateLimiterConfigWriter}.
 *
 * Three outcomes, all driven by a read-only {@see Yaml::parse()} lookup of the item by `key`:
 *  - no item with that key exists yet: a brand-new item is appended to the given section
 *    (creating the section itself if it doesn't exist yet either);
 *  - an item exists but is missing `gated: true` and/or some of the required `actions` keys:
 *    only the missing pieces are patched in — existing fields (including custom action
 *    labels for keys that are already present) are left untouched;
 *  - an item exists and is already gated with every required action key present: the source
 *    is returned UNCHANGED (idempotent no-op), not an error.
 *
 * Throws only when the file's structure doesn't match what's expected (no top-level
 * `sections:` key, or a section/item found by {@see Yaml::parse()} can't be relocated in the
 * raw text for editing) — callers fall back to printing the manual paste-in-by-hand
 * instructions in that case, same resilience pattern as {@see EntityAttributeWriter}.
 *
 * @package Kematjaya\CrudMakerBundle\Util
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class PermissionManifestWriter
{
    /**
     * @param list<string>          $defaultRoles
     * @param array<string, string> $actions
     */
    public function addOrUpdateItem(
        string $sourceCode,
        string $sectionName,
        string $key,
        string $label,
        string $href,
        string $icon,
        array $defaultRoles,
        array $actions,
    ): string {
        $parsed = Yaml::parse($sourceCode);
        if (!is_array($parsed) || !isset($parsed['sections']) || !is_array($parsed['sections'])) {
            throw new \RuntimeException('Could not find a top-level "sections:" key in the permission manifest.');
        }

        $existingItem = $this->findParsedItem($parsed['sections'], $key);

        if (null === $existingItem) {
            return $this->insertNewItem($sourceCode, $sectionName, $key, $label, $href, $icon, $defaultRoles, $actions);
        }

        $existingActions = is_array($existingItem['actions'] ?? null) ? $existingItem['actions'] : [];
        $missingActions = array_diff_key($actions, $existingActions);
        $alreadyGated = true === ($existingItem['gated'] ?? false);

        if ($alreadyGated && [] === $missingActions) {
            return $sourceCode;
        }

        return $this->patchExistingItem($sourceCode, $key, $alreadyGated, $missingActions);
    }

    /**
     * @param array<int, mixed> $sections
     *
     * @return array<string, mixed>|null
     */
    private function findParsedItem(array $sections, string $key): ?array
    {
        foreach ($sections as $section) {
            if (!is_array($section) || !isset($section['items']) || !is_array($section['items'])) {
                continue;
            }
            foreach ($section['items'] as $item) {
                if (is_array($item) && ($item['key'] ?? null) === $key) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string>          $defaultRoles
     * @param array<string, string> $actions
     */
    private function insertNewItem(
        string $sourceCode,
        string $sectionName,
        string $key,
        string $label,
        string $href,
        string $icon,
        array $defaultRoles,
        array $actions,
    ): string {
        $lines = explode("\n", $sourceCode);

        $sectionIdx = null;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^  - name:\s*'.preg_quote($sectionName, '/').'\s*$/', $line)) {
                $sectionIdx = $i;
                break;
            }
        }

        $block = $this->renderItemBlock($key, $label, $href, $icon, $defaultRoles, $actions);

        if (null === $sectionIdx) {
            $newSection = array_merge(['  - name: '.$sectionName, '    items:'], $block);

            while ([] !== $lines && '' === trim((string) end($lines))) {
                array_pop($lines);
            }
            array_push($lines, ...$newSection);

            return implode("\n", $lines)."\n";
        }

        $itemsIdx = null;
        for ($i = $sectionIdx + 1; $i < count($lines); ++$i) {
            if (preg_match('/^  - name:/', $lines[$i])) {
                break; // ran into the next section without finding "items:" in this one
            }
            if (preg_match('/^    items:\s*$/', $lines[$i])) {
                $itemsIdx = $i;
                break;
            }
        }

        if (null === $itemsIdx) {
            throw new \RuntimeException(sprintf('Section "%s" has no "items:" key — cannot add the permission item automatically.', $sectionName));
        }

        array_splice($lines, $itemsIdx + 1, 0, $block);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $missingActions
     */
    private function patchExistingItem(string $sourceCode, string $key, bool $alreadyGated, array $missingActions): string
    {
        $lines = explode("\n", $sourceCode);

        $itemIdx = null;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^      - key:\s*'.preg_quote($key, '/').'\s*$/', $line)) {
                $itemIdx = $i;
                break;
            }
        }
        if (null === $itemIdx) {
            throw new \RuntimeException(sprintf('Could not locate permission item "%s" in the manifest source for patching.', $key));
        }

        $itemEnd = $itemIdx + 1;
        for (; $itemEnd < count($lines); ++$itemEnd) {
            $line = $lines[$itemEnd];
            if ('' === trim($line)) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            if ($indent <= 6) {
                break; // next item ("      - key:") or a shallower/top-level line
            }
        }

        $insertPos = $itemIdx + 1;

        if (!$alreadyGated) {
            array_splice($lines, $insertPos, 0, ['        gated: true']);
            ++$itemEnd;
            ++$insertPos;
        }

        if ([] !== $missingActions) {
            $actionsIdx = null;
            for ($i = $itemIdx; $i < $itemEnd; ++$i) {
                if (preg_match('/^        actions:\s*$/', $lines[$i])) {
                    $actionsIdx = $i;
                    break;
                }
            }

            $newActionLines = [];
            foreach ($missingActions as $actionKey => $actionLabel) {
                $newActionLines[] = '          '.$actionKey.': '.$actionLabel;
            }

            if (null !== $actionsIdx) {
                array_splice($lines, $actionsIdx + 1, 0, $newActionLines);
            } else {
                array_splice($lines, $insertPos, 0, array_merge(['        actions:'], $newActionLines));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string>          $defaultRoles
     * @param array<string, string> $actions
     *
     * @return list<string>
     */
    private function renderItemBlock(string $key, string $label, string $href, string $icon, array $defaultRoles, array $actions): array
    {
        $lines = [
            '      - key: '.$key,
            '        label: '.$label,
            '        href: '.$href,
            '        icon: '.$icon,
            '        gated: true',
        ];

        if ([] !== $defaultRoles) {
            $lines[] = '        defaultRoles: ['.implode(', ', $defaultRoles).']';
        }

        if ([] !== $actions) {
            $lines[] = '        actions:';
            foreach ($actions as $actionKey => $actionLabel) {
                $lines[] = '          '.$actionKey.': '.$actionLabel;
            }
        }

        return $lines;
    }
}
