import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

export type WriteLog = { created: string[]; skipped: string[]; appended: string[] };

export function newLog(): WriteLog {
    return { created: [], skipped: [], appended: [] };
}

/** Writes a file, creating parent directories as needed. Refuses to clobber an existing file. */
export function writeNewFile(path: string, contents: string, log: WriteLog): void {
    if (existsSync(path)) {
        log.skipped.push(path);
        return;
    }
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, contents, 'utf8');
    log.created.push(path);
}

/** Writes a file only if it doesn't exist yet — for shared, entity-agnostic components. */
export function writeIfMissing(path: string, contents: string, log: WriteLog): void {
    if (existsSync(path)) {
        log.skipped.push(path);
        return;
    }
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, contents, 'utf8');
    log.created.push(path);
}

/**
 * Appends a generated block to a shared, multi-entity file (created fresh if missing), keyed by
 * `marker` so re-running the generator for the same entity is a no-op instead of duplicating the
 * block. Used for lib/api-shapes.ts, lib/schemas.ts, and types/api.ts, which accumulate one block
 * per entity across generator runs.
 */
export function appendBlock(
    path: string,
    header: string,
    marker: string,
    block: string,
    log: WriteLog,
): void {
    if (!existsSync(path)) {
        mkdirSync(dirname(path), { recursive: true });
        writeFileSync(path, header + block, 'utf8');
        log.created.push(path);
        return;
    }

    const current = readFileSync(path, 'utf8');
    if (current.includes(marker)) {
        log.skipped.push(path);
        return;
    }

    writeFileSync(path, current.replace(/\s*$/, '\n') + block, 'utf8');
    log.appended.push(path);
}

/**
 * Like {@link appendBlock}, but for files where each entity's block also needs an `import`
 * line — keeping those at the top of the file (after the last existing import) instead of
 * scattered mid-file, which `appendBlock` alone would produce.
 */
export function appendBlockWithImport(
    path: string,
    header: string,
    importLine: string,
    marker: string,
    block: string,
    log: WriteLog,
): void {
    if (!existsSync(path)) {
        mkdirSync(dirname(path), { recursive: true });
        writeFileSync(path, header + importLine + block, 'utf8');
        log.created.push(path);
        return;
    }

    const current = readFileSync(path, 'utf8');
    if (current.includes(marker)) {
        log.skipped.push(path);
        return;
    }

    const importLines = [...current.matchAll(/^import .*$/gm)];
    const lastImport = importLines.at(-1);
    const insertAt = lastImport ? lastImport.index! + lastImport[0].length : 0;
    const withImport =
        current.slice(0, insertAt) + '\n' + importLine.trimEnd() + current.slice(insertAt);

    writeFileSync(path, withImport.replace(/\s*$/, '\n') + block, 'utf8');
    log.appended.push(path);
}
