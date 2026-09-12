import { Fragment, useEffect, useState } from "react";
import { correctSession, labStatus, listSessions, type SessionFilters } from "../../api/attendance";
import { ApiError } from "../../api/client";
import type { AttendanceSession, LabStatusEntry } from "../../types";

function toInputDateTime(value: string | null): string {
  if (!value) return "";
  return new Date(value).toISOString().slice(0, 16);
}

export function AdminAttendance() {
  const [inLab, setInLab] = useState<LabStatusEntry[] | null>(null);
  const [sessions, setSessions] = useState<AttendanceSession[] | null>(null);
  const [filters, setFilters] = useState<SessionFilters>({});
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState({ startedAt: "", endedAt: "", status: "closed", reason: "" });
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function refresh() {
    listSessions(filters).then((page) => setSessions(page.data));
  }

  useEffect(() => {
    labStatus().then((r) => setInLab(r.estudiantesDentro));
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function startEditing(session: AttendanceSession) {
    setEditingId(session.id);
    setForm({
      startedAt: toInputDateTime(session.startedAt),
      endedAt: toInputDateTime(session.endedAt),
      status: session.status,
      reason: "",
    });
    setError(null);
  }

  async function handleSave(sessionId: number) {
    setSaving(true);
    setError(null);
    try {
      await correctSession(sessionId, {
        startedAt: form.startedAt ? new Date(form.startedAt).toISOString() : undefined,
        endedAt: form.endedAt ? new Date(form.endedAt).toISOString() : null,
        status: form.status,
        reason: form.reason,
      });
      setEditingId(null);
      refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "No se pudo guardar la corrección.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="stack">
      <section className="card">
        <h2>En el laboratorio ahora mismo</h2>
        {inLab === null && <p>Cargando…</p>}
        {inLab && inLab.length === 0 && <p>Nadie registrado como presente.</p>}
        {inLab && inLab.length > 0 && (
          <ul>
            {inLab.map((entry) => (
              <li key={entry.sessionId}>
                {entry.nombre} ({entry.matricula}) — desde {new Date(entry.startedAt).toLocaleTimeString()}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card">
        <div className="row-between">
          <h2>Sesiones de asistencia</h2>
        </div>

        <form
          className="inline-form"
          onSubmit={(e) => {
            e.preventDefault();
            refresh();
          }}
        >
          <select
            value={filters.status ?? ""}
            onChange={(e) => setFilters({ ...filters, status: e.target.value || undefined })}
          >
            <option value="">Todos los estados</option>
            <option value="open">Abiertas</option>
            <option value="closed">Cerradas</option>
            <option value="inconsistent">Inconsistentes</option>
          </select>
          <button type="submit">Filtrar</button>
        </form>

        {sessions === null && <p>Cargando…</p>}
        {sessions && sessions.length === 0 && <p>No hay sesiones que coincidan.</p>}

        {sessions && sessions.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>Estudiante</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Estado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {sessions.map((session) => (
                <Fragment key={session.id}>
                  <tr>
                    <td>#{session.studentId}</td>
                    <td>{new Date(session.startedAt).toLocaleString()}</td>
                    <td>{session.endedAt ? new Date(session.endedAt).toLocaleString() : "—"}</td>
                    <td>
                      <span className={`badge badge-${session.status}`}>{session.status}</span>
                    </td>
                    <td>
                      <button type="button" onClick={() => startEditing(session)}>
                        Corregir
                      </button>
                    </td>
                  </tr>
                  {editingId === session.id && (
                    <tr>
                      <td colSpan={5}>
                        <div className="card-inset">
                          <label>
                            Entrada
                            <input
                              type="datetime-local"
                              value={form.startedAt}
                              onChange={(e) => setForm({ ...form, startedAt: e.target.value })}
                            />
                          </label>
                          <label>
                            Salida
                            <input
                              type="datetime-local"
                              value={form.endedAt}
                              onChange={(e) => setForm({ ...form, endedAt: e.target.value })}
                            />
                          </label>
                          <label>
                            Estado
                            <select
                              value={form.status}
                              onChange={(e) => setForm({ ...form, status: e.target.value })}
                            >
                              <option value="open">Abierta</option>
                              <option value="closed">Cerrada</option>
                              <option value="inconsistent">Inconsistente</option>
                            </select>
                          </label>
                          <label>
                            Motivo de la corrección (obligatorio, queda auditado)
                            <input
                              value={form.reason}
                              onChange={(e) => setForm({ ...form, reason: e.target.value })}
                              required
                            />
                          </label>
                          {error && <p className="error-text">{error}</p>}
                          <div className="row-gap">
                            <button type="button" onClick={() => void handleSave(session.id)} disabled={saving}>
                              {saving ? "Guardando…" : "Guardar corrección"}
                            </button>
                            <button type="button" onClick={() => setEditingId(null)}>
                              Cancelar
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
