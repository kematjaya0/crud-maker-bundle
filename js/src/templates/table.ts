import type { CrudSpec, FieldSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { displayFields, filterableFields, humanize, labelField, lowerWords } from '../naming.js';

function columnCell(fieldName: string, fieldType: FieldSpec['type'] | null, timestampField: string | null): string {
    if (fieldName === timestampField || fieldType === 'datetime') {
        return `                                    <td>
                                        {new Date(
                                            item.${fieldName}
                                        ).toLocaleString()}
                                    </td>`;
    }
    if (fieldType === 'date') {
        // "YYYY-MM-DD" alone parses as UTC midnight per spec (wrong day in timezones behind
        // UTC); appending a time forces local-time parsing instead.
        return `                                    <td>
                                        {new Date(
                                            \`\${item.${fieldName}}T00:00:00\`
                                        ).toLocaleDateString()}
                                    </td>`;
    }
    return `                                    <td>{String(item.${fieldName})}</td>`;
}

function filterFieldKind(type: FieldSpec['type']): 'text' | 'number' | 'boolean' {
    if (type === 'number') return 'number';
    if (type === 'boolean') return 'boolean';
    return 'text';
}

export function table(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesCamel, entitiesKebab } = names;
    const prefix = entitiesKebab;
    const cols = displayFields(spec).map((f) => f.name);
    if (null !== spec.timestampField && !cols.includes(spec.timestampField)) {
        cols.push(spec.timestampField);
    }
    const fieldTypeByName = new Map(spec.fields.map((f) => [f.name, f.type] as const));
    const label = labelField(spec);
    const noun = lowerWords(entityPascal);
    const pluralNoun = lowerWords(entitiesPascal);
    const filters = filterableFields(spec);
    const constPrefix = entitiesCamel.toUpperCase();
    const hasFilter = filters.length > 0;
    const filterPanelId = `${noun}-filter-panel`;

    const headCells = cols.map((c) => `                                <th>${humanize(c)}</th>`).join('\n');
    const bodyCells = cols
        .map((c) => columnCell(c, fieldTypeByName.get(c) ?? null, spec.timestampField))
        .join('\n');

    const filterButtonBlock = hasFilter
        ? `                    <Button
                        variant="outline"
                        icon={filterOpen ? 'bi-chevron-up' : 'bi-funnel'}
                        aria-expanded={filterOpen}
                        aria-controls="${filterPanelId}"
                        onClick={() => setFilterOpen((open) => !open)}
                    >
                        Filter
                    </Button>
`
        : '';

    const filterFieldsLiteral = filters
        .map((f) => `{ name: '${f.name}', label: '${humanize(f.name)}', kind: '${filterFieldKind(f.type)}' }`)
        .join(',\n                        ');
    const filterValuesLiteral = filters.map((f) => `${f.name}: query.${f.name}`).join(', ');
    const clearedValuesLiteral = filters.map((f) => `${f.name}: ''`).join(', ');
    const filterKeyExpr = filters.map((f) => '${query.' + f.name + '}').join('|');

    const filterPanelBlock = hasFilter
        ? `            <div
                id="${filterPanelId}"
                className={\`crud-filter-collapse collapse\${filterOpen ? ' show' : ''}\`}
            >
                <FilterPanel
                    key={\`${filterKeyExpr}\`}
                    fields={[
                        ${filterFieldsLiteral}
                    ]}
                    values={{ ${filterValuesLiteral} }}
                    totalItems={totalItems}
                    pluralNoun="${pluralNoun}"
                    pending={loading}
                    disabled={trueEmpty}
                    onApply={(values) =>
                        navigateTo({ ...query, page: 1, ...values })
                    }
                    onClear={() =>
                        navigateTo({ ...query, page: 1, ${clearedValuesLiteral} })
                    }
                />
            </div>

`
        : '';

    return `'use client';

import { Button, EmptyState, LinkButton, ListPageCard, Toast } from '@kematjaya/bootstrap-ui-kit';
import { useEffect, useRef${hasFilter ? ', useState' : ''} } from 'react';
import { usePermissions } from '@kematjaya/access-control-ui';
import { BulkActionsBar } from '@/components/crud/BulkActionsBar';
import { DeleteConfirmModal } from '@/components/crud/DeleteConfirmModal';
import { ExportAllButton } from '@/components/crud/ExportAllButton';
${hasFilter ? "import { FilterPanel } from '@/components/crud/FilterPanel';\n" : ''}import { PaginationBar } from '@/components/crud/PaginationBar';
import {
    ${constPrefix}_PAGE_SIZES,
    build${entitiesPascal}Href,
    nextItemsPerPagePage
} from '@/lib/${entitiesKebab}-query';
import { use${entitiesPascal} } from './use${entitiesPascal}';

export function ${entityPascal}Table() {
    const {
        query,
        items,
        totalItems,
        loading,
        error,
        hasFilters,
        trueEmpty,
        filteredEmpty,
        hasItems,
        navigateTo,
        selectedIds,
        allOnPageSelected,
        someOnPageSelected,
        toggleSelect,
        toggleSelectAll,
        clearSelection,
        deleteTarget,
        setDeleteTarget,
        deleting,
        confirmDelete,
        bulkDeleteConfirming,
        setBulkDeleteConfirming,
        bulkDeleting,
        confirmBulkDelete,
        toastMessage,
        setToastMessage,
        exportingSelected,
        exportingAll,
        handleExportSelected,
        handleExportAll
    } = use${entitiesPascal}();
    const { has: hasPermission, loading: permissionsLoading } = usePermissions();
    const selectAllRef = useRef<HTMLInputElement>(null);
${hasFilter ? '    const [filterOpen, setFilterOpen] = useState(false);\n' : ''}
    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate =
                someOnPageSelected && !allOnPageSelected;
        }
    }, [someOnPageSelected, allOnPageSelected]);

    return (
        <ListPageCard
            title="${entitiesPascal}"
            error={error}
            action={
                <div className="crud-card-actions">
${filterButtonBlock}                    {(permissionsLoading || hasPermission('${prefix}.export_all')) && (
                        <ExportAllButton
                            totalItems={totalItems}
                            filtered={hasFilters}
                            exporting={exportingAll}
                            disabled={loading || exportingAll || 0 === totalItems}
                            onExport={() => void handleExportAll()}
                        />
                    )}
                    {(permissionsLoading || hasPermission('${prefix}.create')) && (
                        <LinkButton href="/dashboard/${entitiesKebab}/new" icon="bi-plus-lg">
                            New
                        </LinkButton>
                    )}
                </div>
            }
        >
${filterPanelBlock}            {trueEmpty && (
                <EmptyState
                    title="No ${pluralNoun} yet"
                    description="Create your first ${noun} to get started."
                    action={
                        (permissionsLoading || hasPermission('${prefix}.create')) && (
                            <LinkButton href="/dashboard/${entitiesKebab}/new">
                                New
                            </LinkButton>
                        )
                    }
                />
            )}

            {filteredEmpty && (
                <EmptyState
                    title="No ${pluralNoun} match your filters"
                    description="Try different filters or clear them to see all ${pluralNoun}."
                    action={
                        <Button
                            variant="outline"
                            onClick={() =>
                                navigateTo({ ...query, page: 1${hasFilter ? `, ${clearedValuesLiteral}` : ''} })
                            }
                        >
                            Clear filters
                        </Button>
                    }
                />
            )}

            {hasItems && 0 < selectedIds.size && (
                <BulkActionsBar
                    selectedCount={selectedIds.size}
                    noun="${noun}"
                    pluralNoun="${pluralNoun}"
                    disabled={bulkDeleting || exportingSelected}
                    onDelete={permissionsLoading || hasPermission('${prefix}.bulk_delete') || hasPermission('${prefix}.delete') ? () => setBulkDeleteConfirming(true) : undefined}
                    onExportSelected={permissionsLoading || hasPermission('${prefix}.export_selected') ? handleExportSelected : undefined}
                    onClearSelection={clearSelection}
                />
            )}

            {hasItems && (
                <div className="dash-table-scroll">
                    <table className="dash-table">
                        <thead>
                            <tr>
                                {(permissionsLoading || hasPermission('${prefix}.bulk_delete') || hasPermission('${prefix}.delete')) && (
                                    <th style={{ width: 36 }}>
                                        <input
                                            ref={selectAllRef}
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={allOnPageSelected}
                                            onChange={toggleSelectAll}
                                            aria-label="Select all ${pluralNoun} on this page"
                                        />
                                    </th>
                                )}
${headCells}
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id}>
                                    {(permissionsLoading || hasPermission('${prefix}.bulk_delete') || hasPermission('${prefix}.delete')) && (
                                        <td>
                                            <input
                                                type="checkbox"
                                                className="form-check-input"
                                                checked={selectedIds.has(item.id)}
                                                onChange={() =>
                                                    toggleSelect(item.id)
                                                }
                                                aria-label={\`Select \${item.${label}}\`}
                                            />
                                        </td>
                                    )}
${bodyCells}
                                    <td>
                                        <div className="d-flex gap-2">
                                            {(permissionsLoading || hasPermission('${prefix}.edit')) && (
                                                <LinkButton
                                                    href={\`/dashboard/${entitiesKebab}/\${item.id}/edit\`}
                                                    variant="outline-primary"
                                                    size="sm"
                                                    icon="bi-pencil"
                                                    aria-label={\`Edit \${item.${label}}\`}
                                                >
                                                    Edit
                                                </LinkButton>
                                            )}
                                            {(permissionsLoading || hasPermission('${prefix}.delete')) && (
                                                <Button
                                                    variant="outline-danger"
                                                    size="sm"
                                                    icon="bi-trash"
                                                    onClick={() =>
                                                        setDeleteTarget(item)
                                                    }
                                                    aria-label={\`Delete \${item.${label}}\`}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {hasItems && (
                <PaginationBar
                    page={query.page}
                    itemsPerPage={query.itemsPerPage}
                    pageSizes={${constPrefix}_PAGE_SIZES}
                    totalItems={totalItems}
                    loadedItems={items.length}
                    buildHref={(patch) => build${entitiesPascal}Href(query, patch)}
                    onPageChange={(page) => navigateTo({ ...query, page })}
                    onPageSizeChange={(itemsPerPage) =>
                        navigateTo({
                            ...query,
                            itemsPerPage,
                            page: nextItemsPerPagePage(
                                query.page,
                                query.itemsPerPage,
                                itemsPerPage
                            )
                        })
                    }
                />
            )}

            {deleteTarget && (
                <DeleteConfirmModal
                    title="Delete ${noun}?"
                    message={
                        <>
                            Are you sure you want to delete{' '}
                            <strong>{String(deleteTarget.${label})}</strong>? This action
                            cannot be undone.
                        </>
                    }
                    confirmLabel="Delete"
                    confirming={deleting}
                    onConfirm={() => void confirmDelete()}
                    onCancel={() => setDeleteTarget(null)}
                />
            )}

            {bulkDeleteConfirming && (
                <DeleteConfirmModal
                    title={\`Delete \${selectedIds.size} ${pluralNoun}?\`}
                    message={
                        <>
                            Are you sure you want to delete{' '}
                            <strong>{selectedIds.size}</strong> selected ${pluralNoun}?
                            This action cannot be undone.
                        </>
                    }
                    confirmLabel="Delete"
                    confirming={bulkDeleting}
                    onConfirm={() => void confirmBulkDelete()}
                    onCancel={() => setBulkDeleteConfirming(false)}
                />
            )}

            {toastMessage && (
                <Toast
                    message={toastMessage}
                    onDismiss={() => setToastMessage('')}
                />
            )}
        </ListPageCard>
    );
}
`;
}
