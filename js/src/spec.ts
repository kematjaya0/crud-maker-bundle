import { readFileSync } from 'node:fs';

export type FieldSpec = {
    name: string;
    type: 'text' | 'textarea' | 'number' | 'boolean';
    required: boolean;
    maxLength: number | null;
    searchable: boolean;
};

export type IdType = 'uuid' | 'int' | 'string';

export type CrudSpec = {
    entity: string;
    permissionPrefix: string;
    ownerProperty: string | null;
    timestampField: string | null;
    idType: IdType;
    fields: FieldSpec[];
};

function isFieldSpec(value: unknown): value is FieldSpec {
    if (typeof value !== 'object' || value === null) return false;
    const f = value as Record<string, unknown>;
    return (
        typeof f.name === 'string' &&
        ['text', 'textarea', 'number', 'boolean'].includes(f.type as string) &&
        typeof f.required === 'boolean' &&
        (f.maxLength === null || typeof f.maxLength === 'number') &&
        typeof f.searchable === 'boolean'
    );
}

export function loadSpec(specPath: string): CrudSpec {
    let raw: string;
    try {
        raw = readFileSync(specPath, 'utf8');
    } catch {
        throw new Error(`Could not read spec file: ${specPath}`);
    }

    let data: unknown;
    try {
        data = JSON.parse(raw);
    } catch {
        throw new Error(`Spec file is not valid JSON: ${specPath}`);
    }

    if (typeof data !== 'object' || data === null) {
        throw new Error(`Spec file must contain a JSON object: ${specPath}`);
    }
    const spec = data as Record<string, unknown>;

    if (typeof spec.entity !== 'string' || spec.entity === '') {
        throw new Error(`Spec file missing "entity": ${specPath}`);
    }
    if (typeof spec.permissionPrefix !== 'string' || spec.permissionPrefix === '') {
        throw new Error(`Spec file missing "permissionPrefix": ${specPath}`);
    }
    if (!Array.isArray(spec.fields) || !spec.fields.every(isFieldSpec)) {
        throw new Error(`Spec file "fields" is missing or malformed: ${specPath}`);
    }

    const idType: IdType =
        spec.idType === 'int' || spec.idType === 'string' || spec.idType === 'uuid' ? spec.idType : 'uuid';

    return {
        entity: spec.entity,
        permissionPrefix: spec.permissionPrefix,
        ownerProperty: typeof spec.ownerProperty === 'string' ? spec.ownerProperty : null,
        timestampField: typeof spec.timestampField === 'string' ? spec.timestampField : null,
        idType,
        fields: spec.fields,
    };
}
