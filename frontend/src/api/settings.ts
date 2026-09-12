import { apiRequest } from "./client";
import type { Setting } from "../types";

export function listSettings(): Promise<{ data: Setting[] }> {
  return apiRequest<{ data: Setting[] }>("/settings");
}

export function updateSettings(values: Record<string, string | number>): Promise<{ data: Setting[] }> {
  return apiRequest<{ data: Setting[] }>("/settings", { method: "PATCH", body: values });
}
