<?php

namespace Kematjaya\CrudMakerBundle\Api\Util;

use Symfony\Component\Yaml\Yaml;

/**
 * Adds or completes an item in an EXISTING `config/permissions/default.yaml` (the
 * `kematjaya/access-control-bundle` menu/permission manifest — see
 * `PermissionManifestLoader`), via targeted line/indent-based text insertion rather than a
 * full {@see Yaml::parse()}+{@see Yaml::dump()} round trip, so hand-written formatting and
 * ordering elsewhere in the file survive untouched — same reasoning as
 * {@see RateLimiterConfigWriter}.
 *
 * Indentation (section marker, `items:` key, item marker, item field) is DETECTED from the
 * file's existing content (see {@see detectIndentation()}) rather than hardcoded — YAML block
 * sequences allow the `- ` marker to sit at the same column as its parent key or indented
 * further, and both are equally valid, so a fixed assumption breaks as soon as a real file uses
 * a different (but still valid) indent width than whatever was assumed.
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
 * @package Kematjaya\CrudMakerBundle\Api\Util
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
        $indent = $this->detectIndentation($lines);

        $sectionPad = str_repeat(' ', $indent['section']);
        $itemsPad = str_repeat(' ', $indent['itemsKey']);

        $sectionIdx = null;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^'.$sectionPad.'-\s*name:\s*'.preg_quote($sectionName, '/').'\s*$/', $line)) {
                $sectionIdx = $i;
                break;
            }
        }

        $block = $this->renderItemBlock($key, $label, $href, $icon, $defaultRoles, $actions, $indent['item'], $indent['itemField']);

        if (null === $sectionIdx) {
            $newSection = array_merge([$sectionPad.'- name: '.$sectionName, $itemsPad.'items:'], $block);

            while ([] !== $lines && '' === trim((string) end($lines))) {
                array_pop($lines);
            }
            array_push($lines, ...$newSection);

            return implode("\n", $lines)."\n";
        }

        $itemsIdx = null;
        for ($i = $sectionIdx + 1; $i < count($lines); ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            $lineIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
            if ($lineIndent <= $indent['section']) {
                break; // ran into the next section (or past it) without finding "items:" in this one
            }
            if (preg_match('/^'.$itemsPad.'items:\s*$/', $lines[$i])) {
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
     * Detects the four indent widths this file actually uses (section marker, `items:` key,
     * item marker, item field), by measuring real lines rather than assuming a fixed scheme —
     * both `- ` at the same column as its parent key and `- ` indented further are valid YAML,
     * and different files legitimately pick different widths. Falls back to a conventional
     * 2-space-per-level scheme only when the file has no existing section/item to learn from
     * (e.g. a freshly-scaffolded, still-empty manifest).
     *
     * @param list<string> $lines
     *
     * @return array{section: int, itemsKey: int, item: int, itemField: int}
     */
    private function detectIndentation(array $lines): array
    {
        $default = ['section' => 2, 'itemsKey' => 4, 'item' => 6, 'itemField' => 8];

        $sectionsIdx = null;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^sections:\s*$/', $line)) {
                $sectionsIdx = $i;
                break;
            }
        }
        if (null === $sectionsIdx) {
            return $default;
        }

        $sectionIndent = null;
        $sectionLineIdx = null;
        for ($i = $sectionsIdx + 1; $i < count($lines); ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            if (1 === preg_match('/^(\s*)-\s*name:/', $lines[$i], $m)) {
                $sectionIndent = strlen($m[1]);
                $sectionLineIdx = $i;
            }
            break;
        }
        if (null === $sectionIndent || null === $sectionLineIdx) {
            return $default;
        }

        $itemsKeyIndent = null;
        $itemsLineIdx = null;
        for ($i = $sectionLineIdx + 1; $i < count($lines); ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            $lineIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
            if ($lineIndent <= $sectionIndent) {
                break;
            }
            if (1 === preg_match('/^\s*items:\s*$/', $lines[$i])) {
                $itemsKeyIndent = $lineIndent;
                $itemsLineIdx = $i;
                break;
            }
        }
        if (null === $itemsKeyIndent || null === $itemsLineIdx) {
            return $default;
        }

        $itemIndent = null;
        $itemLineIdx = null;
        for ($i = $itemsLineIdx + 1; $i < count($lines); ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            $lineIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
            if ($lineIndent > $itemsKeyIndent && 1 === preg_match('/^(\s*)-\s*key:/', $lines[$i], $m)) {
                $itemIndent = strlen($m[1]);
                $itemLineIdx = $i;
            }
            break;
        }
        if (null === $itemIndent || null === $itemLineIdx) {
            return array_merge($default, ['section' => $sectionIndent, 'itemsKey' => $itemsKeyIndent]);
        }

        $itemFieldIndent = null;
        for ($i = $itemLineIdx + 1; $i < count($lines); ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            $lineIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
            if ($lineIndent > $itemIndent) {
                $itemFieldIndent = $lineIndent;
            }
            break;
        }

        return [
            'section' => $sectionIndent,
            'itemsKey' => $itemsKeyIndent,
            'item' => $itemIndent,
            'itemField' => $itemFieldIndent ?? $itemIndent + 2,
        ];
    }

    /**
     * @param array<string, string> $missingActions
     */
    private function patchExistingItem(string $sourceCode, string $key, bool $alreadyGated, array $missingActions): string
    {
        $lines = explode("\n", $sourceCode);

        $itemIdx = null;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^\s*-\s*key:\s*'.preg_quote($key, '/').'\s*$/', $line)) {
                $itemIdx = $i;
                break;
            }
        }
        if (null === $itemIdx) {
            throw new \RuntimeException(sprintf('Could not locate permission item "%s" in the manifest source for patching.', $key));
        }
        $itemIndent = strlen($lines[$itemIdx]) - strlen(ltrim($lines[$itemIdx], ' '));

        // The item's own fields (label:, href:, ...) are the next non-blank sibling line —
        // detected rather than assumed, same reasoning as detectIndentation().
        $itemFieldIndent = $itemIndent + 2;
        for ($i = $itemIdx + 1; $i < count($lines); ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            $lineIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
            if ($lineIndent > $itemIndent) {
                $itemFieldIndent = $lineIndent;
            }
            break;
        }
        $fieldPad = str_repeat(' ', $itemFieldIndent);

        $itemEnd = $itemIdx + 1;
        for (; $itemEnd < count($lines); ++$itemEnd) {
            $line = $lines[$itemEnd];
            if ('' === trim($line)) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            if ($indent <= $itemIndent) {
                break; // next item ("- key:") or a shallower/top-level line
            }
        }

        $insertPos = $itemIdx + 1;

        if (!$alreadyGated) {
            array_splice($lines, $insertPos, 0, [$fieldPad.'gated: true']);
            ++$itemEnd;
            ++$insertPos;
        }

        if ([] !== $missingActions) {
            $actionsIdx = null;
            for ($i = $itemIdx; $i < $itemEnd; ++$i) {
                if (1 === preg_match('/^'.$fieldPad.'actions:\s*$/', $lines[$i])) {
                    $actionsIdx = $i;
                    break;
                }
            }

            $actionEntryIndent = $itemFieldIndent + 2;
            if (null !== $actionsIdx) {
                for ($i = $actionsIdx + 1; $i < $itemEnd; ++$i) {
                    if ('' === trim($lines[$i])) {
                        continue;
                    }
                    $lineIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i], ' '));
                    if ($lineIndent > $itemFieldIndent) {
                        $actionEntryIndent = $lineIndent;
                    }
                    break;
                }
            }
            $actionPad = str_repeat(' ', $actionEntryIndent);

            $newActionLines = [];
            foreach ($missingActions as $actionKey => $actionLabel) {
                $newActionLines[] = $actionPad.$actionKey.': '.$actionLabel;
            }

            if (null !== $actionsIdx) {
                array_splice($lines, $actionsIdx + 1, 0, $newActionLines);
            } else {
                array_splice($lines, $insertPos, 0, array_merge([$fieldPad.'actions:'], $newActionLines));
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
    private function renderItemBlock(string $key, string $label, string $href, string $icon, array $defaultRoles, array $actions, int $itemIndent, int $itemFieldIndent): array
    {
        $itemPad = str_repeat(' ', $itemIndent);
        $fieldPad = str_repeat(' ', $itemFieldIndent);
        $actionPad = str_repeat(' ', $itemFieldIndent + 2);

        $lines = [
            $itemPad.'- key: '.$key,
            $fieldPad.'label: '.$label,
            $fieldPad.'href: '.$href,
            $fieldPad.'icon: '.$icon,
            $fieldPad.'gated: true',
        ];

        if ([] !== $defaultRoles) {
            $lines[] = $fieldPad.'defaultRoles: ['.implode(', ', $defaultRoles).']';
        }

        if ([] !== $actions) {
            $lines[] = $fieldPad.'actions:';
            foreach ($actions as $actionKey => $actionLabel) {
                $lines[] = $actionPad.$actionKey.': '.$actionLabel;
            }
        }

        return $lines;
    }
}
