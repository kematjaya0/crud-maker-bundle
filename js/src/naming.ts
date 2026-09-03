import type { CrudSpec, FieldSpec } from './spec.js';

export type Names = {
    /** PascalCase singular entity name, e.g. "TestArticle" (verbatim from spec.entity). */
    entityPascal: string;
    /** camelCase singular entity name, e.g. "testArticle". */
    entityCamel: string;
    /** kebab-case plural, used as the URL segment / folder name, e.g. "test-articles" (verbatim from spec.permissionPrefix). */
    entitiesKebab: string;
    /** PascalCase plural, e.g. "TestArticles" — used for compound identifiers (hook/type names). */
    entitiesPascal: string;
    /** camelCase plural, e.g. "testArticles" — used for variable identifiers. */
    entitiesCamel: string;
};

function kebabToPascal(kebab: string): string {
    return kebab
        .split(/[-_]/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');
}

export function namesFromSpec(spec: CrudSpec): Names {
    const entityPascal = spec.entity;
    const entityCamel = entityPascal.charAt(0).toLowerCase() + entityPascal.slice(1);
    const entitiesKebab = spec.permissionPrefix;
    const entitiesPascal = kebabToPascal(entitiesKebab);
    const entitiesCamel = entitiesPascal.charAt(0).toLowerCase() + entitiesPascal.slice(1);

    return { entityPascal, entityCamel, entitiesKebab, entitiesPascal, entitiesCamel };
}

/** Field types with no filter-panel control yet — excluded from `filterableFields()`. */
const UNFILTERABLE_TYPES: ReadonlyArray<FieldSpec['type']> = ['textarea', 'date', 'datetime'];

/**
 * Fields rendered as controls in the list view's Filter panel: every `searchable` field except
 * `textarea` (long text doesn't make a sensible filter input) and `date`/`datetime` (no
 * date-range filter control exists yet — a future iteration), mirroring `displayFields()`'s
 * `textarea` exclusion plus this narrower one. All of these are wired into the list query and
 * AND-combined. The CSV export endpoint's OR-search (against every `searchable` field, textarea
 * included) is a separate, server-side-only concept — it reuses this list's first filterable
 * field's current value as its convenience search text (see `useExport.ts` / `bffRoutes.ts`'s
 * `exportRoute`).
 */
export function filterableFields(spec: CrudSpec): FieldSpec[] {
    return spec.fields.filter((f) => f.searchable && !UNFILTERABLE_TYPES.includes(f.type));
}

/** "createdAt" -> "Created At", "TestArticle" -> "Test Article". */
export function humanize(identifier: string): string {
    const words = identifier
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/[-_]/g, ' ')
        .trim()
        .split(/\s+/);
    return words.map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

/** "TestArticles" -> "test articles" — used for prose/aria copy. */
export function lowerWords(identifier: string): string {
    return humanize(identifier).toLowerCase();
}

/**
 * Fields shown as table columns / export CSV columns: everything except long-text (textarea)
 * fields, mirroring how the hand-written Notes table shows `title` but not `body`.
 */
export function displayFields(spec: CrudSpec) {
    return spec.fields.filter((f) => f.type !== 'textarea');
}

/** Field used to label a single row in delete-confirmation copy / aria-labels. */
export function labelField(spec: CrudSpec): string {
    return displayFields(spec)[0]?.name ?? spec.fields[0]?.name ?? 'id';
}
