import type { CrudSpec, FieldSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { humanize } from '../naming.js';

/** Fresh-file bootstrap, only used when src/lib/schemas.ts doesn't exist yet. */
export const schemasFileHeader = `import { z } from 'zod';
`;

export function schemasMarker(entityCamel: string): string {
    return `export const ${entityCamel}Schema = `;
}

function zodField(field: FieldSpec): string {
    const label = humanize(field.name);

    if (field.type === 'boolean') return 'z.boolean()';

    if (field.type === 'number') {
        return field.required ? 'z.number()' : 'z.number().optional()';
    }

    let expr = 'z.string().trim()';
    if (field.required) expr += `.min(1, '${label} is required')`;
    if (field.maxLength !== null) expr += `.max(${field.maxLength})`;
    if (!field.required) expr += '.optional()';
    return expr;
}

export function schemasBlock(spec: CrudSpec, names: Names): string {
    const { entityPascal, entityCamel } = names;
    const fields = spec.fields
        .map((f) => `    ${f.name}: ${zodField(f)}`)
        .join(',\n');

    return `
export const ${entityCamel}Schema = z.object({
${fields}
});

export type ${entityPascal}FormValues = z.infer<typeof ${entityCamel}Schema>;
`;
}
