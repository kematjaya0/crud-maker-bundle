/**
 * Written once (via `writeIfMissing`, shared across every generated entity that has a
 * `datetime` field) into `src/lib/datetime.ts` — converts between the API's RFC3339 wire value
 * (`2026-09-03T14:30:00+07:00`) and `<input type="datetime-local">`'s native value
 * (`2026-09-03T14:30`, local wall-clock, no offset). Both directions go through the `Date`
 * constructor, which parses a string with no offset suffix as LOCAL time per spec — that makes
 * `fromDatetimeLocalValue`'s `.toISOString()` call a correct, timezone-safe local -> UTC
 * conversion, not a naive string transform.
 */
export const dateLib = `function pad(n: number): string {
    return String(n).padStart(2, '0');
}

export function toDatetimeLocalValue(iso: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return \`\${date.getFullYear()}-\${pad(date.getMonth() + 1)}-\${pad(date.getDate())}T\${pad(date.getHours())}:\${pad(date.getMinutes())}\`;
}

export function fromDatetimeLocalValue(local: string): string {
    const date = new Date(local);
    if (Number.isNaN(date.getTime())) return local;
    return date.toISOString();
}
`;
