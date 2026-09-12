// Cliente HTTP base para hablar con la API de Laravel (Sanctum SPA auth).
// docs/02-diseno.md §7: hace falta pedir la cookie CSRF antes de cualquier
// request que mute estado, y mandar su valor de vuelta en X-XSRF-TOKEN.

import { toCamel, toSnake } from "./case";

const API_ROOT = (import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000").replace(/\/$/, "");
const API_BASE = `${API_ROOT}/api`;

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${API_ROOT}/sanctum/csrf-cookie`, { credentials: "include" });
}

interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? "GET";

  if (method !== "GET") {
    await ensureCsrfCookie();
  }

  const headers: Record<string, string> = { Accept: "application/json" };
  const xsrfToken = readCookie("XSRF-TOKEN");
  if (xsrfToken) {
    headers["X-XSRF-TOKEN"] = xsrfToken;
  }

  let body: BodyInit | undefined;
  if (options.body instanceof FormData) {
    body = options.body;
  } else if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(toSnake(options.body));
  }

  const response = await fetch(`${API_BASE}${path}`, {
    method,
    credentials: "include",
    headers,
    body,
  });

  const text = await response.text();
  const data = text ? JSON.parse(text) : null;

  if (!response.ok) {
    throw new ApiError(
      response.status,
      (data && data.message) || "Ocurrió un error inesperado.",
      data?.errors,
    );
  }

  return toCamel<T>(data);
}

/** Para PUT/PATCH con archivos: PHP no parsea multipart en esos verbos, así
 * que se envía como POST con spoofing de método (convención de Laravel). */
export function withMethodSpoof(formData: FormData, method: "PUT" | "PATCH"): FormData {
  formData.append("_method", method);
  return formData;
}
