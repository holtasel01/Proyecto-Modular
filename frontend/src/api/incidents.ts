import { apiRequest } from "./client";
import type { Incident, Paginated } from "../types";

export function listIncidents(filters: { status?: string; type?: string } = {}) {
  const params = new URLSearchParams();
  if (filters.status) params.set("status", filters.status);
  if (filters.type) params.set("type", filters.type);
  const query = params.toString();
  return apiRequest<Paginated<Incident>>(`/incidents${query ? `?${query}` : ""}`);
}

export function resolveIncident(id: number, resolutionNote: string) {
  return apiRequest<{ data: Incident }>(`/incidents/${id}`, {
    method: "PATCH",
    body: { resolutionNote },
  });
}
