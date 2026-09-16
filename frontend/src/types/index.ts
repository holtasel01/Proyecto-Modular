// Tipos que reflejan los API Resources de Laravel (docs/02-diseno.md §8).

export type Role = "student" | "admin";

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  studentId: number | null;
}

export interface Student {
  id: number;
  matricula: string;
  nombre: string;
  carrera: string | null;
  horasMeta: number;
  estado: "activo" | "inactivo";
  tieneEnrolamientoFacial?: boolean;
  createdAt: string;
}

export interface FaceProfile {
  existe: boolean;
  modelo?: string;
  createdAt?: string;
}

export type SessionStatus = "open" | "closed" | "inconsistent";

export interface AttendanceSession {
  id: number;
  studentId: number;
  startedAt: string;
  endedAt: string | null;
  durationMinutes: number | null;
  status: SessionStatus;
}

export interface AttendanceSummary {
  horasAcumuladas: number;
  horasMeta: number;
  progresoPorcentaje: number;
}

export interface LabStatusEntry {
  sessionId: number;
  studentId: number;
  matricula: string;
  nombre: string;
  startedAt: string;
}

export type IncidentType =
  | "entrada_sin_salida"
  | "duplicado"
  | "baja_confianza"
  | "salida_sin_entrada"
  | "corregido_manualmente";

export interface Incident {
  id: number;
  type: IncidentType;
  attendanceSessionId: number | null;
  attendanceEventId: number | null;
  description: string | null;
  status: "open" | "resolved";
  resolvedBy: number | null;
  resolvedAt: string | null;
  createdAt: string;
}

export interface Setting {
  key: string;
  value: string;
  updatedAt: string;
}

export interface Device {
  id: number;
  nombre: string;
  ubicacion: string | null;
  lastSeenAt: string | null;
  plainTextToken?: string;
}

export interface Paginated<T> {
  data: T[];
  meta: {
    currentPage: number;
    lastPage: number;
    total: number;
  };
}

export type ClusterLabel = "baja actividad" | "actividad moderada" | "alta actividad";

export interface StudentClusterFeatures {
  horasAcumuladas: number;
  numeroSesiones: number;
  duracionPromedioSesionMin: number;
  horasPromedioSemana: number;
  sesionesPromedioSemana: number;
  variabilidadSemanal: number;
}

export interface StudentCluster {
  studentId: number;
  matricula: string;
  nombre: string;
  cluster: number;
  clusterLabel: ClusterLabel;
  features: StudentClusterFeatures;
}

export interface ClusterCentroid extends StudentClusterFeatures {
  cluster: number;
  clusterLabel: ClusterLabel;
}

export interface StudentClusterResult {
  clusters: StudentCluster[];
  centroids: ClusterCentroid[];
}

export interface StudentPrediction {
  tieneDatos: boolean;
  horasAcumuladas: number;
  horasMeta: number;
  horasRestantes: number;
  progresoPorcentaje: number;
  metaCumplida: boolean;
  numeroSesiones: number;
  duracionPromedioSesionMin: number | null;
  horasPromedioSemana: number | null;
  horasPromedioDia: number | null;
  sesionesPromedioSemana: number | null;
  variabilidadSemanal: number | null;
  semanasRestantesEstimadas: number | null;
  diasRestantesEstimados: number | null;
  sesionesRestantesEstimadas: number | null;
  fechaEstimadaFinalizacion: string | null;
}
