import { apiRequest } from "./client";
import type { AttendanceSession, AttendanceSummary, LabStatusEntry, Paginated } from "../types";

export function myAttendance(): Promise<Paginated<AttendanceSession>> {
  return apiRequest<Paginated<AttendanceSession>>("/me/attendance");
}

export function mySummary(): Promise<AttendanceSummary> {
  return apiRequest<AttendanceSummary>("/me/summary");
}

export interface SessionFilters {
  studentId?: number;
  status?: string;
  from?: string;
  to?: string;
}

export function listSessions(filters: SessionFilters = {}): Promise<Paginated<AttendanceSession>> {
  const params = new URLSearchParams();
  if (filters.studentId) params.set("student_id", String(filters.studentId));
  if (filters.status) params.set("status", filters.status);
  if (filters.from) params.set("from", filters.from);
  if (filters.to) params.set("to", filters.to);
  const query = params.toString();
  return apiRequest<Paginated<AttendanceSession>>(`/attendance/sessions${query ? `?${query}` : ""}`);
}

export interface SessionCorrection {
  startedAt?: string;
  endedAt?: string | null;
  status?: string;
  reason: string;
}

export function correctSession(id: number, correction: SessionCorrection) {
  return apiRequest<{ data: AttendanceSession }>(`/attendance/sessions/${id}`, {
    method: "PATCH",
    body: correction,
  });
}

export function labStatus(): Promise<{ estudiantesDentro: LabStatusEntry[] }> {
  return apiRequest<{ estudiantesDentro: LabStatusEntry[] }>("/lab/status");
}
