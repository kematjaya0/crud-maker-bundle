/**
 * Entity-agnostic CRUD UI primitives, genericized from boilerplate/frontend's hand-written
 * Notes feature (DeleteConfirmModal, NotesBulkActionsBar, NotesPaginationBar, NotesSearchPanel,
 * ExportAllButton — all under src/components/notes/). Written once per frontend project into
 * src/components/crud/ (skipped on repeat runs / for later entities) so N generated entities
 * don't each carry a byte-identical copy.
 */

export const deleteConfirmModal = `'use client';

import type { ReactNode } from 'react';
import { Button } from '@kematjaya/bootstrap-ui-kit';

type Props = {
    title: string;
    message: ReactNode;
    confirmLabel?: string;
    confirming: boolean;
    onConfirm: () => void;
    onCancel: () => void;
};

export function DeleteConfirmModal({
    title,
    message,
    confirmLabel = 'Delete',
    confirming,
    onConfirm,
    onCancel
}: Props) {
    return (
        <>
            <div className="modal-backdrop fade show" />
            <div
                className="modal fade show d-block"
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                aria-labelledby="crud-delete-modal-title"
            >
                <div className="modal-dialog modal-dialog-centered">
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title" id="crud-delete-modal-title">
                                {title}
                            </h5>
                            <button type="button" className="btn-close" aria-label="Close" onClick={onCancel} />
                        </div>
                        <div className="modal-body">
                            <p className="m-0">{message}</p>
                        </div>
                        <div className="modal-footer" style={{ flexDirection: 'row-reverse' }}>
                            <Button variant="danger" disabled={confirming} onClick={onConfirm}>
                                {confirming ? 'Deleting…' : confirmLabel}
                            </Button>
                            <Button variant="outline" onClick={onCancel}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
`;

export const bulkActionsBar = `type Props = {
    selectedCount: number;
    noun: string;
    pluralNoun: string;
    disabled?: boolean;
    onDelete?: () => void;
    onExportSelected?: () => void;
    onClearSelection: () => void;
};

export function BulkActionsBar({
    selectedCount,
    noun,
    pluralNoun,
    disabled = false,
    onDelete,
    onExportSelected,
    onClearSelection
}: Props) {
    return (
        <div
            className="crud-bulk-bar"
            role="toolbar"
            aria-label="Bulk actions"
        >
            <span className="crud-bulk-count">
                {selectedCount} {selectedCount === 1 ? noun : pluralNoun}{' '}
                selected
            </span>
            <div className="crud-bulk-actions">
                {onDelete && (
                    <button
                        type="button"
                        className="btn btn-outline-danger btn-sm"
                        disabled={disabled}
                        onClick={onDelete}
                    >
                        <i className="bi bi-trash" aria-hidden="true" /> Delete
                    </button>
                )}
                {onExportSelected && (
                    <button
                        type="button"
                        className="btn btn-outline-primary btn-sm"
                        disabled={disabled}
                        onClick={onExportSelected}
                    >
                        <i className="bi bi-download" aria-hidden="true" /> Export
                        Selected
                    </button>
                )}
                <button
                    type="button"
                    className="btn btn-sm btn-link p-0"
                    onClick={onClearSelection}
                    disabled={disabled}
                >
                    Clear selection
                </button>
            </div>
        </div>
    );
}
`;

export const exportAllButton = `type Props = {
    totalItems: number;
    filtered: boolean;
    exporting: boolean;
    disabled: boolean;
    onExport: () => void;
};

export function ExportAllButton({
    totalItems,
    filtered,
    exporting,
    disabled,
    onExport
}: Props) {
    return (
        <div className="crud-export-all">
            <button
                type="button"
                className="btn btn-outline-primary"
                disabled={disabled}
                aria-busy={exporting}
                onClick={onExport}
            >
                <i className="bi bi-download" aria-hidden="true" /> Export All (
                {totalItems})
            </button>
            {filtered && (
                <span className="crud-export-filter" aria-live="polite">
                    matching current search
                </span>
            )}
        </div>
    );
}
`;

