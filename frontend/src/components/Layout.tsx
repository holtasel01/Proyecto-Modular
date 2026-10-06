import { NavLink, Outlet } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import {
  IconAlertTriangle,
  IconBarChart,
  IconCalendarCheck,
  IconCamera,
  IconClock,
  IconFlask,
  IconHome,
  IconLogout,
  IconSettings,
  IconTrendingUp,
  IconUsers,
} from "./icons";

const STUDENT_LINKS = [
  { to: "/", label: "Mi resumen", icon: IconHome },
  { to: "/historial", label: "Historial", icon: IconClock },
  { to: "/prediccion", label: "Predicción", icon: IconTrendingUp },
];

const ADMIN_LINKS = [
  { to: "/", label: "Laboratorio", icon: IconFlask },
  { to: "/estudiantes", label: "Estudiantes", icon: IconUsers },
  { to: "/asistencias", label: "Asistencias", icon: IconCalendarCheck },
  { to: "/incidencias", label: "Incidencias", icon: IconAlertTriangle },
  { to: "/dispositivos", label: "Dispositivos", icon: IconCamera },
  { to: "/configuracion", label: "Configuración", icon: IconSettings },
  { to: "/analitica", label: "Analítica", icon: IconBarChart },
];

export function Layout() {
  const { user, logout } = useAuth();
  const links = user?.role === "admin" ? ADMIN_LINKS : STUDENT_LINKS;

  const initials = (user?.name ?? "")
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <span className="sidebar-brand-mark">F</span>
          <span className="sidebar-brand-text">Facelog</span>
        </div>

        <nav className="sidebar-nav">
          {links.map(({ to, label, icon: Icon }) => (
            <NavLink key={to} to={to} end={to === "/"} className="sidebar-link">
              <Icon className="sidebar-icon" />
              <span className="sidebar-label">{label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-user">
          <div className="sidebar-avatar">{initials || "?"}</div>
          <div className="sidebar-user-info">
            <span className="sidebar-user-name">{user?.name}</span>
            <span className="sidebar-user-role">
              {user?.role === "admin" ? "Administrador" : "Estudiante"}
            </span>
          </div>
          <button
            type="button"
            className="sidebar-logout"
            title="Salir"
            onClick={() => void logout()}
          >
            <IconLogout />
          </button>
        </div>
      </aside>

      <main className="app-content">
        <Outlet />
      </main>
    </div>
  );
}
