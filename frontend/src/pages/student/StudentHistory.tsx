import { useEffect, useState } from "react";
import { myAttendance } from "../../api/attendance";
import type { AttendanceSession } from "../../types";

const STATUS_LABELS: Record<string, string> = {
  open: "En curso",
  closed: "Cerrada",
  inconsistent: "Inconsistente (revisión pendiente)",
};

export function StudentHistory() {
  const [sessions, setSessions] = useState<AttendanceSession[] | null>(null);

  useEffect(() => {
    myAttendance().then((page) => setSessions(page.data));
  }, []);

  return (
    <section className="card">
      <h2>Mi historial de asistencia</h2>

      {sessions === null && <p>Cargando…</p>}
      {sessions && sessions.length === 0 && <p>Todavía no tienes registros de asistencia.</p>}

      {sessions && sessions.length > 0 && (
        <table className="table">
          <thead>
            <tr>
              <th>Entrada</th>
              <th>Salida</th>
              <th>Duración</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            {sessions.map((session) => (
              <tr key={session.id}>
                <td>{new Date(session.startedAt).toLocaleString()}</td>
                <td>{session.endedAt ? new Date(session.endedAt).toLocaleString() : "—"}</td>
                <td>{session.durationMinutes !== null ? `${session.durationMinutes} min` : "—"}</td>
                <td>
                  <span className={`badge badge-${session.status}`}>
                    {STATUS_LABELS[session.status] ?? session.status}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  );
}
