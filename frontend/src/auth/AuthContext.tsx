import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from "react";
import * as authApi from "../api/auth";
import { ApiError } from "../api/client";
import type { User } from "../types";

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  login: (identifier: string, password: string) => Promise<void>;
  register: (matricula: string, email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const fetchedOnce = useRef(false);

  useEffect(() => {
    // Evita que React.StrictMode dispare esta llamada dos veces en desarrollo:
    // dos GET /me casi simultáneos, sin sesión previa, pueden crear dos
    // sesiones distintas en Laravel antes de que exista una cookie estable,
    // lo que luego rompe la verificación CSRF del login.
    if (fetchedOnce.current) return;
    fetchedOnce.current = true;

    authApi
      .me()
      .then(({ data }) => setUser(data))
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  async function login(identifier: string, password: string) {
    const { data } = await authApi.login(identifier, password);
    setUser(data);
  }

  async function register(matricula: string, email: string, password: string) {
    const { data } = await authApi.register(matricula, email, password);
    setUser(data);
  }

  async function logout() {
    try {
      await authApi.logout();
    } catch (error) {
      // si el token/sesión ya expiró, igual limpiamos el estado local
      if (!(error instanceof ApiError) || error.status !== 401) throw error;
    }
    setUser(null);
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth debe usarse dentro de <AuthProvider>");
  return ctx;
}
