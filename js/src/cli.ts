#!/usr/bin/env node
import { join, resolve } from 'node:path';
import { loadSpec } from './spec.js';
import { namesFromSpec, searchField, searchableFields } from './naming.js';
import { appendBlock, appendBlockWithImport, newLog, writeIfMissing, writeNewFile, type WriteLog } from './write.js';
import * as shared from './templates/sharedComponents.js';
import { editPage, listPage, newPage } from './templates/pages.js';
import { table } from './templates/table.js';
import { form } from './templates/form.js';
import { useExportHook } from './templates/useExport.js';
import { queryLib } from './templates/queryLib.js';
import { csvLib } from './templates/csvLib.js';
import { apiShapesBlock, apiShapesFileHeader, apiShapesImport, apiShapesMarker } from './templates/apiShapes.js';
import { schemasBlock, schemasFileHeader, schemasMarker } from './templates/schemas.js';
import { typesApiBlock, typesApiFileHeader, typesApiMarker } from './templates/typesApi.js';
import { exportRoute, itemRoute, listRoute } from './templates/bffRoutes.js';

function parseArgs(argv: string[]): { specPath: string; srcDir: string } {
    const positional: string[] = [];
    let srcDir = 'src';

    for (let i = 0; i < argv.length; i++) {
        const arg = argv[i];
        if (arg === '--src') {
            const next = argv[++i];
            if (!next) throw new Error('--src requires a value');
            srcDir = next;
        } else if (arg === '--help' || arg === '-h') {
            printUsage();
            process.exit(0);
        } else {
            positional.push(arg);
        }
    }

    const specPath = positional[0];
    if (!specPath) {
        printUsage();
        throw new Error('Missing required <spec-path> argument.');
    }

    return { specPath, srcDir };
}

function printUsage(): void {
    console.log(`Usage: crud-ui-generate <spec-path> [--src <dir>]

  <spec-path>   Path to the crud-specs/{Entity}.json sidecar written by
                kematjaya/crud-maker-bundle's "make:kmj-api-crud".
  --src <dir>   Frontend src/ directory to generate into (default: "src").

Example:
  npx @kematjaya/crud-ui-generator ../backend/crud-specs/Note.json --src src
`);
}

function report(log: WriteLog): void {
    for (const path of log.created) console.log(`  created   ${path}`);
    for (const path of log.appended) console.log(`  appended  ${path}`);
    for (const path of log.skipped) console.log(`  skip      ${path} (already exists)`);
}

function main(): void {
    const { specPath, srcDir } = parseArgs(process.argv.slice(2));
    const spec = loadSpec(resolve(specPath));
    const names = namesFromSpec(spec);
    const src = resolve(srcDir);
    const log = newLog();

    const cruddir = join(src, 'components', 'crud');
    writeIfMissing(join(cruddir, 'DeleteConfirmModal.tsx'), shared.deleteConfirmModal, log);
    writeIfMissing(join(cruddir, 'BulkActionsBar.tsx'), shared.bulkActionsBar, log);
    writeIfMissing(join(cruddir, 'PaginationBar.tsx'), shared.paginationBar, log);
    writeIfMissing(join(cruddir, 'ExportAllButton.tsx'), shared.exportAllButton, log);
    if (null !== searchField(spec)) {
        writeIfMissing(join(cruddir, 'SearchPanel.tsx'), shared.searchPanel, log);
    }

    const dashDir = join(src, 'app', 'dashboard', names.entitiesKebab);
    writeNewFile(join(dashDir, 'page.tsx'), listPage(names), log);
    writeNewFile(join(dashDir, 'new', 'page.tsx'), newPage(names), log);
    writeNewFile(join(dashDir, '[id]', 'edit', 'page.tsx'), editPage(names), log);

    const compDir = join(src, 'components', names.entitiesKebab);
    writeNewFile(join(compDir, `${names.entityPascal}Table.tsx`), table(spec, names), log);
    writeNewFile(join(compDir, `${names.entityPascal}Form.tsx`), form(spec, names), log);
    writeNewFile(join(compDir, `use${names.entitiesPascal}Export.ts`), useExportHook(spec, names), log);

    const libDir = join(src, 'lib');
    writeNewFile(join(libDir, `${names.entitiesKebab}-query.ts`), queryLib(spec, names), log);
    writeNewFile(join(libDir, `${names.entitiesKebab}-csv.ts`), csvLib(spec, names), log);

    appendBlockWithImport(
        join(libDir, 'api-shapes.ts'),
        apiShapesFileHeader,
        apiShapesImport(names),
        apiShapesMarker(names.entityPascal),
        apiShapesBlock(spec, names),
        log,
    );
    appendBlock(
        join(libDir, 'schemas.ts'),
        schemasFileHeader,
        schemasMarker(names.entityCamel),
        schemasBlock(spec, names),
        log,
    );
    appendBlock(
        join(src, 'types', 'api.ts'),
        typesApiFileHeader,
        typesApiMarker(names.entityPascal),
        typesApiBlock(spec, names),
        log,
    );

    const apiDir = join(src, 'app', 'api', names.entitiesKebab);
    writeNewFile(join(apiDir, 'route.ts'), listRoute(spec, names), log);
    writeNewFile(join(apiDir, '[id]', 'route.ts'), itemRoute(spec, names), log);
    writeNewFile(join(apiDir, 'export', 'route.ts'), exportRoute(spec, names), log);

    report(log);

    console.log('');
    console.log(`Generated ${names.entityPascal} CRUD UI. Before it works end-to-end:`);
    console.log(`  1. Run the backend maker's printed next-steps (ApiResource/ApiFilter attributes, permission keys, rate limiter config).`);
    console.log(`  2. Run "npm run api:types" in the frontend project so src/types/api.ts's paths/components lookups resolve.`);
    console.log(`  3. Confirm the OpenAPI collection path is "/api/${names.entitiesKebab}" — if the entity's #[ApiResource] uses a custom uriTemplate, fix the "paths[...]" lookups in the appended src/types/api.ts block by hand.`);
    if (searchableFields(spec).length > 1) {
        console.log(`  4. Note: multiple searchable fields were configured (${searchableFields(spec).join(', ')}); the list view's single search box only queries by "${searchField(spec)}" (ApiPlatform SearchFilter's per-property param convention doesn't support one box matching several properties). The export endpoint does OR across all of them.`);
    }
    console.log(`  Id type: "${spec.idType}" (from the spec's "idType") — validId() in the generated app/api/${names.entitiesKebab}/[id]/route.ts was generated to match.`);
    console.log(`  Run "npm run format" afterwards — generated files aren't pre-formatted to this project's Prettier config.`);
}

main();
