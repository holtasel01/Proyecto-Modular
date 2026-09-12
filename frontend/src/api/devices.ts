import { apiRequest } from "./client";
import type { Device } from "../types";

export function listDevices(): Promise<{ data: Device[] }> {
  return apiRequest<{ data: Device[] }>("/devices");
}

export function createDevice(nombre: string, ubicacion?: string): Promise<{ data: Device }> {
  return apiRequest<{ data: Device }>("/devices", { method: "POST", body: { nombre, ubicacion } });
}

export function revokeDevice(id: number): Promise<void> {
  return apiRequest<void>(`/devices/${id}`, { method: "DELETE" });
}
