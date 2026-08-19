import type { CrudSpec, FieldSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { displayFields, searchField } from '../naming.js';

function tsType(field: FieldSpec): string {
    if (field.type === 'number') return 'number';
    if (field.type === 'boolean') return 'boolean';
    return 'string';
}

export function listRoute(_spec: CrudSpec, names: Names): string {
    const { entityCamel, entitiesKebab } = names;
    return `import type { NextRequest } from 'next/server';
import { authedBackend } from '@/lib/bff';
import { parseJson, validateOrigin } from '@/lib/http';
import { build${names.entitiesPascal}ApiPath, parse${names.entitiesPascal}Query } from '@/lib/${entitiesKebab}-query';
import { ${entityCamel}Schema } from '@/lib/schemas';

export async function GET(request: NextRequest) {
    return authedBackend(
        build${names.entitiesPascal}ApiPath(parse${names.entitiesPascal}Query(request.nextUrl.searchParams))
    );
}

export async function POST(request: NextRequest) {
    const badOrigin = validateOrigin(request);
    if (badOrigin) return badOrigin;
    const parsed = await parseJson(request, ${entityCamel}Schema);
    if ('error' in parsed) return parsed.error;
    return authedBackend('/api/${entitiesKebab}', {
        method: 'POST',
        body: JSON.stringify(parsed.data)
    });
}

export const dynamic = 'force-dynamic';
export const revalidate = 0;
`;
}

export function itemRoute(_spec: CrudSpec, names: Names): string {
    const { entityCamel, entitiesKebab } = names;
    return `import type { NextRequest } from 'next/server';
import { authedBackend } from '@/lib/bff';
import { jsonProblem, parseJson, validateOrigin } from '@/lib/http';
import { ${entityCamel}Schema } from '@/lib/schemas';

type Params = { params: Promise<{ id: string }> };

function validId(id: string) {
    return /^[0-9a-fA-F-]{36}$/.test(id);
}

export async function GET(_request: NextRequest, context: Params) {
    const { id } = await context.params;
    if (!validId(id)) return jsonProblem(404, { title: 'Not Found' });
    return authedBackend(\`/api/${entitiesKebab}/\${id}\`);
}

export async function PATCH(request: NextRequest, context: Params) {
    const badOrigin = validateOrigin(request);
    if (badOrigin) return badOrigin;
    const { id } = await context.params;
    if (!validId(id)) return jsonProblem(404, { title: 'Not Found' });
    const parsed = await parseJson(request, ${entityCamel}Schema);
    if ('error' in parsed) return parsed.error;
    // Backend only exposes a Put operation (full replacement) — no Patch operation exists,
    // so the upstream call uses PUT even though the BFF's own contract to the browser stays PATCH.
    return authedBackend(\`/api/${entitiesKebab}/\${id}\`, {
        method: 'PUT',
        body: JSON.stringify(parsed.data)
    });
}

export async function DELETE(request: NextRequest, context: Params) {
    const badOrigin = validateOrigin(request);
    if (badOrigin) return badOrigin;
    const { id } = await context.params;
    if (!validId(id)) return jsonProblem(404, { title: 'Not Found' });
    return authedBackend(\`/api/${entitiesKebab}/\${id}\`, { method: 'DELETE' });
}

export const dynamic = 'force-dynamic';
export const revalidate = 0;
`;
}

export function exportRoute(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesKebab } = names;
    const field = searchField(spec);
    const cols = displayFields(spec).map((f) => f.name);
    if (null !== spec.timestampField && !cols.includes(spec.timestampField)) {
        cols.push(spec.timestampField);
    }
    const fieldsByName = new Map(spec.fields.map((f) => [f.name, f]));
    const exportFieldsType = cols
        .map((c) => `    ${c}: ${c === spec.timestampField ? 'string' : tsType(fieldsByName.get(c) ?? { type: 'text' } as FieldSpec)};`)
        .join('\n');
    const guardLines = cols.flatMap((c) => {
        const t = c === spec.timestampField ? 'string' : tsType(fieldsByName.get(c) ?? { type: 'text' } as FieldSpec);
        return [`'${c}' in value`, `typeof value.${c} === '${t}'`];
    });
    const guardChecks = guardLines.map((line, i) => `        ${line}${i < guardLines.length - 1 ? ' &&' : ''}`).join('\n');

    return `import { type NextRequest, NextResponse } from 'next/server';
import { authedBackend } from '@/lib/bff';
import { jsonProblem } from '@/lib/http';
import { build${entitiesPascal}Csv } from '@/lib/${entitiesKebab}-csv';
import { parse${entitiesPascal}Query } from '@/lib/${entitiesKebab}-query';

type Export${entityPascal} = {
${exportFieldsType}
};

type Export${entitiesPascal}Collection = {
    totalItems: number;
    member: Export${entityPascal}[];
};

function isExport${entityPascal}(value: unknown): value is Export${entityPascal} {
    return (
        typeof value === 'object' &&
        value !== null &&
${guardChecks}
    );
}

function isExport${entitiesPascal}Collection(value: unknown): value is Export${entitiesPascal}Collection {
    return (
        typeof value === 'object' &&
        value !== null &&
        'totalItems' in value &&
        typeof value.totalItems === 'number' &&
        Number.isSafeInteger(value.totalItems) &&
        0 <= value.totalItems &&
        'member' in value &&
        Array.isArray(value.member) &&
        value.member.every(isExport${entityPascal})
    );
}

function buildExportPath(${field ? 'search: string' : ''}): string {
${field ? `    const params = new URLSearchParams();
    if (search) params.set('search', search);
    const query = params.toString();
    return query
        ? \`/api/${entitiesKebab}/export-data?\${query}\`
        : '/api/${entitiesKebab}/export-data';` : `    return '/api/${entitiesKebab}/export-data';`}
}

export async function GET(request: NextRequest): Promise<Response> {
    try {
${field ? `        const { search } = parse${entitiesPascal}Query(request.nextUrl.searchParams);
        const response = await authedBackend(buildExportPath(search));` : `        const response = await authedBackend(buildExportPath());`}
        if (!response.ok) return response;

        const data: unknown = await response.json();
        if (!isExport${entitiesPascal}Collection(data)) throw new Error('Invalid export data');

        const date = new Date().toISOString().slice(0, 10);
        return new NextResponse(build${entitiesPascal}Csv(data.member), {
            status: 200,
            headers: {
                'Content-Type': 'text/csv; charset=utf-8',
                'Content-Disposition': \`attachment; filename="${entitiesKebab}-export-all-\${date}.csv"\`,
                'Cache-Control': 'no-store'
            }
        });
    } catch {
        return jsonProblem(502, {
            title: 'Bad Gateway',
            detail: 'Unexpected ${entitiesKebab} response.'
        });
    }
}

export const dynamic = 'force-dynamic';
export const revalidate = 0;
`;
}
