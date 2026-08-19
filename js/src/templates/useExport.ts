import type { CrudSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { searchField } from '../naming.js';

export function useExportHook(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesCamel, entitiesKebab } = names;
    const field = searchField(spec);

    return `import { useState } from 'react';
import { build${entitiesPascal}Csv } from '@/lib/${entitiesKebab}-csv';
import type { ${entitiesPascal}Query } from '@/lib/${entitiesKebab}-query';
import type { ${entityPascal} } from '@/types/api';

type ExportFailure = {
    ok: false;
    message: string;
    status?: number;
};

type ExportResult = { ok: true } | ExportFailure;

function exportDate(): string {
    return new Date().toISOString().slice(0, 10);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && null !== value;
}

async function exportError(response: Response): Promise<ExportFailure> {
    let data: unknown;
    try {
        data = await response.json();
    } catch {
        return {
            ok: false,
            status: response.status,
            message: 'Could not export ${entitiesCamel}.'
        };
    }

    const detail = isRecord(data) ? data.detail : undefined;
    const title = isRecord(data) ? data.title : undefined;
    return {
        ok: false,
        status: response.status,
        message:
            typeof detail === 'string'
                ? detail
                : typeof title === 'string'
                  ? title
                  : 'Could not export ${entitiesCamel}.'
    };
}

function triggerDownload(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.style.display = 'none';
    document.body.append(link);
    try {
        link.click();
    } finally {
        link.remove();
        URL.revokeObjectURL(url);
    }
}

export function use${entitiesPascal}Export() {
    const [exportingSelected, setExportingSelected] = useState(false);
    const [exportingAll, setExportingAll] = useState(false);

    function exportSelected(${entitiesCamel}: ${entityPascal}[]): ExportResult {
        if (0 === ${entitiesCamel}.length) {
            return { ok: false, message: 'Select ${entitiesCamel} to export.' };
        }

        setExportingSelected(true);
        try {
            triggerDownload(
                new Blob([build${entitiesPascal}Csv(${entitiesCamel})], {
                    type: 'text/csv;charset=utf-8'
                }),
                \`${entitiesKebab}-export-selected-\${exportDate()}.csv\`
            );
            return { ok: true };
        } catch {
            return { ok: false, message: 'Could not export selected ${entitiesCamel}.' };
        } finally {
            setExportingSelected(false);
        }
    }

    async function exportAll(query: ${entitiesPascal}Query): Promise<ExportResult> {
        setExportingAll(true);
        const params = new URLSearchParams();
${field !== null ? "        if ('' !== query.search) params.set('search', query.search);\n" : ''}        const path = params.size
            ? \`/api/${entitiesKebab}/export?\${params.toString()}\`
            : '/api/${entitiesKebab}/export';

        try {
            const response = await fetch(path, { cache: 'no-store' });
            if (!response.ok) return exportError(response);

            triggerDownload(
                await response.blob(),
                \`${entitiesKebab}-export-all-\${exportDate()}.csv\`
            );
            return { ok: true };
        } catch {
            return {
                ok: false,
                message: 'Network error while exporting ${entitiesCamel}.'
            };
        } finally {
            setExportingAll(false);
        }
    }

    return { exportingSelected, exportingAll, exportSelected, exportAll };
}
`;
}
