import { NavLink, Outlet } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

export function Layout() {
  const { user, logout } = useAuth();

  return (
    <div className="app-shell">
      <header className="app-header">
        <span className="brand">Facelog</span>

        <nav>
          {user?.role === "student" && (
            <>
              <NavLink to="/">Mi resumen</NavLink>
              <NavLink to="/historial">Historial</NavLink>
            </>
          )}
          {user?.role === "admin" && (
            <>
              <NavLink to="/">Laboratorio</NavLink>
              <NavLink to="/estudiantes">Estudiantes</NavLink>
              <NavLink to="/asistencias">Asistencias</NavLink>
              <NavLink to="/incidencias">Incidencias</NavLink>
              <NavLink to="/dispositivos">Dispositivos</NavLink>
              <NavLink to="/configuracion">Configuración</NavLink>
            </>
          )}
        </nav>

        <div className="header-user">
          <span>{user?.name}</span>
          <button type="button" onClick={() => void logout()}>
            Salir
          </button>
        </div>
      </header>

      <main className="app-content">
        <Outlet />
      </main>
    </div>
  );
}
