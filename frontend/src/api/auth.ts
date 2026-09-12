import { apiRequest } from "./client";
import type { User } from "../types";

export function login(email: string, password: string): Promise<{ data: User }> {
  return apiRequest<{ data: User }>("/login", { method: "POST", body: { email, password } });
}

export function logout(): Promise<void> {
  return apiRequest<void>("/logout", { method: "POST" });
}

export function me(): Promise<{ data: User }> {
  return apiRequest<{ data: User }>("/me");
}
