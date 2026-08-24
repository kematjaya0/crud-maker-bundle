import type { CrudSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { displayFields, humanize, labelField, lowerWords, searchField } from '../naming.js';

function columnCell(fieldName: string, timestampField: string | null): string {
    if (fieldName === timestampField) {
        return `                                    <td>
                                        {new Date(
                                            item.${fieldName}
                                        ).toLocaleString()}
                                    </td>`;
    }
    return `                                    <td>{String(item.${fieldName})}</td>`;
}

export function table(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesCamel, entitiesKebab } = names;
    const prefix = entitiesKebab;
    const cols = displayFields(spec).map((f) => f.name);
    if (null !== spec.timestampField && !cols.includes(spec.timestampField)) {
        cols.push(spec.timestampField);
    }
    const label = labelField(spec);
    const noun = lowerWords(entityPascal);
    const pluralNoun = lowerWords(entitiesPascal);
    const field = searchField(spec);
    const constPrefix = entitiesCamel.toUpperCase();

    const headCells = cols.map((c) => `                                <th>${humanize(c)}</th>`).join('\n');
    const bodyCells = cols.map((c) => columnCell(c, spec.timestampField)).join('\n');

    const searchPanelBlock = field
        ? `            <SearchPanel
                key={query.search}
                label="Search by ${humanize(field)}"
                value={query.search}
                totalItems={totalItems}
                pluralNoun="${pluralNoun}"
                pending={loading}
                disabled={trueEmpty}
                onSearch={(search) =>
                    navigateTo({ ...query, page: 1, search })
                }
                onClear={() =>
                    navigateTo({ ...query, page: 1, search: '' })
                }
            />

`
        : '';

    return `'use client';

import { Button, EmptyState, LinkButton, ListPageCard, Toast } from '@kematjaya/bootstrap-ui-kit';
import { useEffect, useRef } from 'react';
import { usePermissions } from '@kematjaya/access-control-ui';
import { BulkActionsBar } from '@/components/crud/BulkActionsBar';
import { DeleteConfirmModal } from '@/components/crud/DeleteConfirmModal';
import { ExportAllButton } from '@/components/crud/ExportAllButton';
import { PaginationBar } from '@/components/crud/PaginationBar';
import { SearchPanel } from '@/components/crud/SearchPanel';
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
        hasSearch,
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
                    {(permissionsLoading || hasPermission('${prefix}.export_all')) && (
                        <ExportAllButton
                            totalItems={totalItems}
                            filtered={hasSearch}
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
${searchPanelBlock}            {trueEmpty && (
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
                    title="No ${pluralNoun} match your search"
                    description="Try a different search or clear it to see all ${pluralNoun}."
                    action={
                        <Button
                            variant="outline"
                            onClick={() =>
                                navigateTo({ ...query, page: 1${field ? ", search: ''" : ''} })
                            }
                        >
                            Clear search
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
