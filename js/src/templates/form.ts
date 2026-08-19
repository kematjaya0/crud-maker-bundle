import type { CrudSpec, FieldSpec } from '../spec.js';
import type { Names } from '../naming.js';
import { humanize } from '../naming.js';

function fieldMarkup(field: FieldSpec, entitiesKebab: string, autoFocus: boolean): string {
    const label = humanize(field.name);
    const id = `${entitiesKebab}-${field.name}`;
    const autoFocusProp = autoFocus ? '\n                    autoFocus={mode === \'create\'}' : '';

    if (field.type === 'textarea') {
        return `                <TextareaField
                    id="${id}"
                    label="${label}"
                    rows={6}${field.maxLength !== null ? `\n                    maxLength={${field.maxLength}}` : ''}
                    error={errors.${field.name}}
                    registration={register('${field.name}')}
                />`;
    }

    if (field.type === 'boolean') {
        return `                <div className="form-check mb-3">
                    <input
                        id="${id}"
                        type="checkbox"
                        className="form-check-input"
                        {...register('${field.name}')}
                    />
                    <label htmlFor="${id}" className="form-check-label">
                        ${label}
                    </label>
                </div>`;
    }

    if (field.type === 'number') {
        return `                <TextField
                    id="${id}"
                    label="${label}"
                    type="number"${autoFocusProp}
                    error={errors.${field.name}}
                    registration={register('${field.name}', { valueAsNumber: true })}
                />`;
    }

    return `                <TextField
                    id="${id}"
                    label="${label}"
                    type="text"${field.maxLength !== null ? `\n                    maxLength={${field.maxLength}}` : ''}
                    autoComplete="off"${autoFocusProp}
                    error={errors.${field.name}}
                    registration={register('${field.name}')}
                />`;
}

export function form(spec: CrudSpec, names: Names): string {
    const { entityPascal, entityCamel, entitiesKebab } = names;
    const usesTextarea = spec.fields.some((f) => f.type === 'textarea');
    const usesText = spec.fields.some((f) => f.type === 'text' || f.type === 'number');
    const fieldComponents = [usesText ? 'TextField' : null, usesTextarea ? 'TextareaField' : null]
        .filter((c): c is string => c !== null)
        .join(', ');

    const fieldsMarkup = spec.fields
        .map((f, i) => fieldMarkup(f, entitiesKebab, i === 0))
        .join('\n');

    const resetFields = spec.fields.map((f) => `${f.name}: data.${f.name}`).join(', ');

    return `'use client';

import { zodResolver } from '@hookform/resolvers/zod';
import { Button, ListPageCard${fieldComponents ? `, ${fieldComponents}` : ''} } from '@kematjaya/bootstrap-ui-kit';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { is${entityPascal} } from '@/lib/api-shapes';
import { ${entityCamel}Schema, type ${entityPascal}FormValues } from '@/lib/schemas';

type Props = {
    mode: 'create' | 'edit';
    ${entityCamel}Id?: string;
};

export function ${entityPascal}Form({ mode, ${entityCamel}Id }: Props) {
    const router = useRouter();
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(mode === 'edit');
    const {
        register,
        handleSubmit,
        reset,
        formState: { errors, isSubmitting }
    } = useForm<${entityPascal}FormValues>({
        resolver: zodResolver(${entityCamel}Schema),
        mode: 'onBlur',
        reValidateMode: 'onChange'
    });

    useEffect(() => {
        if (mode !== 'edit' || !${entityCamel}Id) return;
        let cancelled = false;
        (async () => {
            let response: Response;
            try {
                response = await fetch(\`/api/${entitiesKebab}/\${${entityCamel}Id}\`, { cache: 'no-store' });
            } catch {
                if (!cancelled) setError('Network error while loading the record. Check your connection and try again.');
                return;
            }
            if (response.status === 401) {
                router.push('/login');
                return;
            }
            if (!response.ok) {
                if (!cancelled) setError('Could not load the record. Try again.');
                return;
            }
            const data: unknown = await response.json();
            if (!is${entityPascal}(data)) {
                if (!cancelled) setError('Unexpected response.');
                return;
            }
            if (!cancelled) {
                reset({ ${resetFields} });
                setLoading(false);
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [mode, ${entityCamel}Id, reset, router]);

    async function submit(values: ${entityPascal}FormValues) {
        setError('');
        const path = mode === 'edit' ? \`/api/${entitiesKebab}/\${${entityCamel}Id}\` : '/api/${entitiesKebab}';
        let response: Response;
        try {
            response = await fetch(path, {
                method: mode === 'edit' ? 'PATCH' : 'POST',
                headers: { 'content-type': 'application/json' },
                body: JSON.stringify(values)
            });
        } catch {
            setError('Network error while saving. Check your connection and try again.');
            return;
        }
        if (!response.ok) {
            setError('Could not save. Try again.');
            return;
        }
        const toast = mode === 'edit' ? 'Updated.' : 'Created.';
        router.push(\`/dashboard/${entitiesKebab}?toast=\${encodeURIComponent(toast)}\`);
    }

    if (loading) {
        return <p style={{ fontSize: 14, color: 'var(--color-fog)' }}>Loading...</p>;
    }

    return (
        <ListPageCard
            title={mode === 'edit' ? 'Edit' : 'New'}
            error={error}
            style={{ maxWidth: 640 }}
        >
            <form onSubmit={handleSubmit(submit)} noValidate>
${fieldsMarkup}
                <div className="d-flex gap-2">
                    <Button type="submit" disabled={isSubmitting}>
                        {isSubmitting ? 'Saving...' : mode === 'edit' ? 'Save changes' : 'Create'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.push('/dashboard/${entitiesKebab}')}
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </ListPageCard>
    );
}
`;
}
