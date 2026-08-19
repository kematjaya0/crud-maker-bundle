import type { Names } from '../naming.js';

export function listPage({ entitiesKebab, entityPascal, entitiesPascal }: Names): string {
    return `import { Suspense } from 'react';
import { ${entityPascal}Table } from '@/components/${entitiesKebab}/${entityPascal}Table';
import { requirePermission } from '@/lib/permissions';

export default async function ${entitiesPascal}Page() {
    await requirePermission('${entitiesKebab}');
    return (
        <Suspense>
            <${entityPascal}Table />
        </Suspense>
    );
}
`;
}

export function newPage({ entitiesKebab, entityPascal }: Names): string {
    return `import { ${entityPascal}Form } from '@/components/${entitiesKebab}/${entityPascal}Form';
import { requirePermission } from '@/lib/permissions';

export default async function New${entityPascal}Page() {
    await requirePermission('${entitiesKebab}.create');
    return <${entityPascal}Form mode="create" />;
}
`;
}

export function editPage({ entitiesKebab, entityPascal, entityCamel }: Names): string {
    return `import { ${entityPascal}Form } from '@/components/${entitiesKebab}/${entityPascal}Form';
import { requirePermission } from '@/lib/permissions';

type Props = { params: Promise<{ id: string }> };

export default async function Edit${entityPascal}Page({ params }: Props) {
    await requirePermission('${entitiesKebab}.edit');
    const { id } = await params;
    return <${entityPascal}Form mode="edit" ${entityCamel}Id={id} />;
}
`;
}
