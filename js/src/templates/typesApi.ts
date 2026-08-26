import type { CrudSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { displayFields } from '../naming.js';

/**
 * Fresh-file bootstrap, only used when src/types/api.ts doesn't exist yet. In the boilerplate
 * app this file already exists (written for the Notes feature) with this same Json helper
 * and the `paths`/`components` import from the OpenAPI-generated `./api.generated` — this
 * header is only a fallback for a from-scratch project that hasn't set that up yet.
 *
 * Extracts the `application/json` (not `application/ld+json`/Hydra) response shape, matching
 * how `lib/bff.ts`'s `authedBackend()` calls the API Platform backend — plain JSON, so
 * collections come back as `{ totalItems, member }` rather than a Hydra-wrapped `hydra:member`.
 */
export const typesApiFileHeader = `import type { components, paths } from './api.generated';

type Json<T> = T extends { 'application/json': infer V } ? V : never;
`;

export function typesApiMarker(entityPascal: string): string {
    return `export type ${entityPascal} =`;
}

export function typesApiBlock(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesKebab } = names;
    const picked = ['id', ...displayFields(spec).map((f) => f.name)];
    if (spec.fields.some((f) => f.type === 'textarea')) {
        for (const f of spec.fields) {
            if (f.type === 'textarea' && !picked.includes(f.name)) picked.push(f.name);
        }
    }
    if (null !== spec.timestampField && !picked.includes(spec.timestampField)) {
        picked.push(spec.timestampField);
    }
    const pickList = picked.map((p) => `'${p}'`).join(' | ');

    return `
type ${entitiesPascal}CollectionResponses = paths['/api/${entitiesKebab}']['get']['responses'];
type ${entityPascal}PostResponses = paths['/api/${entitiesKebab}']['post']['responses'];
type Generated${entityPascal} = NonNullable<Json<${entityPascal}PostResponses[201]['content']>>;
type Generated${entitiesPascal}Collection = NonNullable<
    Json<${entitiesPascal}CollectionResponses[200]['content']>
>;

export type ${entityPascal}Input = components['schemas']['${entityPascal}.${entityPascal}Input'];
export type ${entityPascal} = Required<
    Pick<Generated${entityPascal}, ${pickList}>
>;
export type ${entitiesPascal}Collection = Omit<
    Generated${entitiesPascal}Collection,
    'member' | 'totalItems'
> & { member: ${entityPascal}[]; totalItems: number };
`;
}
