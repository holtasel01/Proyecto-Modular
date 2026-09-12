import { apiRequest, withMethodSpoof } from "./client";
import type { FaceProfile, Paginated, Student } from "../types";

export function listStudents(search?: string): Promise<Paginated<Student>> {
  const query = search ? `?search=${encodeURIComponent(search)}` : "";
  return apiRequest<Paginated<Student>>(`/students${query}`);
}

export function getStudent(id: number): Promise<{ data: Student }> {
  return apiRequest<{ data: Student }>(`/students/${id}`);
}

export interface StudentInput {
  matricula: string;
  nombre: string;
  carrera?: string;
  horasMeta?: number;
}

export function createStudent(input: StudentInput): Promise<{ data: Student }> {
  return apiRequest<{ data: Student }>("/students", { method: "POST", body: input });
}

export function updateStudent(id: number, input: Partial<StudentInput & { estado: string }>) {
  return apiRequest<{ data: Student }>(`/students/${id}`, { method: "PATCH", body: input });
}

export function getFaceProfile(studentId: number): Promise<FaceProfile> {
  return apiRequest<FaceProfile>(`/students/${studentId}/face-profile`);
}

export function uploadFacePhoto(studentId: number, photo: File): Promise<FaceProfile> {
  const formData = new FormData();
  formData.append("photo", photo);
  return apiRequest<FaceProfile>(`/students/${studentId}/face-photo`, {
    method: "POST",
    body: formData,
  });
}

export function replaceFacePhoto(studentId: number, photo: File): Promise<FaceProfile> {
  const formData = new FormData();
  formData.append("photo", photo);
  return apiRequest<FaceProfile>(`/students/${studentId}/face-photo`, {
    method: "POST",
    body: withMethodSpoof(formData, "PUT"),
  });
}
