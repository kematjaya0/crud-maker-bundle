import type { CrudSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { searchField } from '../naming.js';

export function queryLib(spec: CrudSpec, names: Names): string {
    const { entitiesKebab, entitiesPascal, entitiesCamel } = names;
    const field = searchField(spec);
    const constPrefix = entitiesCamel.toUpperCase();

    return `export const ${constPrefix}_PAGE_SIZES = [10, 20, 30, 50] as const;
export const ${constPrefix}_DEFAULT_PAGE_SIZE = 30;

export type ${entitiesPascal}Query = {
${field !== null ? '    search: string;\n' : ''}    page: number;
    itemsPerPage: number;
};

function parsePositiveInteger(value: string | null): number | null {
    if (value === null || !/^[1-9]\\d*$/.test(value)) return null;
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) ? parsed : null;
}

function isAllowedPageSize(
    value: number | null
): value is (typeof ${constPrefix}_PAGE_SIZES)[number] {
    return value !== null && ${constPrefix}_PAGE_SIZES.some((size) => size === value);
}

export function parse${entitiesPascal}Query(searchParams: URLSearchParams): ${entitiesPascal}Query {
    const state: ${entitiesPascal}Query = {
${field !== null ? `        search: searchParams.get('${field}')?.trim() ?? '',\n` : ''}        page: parsePositiveInteger(searchParams.get('page')) ?? 1,
        itemsPerPage: ${constPrefix}_DEFAULT_PAGE_SIZE
    };
    const pageSize = parsePositiveInteger(searchParams.get('itemsPerPage'));
    if (isAllowedPageSize(pageSize)) state.itemsPerPage = pageSize;

    return state;
}

function buildParams(state: ${entitiesPascal}Query): URLSearchParams {
    const params = new URLSearchParams();
${field !== null ? `    if (state.search) params.set('${field}', state.search);\n` : ''}    if (state.page !== 1) params.set('page', String(state.page));
    if (state.itemsPerPage !== ${constPrefix}_DEFAULT_PAGE_SIZE) {
        params.set('itemsPerPage', String(state.itemsPerPage));
    }
    return params;
}

export function build${entitiesPascal}Href(
    current: ${entitiesPascal}Query,
    patch: Partial<${entitiesPascal}Query> = {}
): string {
    const query = buildParams({ ...current, ...patch }).toString();
    return query ? \`/dashboard/${entitiesKebab}?\${query}\` : '/dashboard/${entitiesKebab}';
}

export function build${entitiesPascal}ApiPath(state: ${entitiesPascal}Query): string {
    const query = buildParams(state).toString();
    return query ? \`/api/${entitiesKebab}?\${query}\` : '/api/${entitiesKebab}';
}

export function clampPageToTotal(
    page: number,
    itemsPerPage: number,
    totalItems: number
): number {
    const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
    return Math.min(Math.max(page, 1), totalPages);
}

export function nextItemsPerPagePage(
    currentPage: number,
    oldItemsPerPage: number,
    newItemsPerPage: number
): number {
    const firstRowShown = (currentPage - 1) * oldItemsPerPage + 1;
    return Math.max(1, Math.ceil(firstRowShown / newItemsPerPage));
}
`;
}