export const paginationBar = `import { Button, LinkButton, dashButtonClassName } from '@kematjaya/bootstrap-ui-kit';

export type PageSizeChange = { page?: number; itemsPerPage?: number };

type Props = {
    page: number;
    itemsPerPage: number;
    pageSizes: readonly number[];
    totalItems: number;
    loadedItems: number;
    buildHref: (patch: PageSizeChange) => string;
    onPageSizeChange: (itemsPerPage: number) => void;
    onPageChange: (page: number) => void;
};

function PageControl({
    disabled,
    href,
    label
}: {
    disabled: boolean;
    href: string;
    label: string;
}) {
    if (disabled) {
        return (
            <span
                className={\`\${dashButtonClassName('outline')} disabled\`}
                aria-disabled="true"
            >
                {label}
            </span>
        );
    }

    return (
        <LinkButton variant="outline" href={href}>
            {label}
        </LinkButton>
    );
}

export function PaginationBar({
    page,
    itemsPerPage,
    pageSizes,
    totalItems,
    loadedItems,
    buildHref,
    onPageSizeChange,
    onPageChange
}: Props) {
    if (totalItems <= itemsPerPage) return null;

    const pageCount = Math.max(1, Math.ceil(totalItems / itemsPerPage));
    const first = totalItems === 0 ? 0 : (page - 1) * itemsPerPage + 1;
    const last = first === 0 ? 0 : first + loadedItems - 1;
    const summary = \`\${first}–\${last} of \${totalItems}\`;

    return (
        <nav className="crud-pagination" aria-label="Pagination">
            <p className="dash-card-subtitle" aria-live="polite">
                Showing {summary}
            </p>
            <label className="crud-page-size">
                <span>Rows</span>
                <select
                    className="form-select form-select-sm"
                    value={itemsPerPage}
                    aria-label="Rows per page"
                    onChange={(event) =>
                        onPageSizeChange(Number(event.target.value))
                    }
                >
                    {pageSizes.map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </select>
            </label>
            <div className="crud-page-links">
                <PageControl
                    disabled={page === 1}
                    href={buildHref({ page: 1 })}
                    label="First"
                />
                <PageControl
                    disabled={page === 1}
                    href={buildHref({ page: Math.max(1, page - 1) })}
                    label="Previous"
                />
                <span className="dash-card-subtitle">
                    Page {page} of {pageCount}
                </span>
                <form
                    className="crud-page-jump"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const value = Number(
                            new FormData(event.currentTarget).get('page')
                        );
                        if (Number.isInteger(value))
                            onPageChange(Math.min(pageCount, Math.max(1, value)));
                    }}
                >
                    <label>
                        <span>Go to page</span>
                        <input
                            key={page}
                            className="form-control form-control-sm"
                            name="page"
                            type="number"
                            min={1}
                            max={pageCount}
                            defaultValue={page}
                            aria-label="Go to page"
                        />
                    </label>
                    <Button type="submit" variant="outline">
                        Go
                    </Button>
                </form>
                <PageControl
                    disabled={page >= pageCount}
                    href={buildHref({ page: Math.min(pageCount, page + 1) })}
                    label="Next"
                />
                <PageControl
                    disabled={page >= pageCount}
                    href={buildHref({ page: pageCount })}
                    label="Last"
                />
            </div>
        </nav>
    );
}
`;

export const searchPanel = `import { useId } from 'react';
import { Button } from '@kematjaya/bootstrap-ui-kit';

type Props = {
    label: string;
    value: string;
    totalItems: number;
    pluralNoun: string;
    pending: boolean;
    disabled: boolean;
    onSearch: (value: string) => void;
    onClear: () => void;
};

function summary(totalItems: number, pluralNoun: string, filtered: boolean) {
    return filtered
        ? \`\${totalItems} \${pluralNoun} match your search\`
        : \`\${totalItems} \${pluralNoun} total\`;
}

export function SearchPanel({
    label,
    value,
    totalItems,
    pluralNoun,
    pending,
    disabled,
    onSearch,
    onClear
}: Props) {
    const inputId = useId();
    const trimmedValue = value.trim();
    const searchDisabled = pending || disabled;

    return (
        <div className="crud-search-panel">
            <form
                className="crud-toolbar"
                method="get"
                role="search"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSearch(
                        new FormData(event.currentTarget)
                            .get('q')
                            ?.toString()
                            .trim() ?? ''
                    );
                }}
            >
                <div className="crud-search-field">
                    <label htmlFor={inputId} className="form-label mb-1">
                        {label}
                    </label>
                    <input
                        id={inputId}
                        type="search"
                        className="form-control"
                        name="q"
                        defaultValue={value}
                        placeholder={label}
                        autoComplete="off"
                        disabled={searchDisabled}
                    />
                </div>
                <div className="crud-toolbar-actions">
                    <Button type="submit" disabled={searchDisabled}>
                        Search
                    </Button>
                    <Button
                        variant="outline"
                        onClick={(event) => {
                            event.preventDefault();
                            onClear();
                        }}
                        aria-label="Clear search"
                        disabled={!trimmedValue || pending}
                    >
                        Clear
                    </Button>
                </div>
            </form>
            <div className="crud-search-meta">
                <p
                    className="dash-card-subtitle m-0"
                    role="status"
                    aria-live="polite"
                    aria-busy={pending}
                >
                    {pending ? 'Loading...' : summary(totalItems, pluralNoun, Boolean(trimmedValue))}
                </p>
                {trimmedValue && (
                    <div
                        className="crud-filter-chip"
                        aria-label={\`Active filter: \${trimmedValue}\`}
                    >
                        <span>Search: {trimmedValue}</span>
                        <button
                            type="button"
                            className="btn btn-sm btn-link p-0"
                            onClick={onClear}
                            disabled={pending}
                        >
                            Clear all filters
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
`;
