import type { CrudSpec } from './spec.js';

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

/** The single field used for the search box / list-view SearchFilter query param, if any. */
export function searchField(spec: CrudSpec): string | null {
    return spec.fields.find((f) => f.searchable)?.name ?? null;
}

/** All searchable field names, used by the export endpoint's OR-search. */
export function searchableFields(spec: CrudSpec): string[] {
    return spec.fields.filter((f) => f.searchable).map((f) => f.name);
}
