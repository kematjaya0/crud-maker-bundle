<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Util;

use Kematjaya\CrudMakerBundle\Api\Util\PermissionManifestWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class PermissionManifestWriterTest extends TestCase
{
    private const SOURCE = <<<'YAML'
sections:
  - name: Overview
    items:
      - key: dashboard
        label: Dashboard
        href: /dashboard
        icon: bi-grid-1x2-fill

  - name: Administration
    items:
      - key: admin
        label: Admin
        href: /dashboard/admin
        icon: bi-shield-lock
        roles: [ROLE_ADMIN]

YAML;

    private const ACTIONS = [
        'create' => 'Create Note',
        'edit' => 'Edit Note',
        'delete' => 'Delete Note',
        'bulk_delete' => 'Bulk Delete',
        'export_selected' => 'Export Selected',
        'export_all' => 'Export All',
    ];

    public function testCreatesTargetSectionAndItemWhenNeitherExist(): void
    {
        $writer = new PermissionManifestWriter();

        $result = $writer->addOrUpdateItem(self::SOURCE, 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);

        // untouched pre-existing sections
        self::assertStringContainsString('key: dashboard', $result);
        self::assertStringContainsString('key: admin', $result);

        $parsed = Yaml::parse($result);
        $master = null;
        foreach ($parsed['sections'] as $section) {
            if ('Master' === $section['name']) {
                $master = $section;
            }
        }
        self::assertNotNull($master, 'Master section should have been created');

        $item = $master['items'][0];
        self::assertSame('notes', $item['key']);
        self::assertSame('Notes', $item['label']);
        self::assertSame('/dashboard/notes', $item['href']);
        self::assertSame('bi-journal-text', $item['icon']);
        self::assertTrue($item['gated']);
        self::assertSame(['ROLE_USER'], $item['defaultRoles']);
        self::assertSame(self::ACTIONS, $item['actions']);
    }

    public function testAddsItemToExistingTargetSectionWithoutDuplicatingSection(): void
    {
        $writer = new PermissionManifestWriter();

        $withMaster = $writer->addOrUpdateItem(self::SOURCE, 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);
        $withBoth = $writer->addOrUpdateItem($withMaster, 'Master', 'tasks', 'Tasks', '/dashboard/tasks', 'bi-list-task', ['ROLE_USER'], self::ACTIONS);

        $parsed = Yaml::parse($withBoth);
        $masterSections = array_values(array_filter($parsed['sections'], static fn (array $s) => 'Master' === $s['name']));
        self::assertCount(1, $masterSections, 'Master section must not be duplicated on a second insert');
        self::assertCount(2, $masterSections[0]['items']);
    }

    public function testPatchesExistingUngatedItemWithoutTouchingOtherFields(): void
    {
        $source = <<<'YAML'
sections:
  - name: Master
    items:
      - key: notes
        label: Notes
        href: /dashboard/notes
        icon: bi-journal-text

YAML;

        $writer = new PermissionManifestWriter();
        $result = $writer->addOrUpdateItem($source, 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);

        $parsed = Yaml::parse($result);
        $item = $parsed['sections'][0]['items'][0];
        self::assertTrue($item['gated']);
        self::assertSame(self::ACTIONS, $item['actions']);
        // pre-existing fields survive untouched
        self::assertSame('/dashboard/notes', $item['href']);
        self::assertSame('bi-journal-text', $item['icon']);
    }

    public function testCompletesMissingActionsWithoutOverwritingCustomLabels(): void
    {
        $source = <<<'YAML'
sections:
  - name: Master
    items:
      - key: notes
        label: Notes
        href: /dashboard/notes
        icon: bi-journal-text
        gated: true
        actions:
          create: Buat Catatan

YAML;

        $writer = new PermissionManifestWriter();
        $result = $writer->addOrUpdateItem($source, 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);

        $parsed = Yaml::parse($result);
        $actions = $parsed['sections'][0]['items'][0]['actions'];
        self::assertSame('Buat Catatan', $actions['create'], 'existing custom label must not be overwritten');
        self::assertSame('Edit Note', $actions['edit']);
        self::assertSame('Export All', $actions['export_all']);
    }

    public function testIsIdempotentWhenItemAlreadyGatedAndComplete(): void
    {
        $writer = new PermissionManifestWriter();

        $written = $writer->addOrUpdateItem(self::SOURCE, 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);
        $again = $writer->addOrUpdateItem($written, 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);

        self::assertSame($written, $again);
    }

    public function testMatchesFileIndentationDeeperThanTheDefaultTwoSpaceScheme(): void
    {
        // Some hand-written manifests indent more generously than the writer's own default
        // (section marker at 4, "items:" at 6, item marker at 10, item fields at 12) — the
        // writer must match whatever the file actually uses, not assume its own convention,
        // or the inserted section ends up misaligned with its siblings and breaks the YAML.
        $source = <<<'YAML'
sections:
    - name: Overview
      items:
          - key: dashboard
            label: Dashboard
            href: /dashboard
            icon: dashboard

    - name: Administration
      items:
          - key: access_control
            label: Access Control
            href: /dashboard/admin/access-control
            icon: bi-grid-3x3
            roles: [ROLE_ADMIN]
            gated: true
            defaultRoles: [ROLE_USER]

YAML;

        $writer = new PermissionManifestWriter();
        $result = $writer->addOrUpdateItem($source, 'Master', 'category', 'Categories', '/dashboard/category', 'bi-list', ['ROLE_USER'], self::ACTIONS);

        // must stay parseable — a mismatched indent breaks the "Master" section's sibling
        // relationship with "Overview"/"Administration" and makes the whole file invalid YAML.
        $parsed = Yaml::parse($result);
        self::assertSame(['Overview', 'Administration', 'Master'], array_column($parsed['sections'], 'name'));

        $master = $parsed['sections'][2];
        self::assertSame('category', $master['items'][0]['key']);
        self::assertTrue($master['items'][0]['gated']);
        self::assertSame(self::ACTIONS, $master['items'][0]['actions']);

        // the new section's marker must land at the SAME column as its siblings
        self::assertStringContainsString("\n    - name: Master\n", $result);
    }

    public function testThrowsWhenSectionsKeyIsMissing(): void
    {
        $writer = new PermissionManifestWriter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sections/');

        $writer->addOrUpdateItem("foo: bar\n", 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);
    }
}
