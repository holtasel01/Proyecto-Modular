import { apiRequest } from "./client";
import type { User } from "../types";

export function login(identifier: string, password: string): Promise<{ data: User }> {
  return apiRequest<{ data: User }>("/login", { method: "POST", body: { identifier, password } });
}

export function register(
  matricula: string,
  email: string,
  password: string,
): Promise<{ data: User }> {
  return apiRequest<{ data: User }>("/register", {
    method: "POST",
    body: { matricula, email, password },
  });
}

export function logout(): Promise<void> {
  return apiRequest<void>("/logout", { method: "POST" });
}

export function me(): Promise<{ data: User }> {
  return apiRequest<{ data: User }>("/me");
}
