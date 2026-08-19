import type { CrudSpec, FieldSpec } from '../spec.js';
import type { Names } from '../naming.js';

function tsType(field: FieldSpec): string {
    if (field.type === 'number') return 'number';
    if (field.type === 'boolean') return 'boolean';
    return 'string';
}

/** Fresh-file bootstrap, only used when src/lib/api-shapes.ts doesn't exist yet. */
export const apiShapesFileHeader = `export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
`;

export function apiShapesMarker(entityPascal: string): string {
    return `export function is${entityPascal}(`;
}

export function apiShapesImport(names: Names): string {
    return `import type { ${names.entityPascal}, ${names.entitiesPascal}Collection } from '@/types/api';\n`;
}

export function apiShapesBlock(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal } = names;
    const idJsType = 'int' === spec.idType ? 'number' : 'string';
    const checks = ['isRecord(value)', `typeof value.id === '${idJsType}'`];
    for (const field of spec.fields) {
        checks.push(`typeof value.${field.name} === '${tsType(field)}'`);
    }
    if (null !== spec.timestampField) {
        checks.push(`typeof value.${spec.timestampField} === 'string'`);
    }
    const checksBlock = checks.map((c, i) => `        ${c}${i < checks.length - 1 ? ' &&' : ''}`).join('\n');

    return `
export function is${entityPascal}(value: unknown): value is ${entityPascal} {
    return (
${checksBlock}
    );
}

export function is${entityPascal}Collection(value: unknown): value is ${entitiesPascal}Collection {
    return (
        isRecord(value) &&
        typeof value.totalItems === 'number' &&
        Array.isArray(value.member) &&
        value.member.every(is${entityPascal})
    );
}
`;
}
