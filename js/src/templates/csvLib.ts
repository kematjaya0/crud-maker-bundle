import type { CrudSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { displayFields, humanize } from '../naming.js';

export function csvLib(spec: CrudSpec, names: Names): string {
    const { entityPascal, entitiesPascal, entitiesCamel } = names;
    const fields = displayFields(spec).map((f) => f.name);
    if (null !== spec.timestampField) fields.push(spec.timestampField);

    const pickList = fields.map((f) => `'${f}'`).join(' | ');
    const header = fields.map((f) => humanize(f)).join(',');
    const row = fields.map((f) => `toCsvField(String(item.${f}))`).join(', ');

    return `import type { ${entityPascal} } from '@/types/api';

const CSV_FORMULA_PREFIX = /^[\\s\\p{Cc}]*[=+\\-@]/u;
const CSV_SPECIAL_CHARACTER = /[",\\r\\n]/;

type Csv${entityPascal} = Pick<${entityPascal}, ${pickList}>;

export function toCsvField(value: string): string {
    const safeValue = CSV_FORMULA_PREFIX.test(value) ? \`'\${value}\` : value;
    return CSV_SPECIAL_CHARACTER.test(safeValue)
        ? \`"\${safeValue.replaceAll('"', '""')}"\`
        : safeValue;
}

export function build${entitiesPascal}Csv(${entitiesCamel}: readonly Csv${entityPascal}[]): string {
    const rows = ${entitiesCamel}.map((item) => [${row}].join(','));

    return \`\\uFEFF\${['${header}', ...rows].join('\\r\\n')}\`;
}
`;
}
