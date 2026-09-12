// Convierte entre snake_case (API de Laravel) y camelCase (frontend) — ver
// docs/02-diseno.md §8 ("Consistencia de nombres entre capas").

function snakeToCamel(key: string): string {
  return key.replace(/_([a-z0-9])/g, (_, c: string) => c.toUpperCase());
}

function camelToSnake(key: string): string {
  return key.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`);
}

function transformKeys(value: unknown, convert: (key: string) => string): unknown {
  if (Array.isArray(value)) {
    return value.map((item) => transformKeys(item, convert));
  }

  if (value !== null && typeof value === "object" && !(value instanceof File)) {
    return Object.fromEntries(
      Object.entries(value as Record<string, unknown>).map(([key, val]) => [
        convert(key),
        transformKeys(val, convert),
      ]),
    );
  }

  return value;
}

export function toCamel<T>(value: unknown): T {
  return transformKeys(value, snakeToCamel) as T;
}

export function toSnake(value: unknown): unknown {
  return transformKeys(value, camelToSnake);
}
