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
                    router.push(build${entitiesPascal}Href(query, { page: 1, search }))
                }
                onClear={() =>
                    router.push(build${entitiesPascal}Href(query, { page: 1, search: '' }))
                }
            />

`
        : '';

    const hasTitleFilterLine = field
        ? `    const hasSearch = Boolean(query.search);\n`
        : `    const hasSearch = false;\n`;

    return `'use client';

import { Button, EmptyState, LinkButton, ListPageCard, Toast } from '@kematjaya/bootstrap-ui-kit';
import { useRouter, useSearchParams } from 'next/navigation';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePermissions } from '@kematjaya/access-control-ui';
import { BulkActionsBar } from '@/components/crud/BulkActionsBar';
import { DeleteConfirmModal } from '@/components/crud/DeleteConfirmModal';
import { ExportAllButton } from '@/components/crud/ExportAllButton';
import { PaginationBar } from '@/components/crud/PaginationBar';
import { SearchPanel } from '@/components/crud/SearchPanel';
import { is${entityPascal}Collection } from '@/lib/api-shapes';
import {
    ${constPrefix}_PAGE_SIZES,
    build${entitiesPascal}ApiPath,
    build${entitiesPascal}Href,
    clampPageToTotal,
    nextItemsPerPagePage,
    parse${entitiesPascal}Query,
    type ${entitiesPascal}Query
} from '@/lib/${entitiesKebab}-query';
import type { ${entityPascal} } from '@/types/api';
import { use${entitiesPascal}Export } from './use${entitiesPascal}Export';

export function ${entityPascal}Table() {
    const router = useRouter();
    const searchParams = useSearchParams();
    const query = useMemo(() => parse${entitiesPascal}Query(searchParams), [searchParams]);
    const [items, setItems] = useState<${entityPascal}[]>([]);
    const [totalItems, setTotalItems] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<${entityPascal} | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [selectedIds, setSelectedIds] = useState<Set<${entityPascal}['id']>>(new Set());
    const [bulkDeleteConfirming, setBulkDeleteConfirming] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [toastMessage, setToastMessage] = useState(
        () => searchParams.get('toast') ?? ''
    );
    const { exportingSelected, exportingAll, exportSelected, exportAll } =
        use${entitiesPascal}Export();
    const { has: hasPermission, loading: permissionsLoading } = usePermissions();
    const requestId = useRef(0);
    const selectAllRef = useRef<HTMLInputElement>(null);

    const loadItems = useCallback(
        async (signal?: AbortSignal) => {
            const id = requestId.current + 1;
            requestId.current = id;
            setError('');
            setLoading(true);
            setItems([]);
            setTotalItems(0);
            let response: Response;
            try {
                response = await fetch(build${entitiesPascal}ApiPath(query), {
                    cache: 'no-store',
                    signal
                });
            } catch (fetchError) {
                if (
                    fetchError instanceof DOMException &&
                    fetchError.name === 'AbortError'
                )
                    return;
                if (requestId.current !== id) return;
                setError('Network error while loading ${pluralNoun}.');
                setLoading(false);
                return;
            }
            if (requestId.current !== id || signal?.aborted) return;
            if (response.status === 401) {
                router.push('/login');
                return;
            }
            if (!response.ok) {
                setError('Could not load ${pluralNoun}.');
                setLoading(false);
                return;
            }
            const data: unknown = await response.json();
            if (!is${entityPascal}Collection(data)) {
                setError('Unexpected response.');
                setLoading(false);
                return;
            }
            const clampedPage = clampPageToTotal(
                query.page,
                query.itemsPerPage,
                data.totalItems
            );
            if (clampedPage !== query.page) {
                router.replace(build${entitiesPascal}Href(query, { page: clampedPage }));
                return;
            }
            setItems(data.member);
            setTotalItems(data.totalItems);
            setLoading(false);
        },
        [query, router]
    );

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(
            () => void loadItems(controller.signal),
            0
        );
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [loadItems]);

    const [selectionQuery, setSelectionQuery] = useState(query);
    if (selectionQuery !== query) {
        setSelectionQuery(query);
        setSelectedIds(new Set());
    }

    function navigateTo(nextQuery: ${entitiesPascal}Query) {
        router.push(build${entitiesPascal}Href(nextQuery));
    }

