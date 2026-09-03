import type { CrudSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { filterableFields, lowerWords } from '../naming.js';

export function entityHook(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesKebab } = names;
    const filters = filterableFields(spec);
    const pluralNoun = lowerWords(entitiesPascal);

    const hasFiltersLine =
        filters.length > 0
            ? `    const hasFilters = [${filters.map((f) => `query.${f.name}`).join(', ')}].some(Boolean);\n`
            : `    const hasFilters = false;\n`;

    return `import { useRouter, useSearchParams } from 'next/navigation';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { is${entityPascal}Collection } from '@/lib/api-shapes';
import {
    build${entitiesPascal}ApiPath,
    build${entitiesPascal}Href,
    clampPageToTotal,
    parse${entitiesPascal}Query,
    type ${entitiesPascal}Query
} from '@/lib/${entitiesKebab}-query';
import type { ${entityPascal} } from '@/types/api';
import { use${entitiesPascal}Export } from './use${entitiesPascal}Export';

export function use${entitiesPascal}() {
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
    const requestId = useRef(0);

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

${hasFiltersLine}    const trueEmpty = !loading && !error && !hasFilters && totalItems === 0;
    const filteredEmpty = !loading && !error && hasFilters && totalItems === 0;
    const hasItems = !loading && items.length > 0;
    const selectedItems = useMemo(
        () => items.filter((item) => selectedIds.has(item.id)),
        [items, selectedIds]
    );
    const allOnPageSelected =
        items.length > 0 && items.every((item) => selectedIds.has(item.id));
    const someOnPageSelected = items.some((item) => selectedIds.has(item.id));

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

    function clearSelection() {
        setSelectedIds(new Set());
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

    return {
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
        selectedItems,
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
    };
}
`;
}
