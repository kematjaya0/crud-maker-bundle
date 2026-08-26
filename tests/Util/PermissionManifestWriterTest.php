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

    public function testThrowsWhenSectionsKeyIsMissing(): void
    {
        $writer = new PermissionManifestWriter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sections/');

        $writer->addOrUpdateItem("foo: bar\n", 'Master', 'notes', 'Notes', '/dashboard/notes', 'bi-journal-text', ['ROLE_USER'], self::ACTIONS);
    }
}