${hasTitleFilterLine}    const trueEmpty = !loading && !error && !hasSearch && totalItems === 0;
    const filteredEmpty = !loading && !error && hasSearch && totalItems === 0;
    const hasItems = !loading && items.length > 0;
    const selectedItems = useMemo(
        () => items.filter((item) => selectedIds.has(item.id)),
        [items, selectedIds]
    );
    const allOnPageSelected =
        items.length > 0 && items.every((item) => selectedIds.has(item.id));
    const someOnPageSelected = items.some((item) => selectedIds.has(item.id));

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate =
                someOnPageSelected && !allOnPageSelected;
        }
    }, [someOnPageSelected, allOnPageSelected]);

    function toggleSelect(id: ${entityPascal}['id']) {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    function toggleSelectAll() {
        setSelectedIds((prev) => {
            const allSelected =
                items.length > 0 && items.every((item) => prev.has(item.id));
            return allSelected
                ? new Set()
                : new Set(items.map((item) => item.id));
        });
    }

    async function confirmDelete() {
        if (!deleteTarget) return;
        setDeleting(true);
        let response: Response;
        try {
            response = await fetch(\`/api/${entitiesKebab}/\${deleteTarget.id}\`, {
                method: 'DELETE'
            });
        } catch {
            setError('Network error while deleting.');
            setDeleting(false);
            return;
        }
        setDeleting(false);
        if (!response.ok && response.status !== 204) {
            setError('Could not delete.');
            return;
        }
        setDeleteTarget(null);
        setToastMessage('Deleted.');
        await loadItems();
    }

    async function confirmBulkDelete() {
        const ids = Array.from(selectedIds);
        if (ids.length === 0) return;
        setBulkDeleting(true);
        const outcomes = await Promise.allSettled(
            ids.map(async (id) => {
                const response = await fetch(\`/api/${entitiesKebab}/\${id}\`, {
                    method: 'DELETE'
                });
                if (!response.ok && response.status !== 204) {
                    throw new Error('delete failed');
                }
            })
        );
        setBulkDeleting(false);
        setBulkDeleteConfirming(false);
        const failedIds = ids.filter(
            (_, index) => outcomes[index]?.status === 'rejected'
        );
        const deletedCount = ids.length - failedIds.length;
        if (failedIds.length === ids.length) {
            setError('Could not delete selected ${pluralNoun}.');
        } else if (failedIds.length > 0) {
            setError(
                \`Deleted \${deletedCount} of \${ids.length}. Some deletions failed.\`
            );
            setToastMessage(\`\${deletedCount} deleted.\`);
        } else {
            setToastMessage(\`\${deletedCount} deleted.\`);
        }
        setSelectedIds(new Set(failedIds));
        await loadItems();
    }

    function handleExportSelected() {
        setError('');
        if (0 === selectedItems.length) return;
        const result = exportSelected(selectedItems);
        if (result.ok) {
            setSelectedIds(new Set());
            return;
        }
        setError(result.message);
    }

    async function handleExportAll() {
        setError('');
        const result = await exportAll(query);
        if (result.ok) return;
        if (401 === result.status) {
            router.push('/login');
            return;
        }
        setError(result.message);
    }

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
                                router.push(
                                    build${entitiesPascal}Href(query, { page: 1${field ? ", search: ''" : ''} })
                                )
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
                    onClearSelection={() => setSelectedIds(new Set())}
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
                    onPageChange={(page) =>
                        router.push(build${entitiesPascal}Href(query, { page }))
                    }
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
