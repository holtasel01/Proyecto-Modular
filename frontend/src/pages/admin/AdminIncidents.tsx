import { Fragment, useEffect, useState } from "react";
import { listIncidents, resolveIncident } from "../../api/incidents";
import { ApiError } from "../../api/client";
import type { Incident } from "../../types";

const TYPE_LABELS: Record<string, string> = {
  entrada_sin_salida: "Entrada sin salida",
  duplicado: "Reconocimiento duplicado sospechoso",
  baja_confianza: "Reconocimiento con confianza baja",
  salida_sin_entrada: "Salida sin entrada",
  corregido_manualmente: "Corrección manual",
};

export function AdminIncidents() {
  const [incidents, setIncidents] = useState<Incident[] | null>(null);
  const [statusFilter, setStatusFilter] = useState("open");
  const [resolvingId, setResolvingId] = useState<number | null>(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function refresh() {
    listIncidents({ status: statusFilter || undefined }).then((page) => setIncidents(page.data));
  }

  useEffect(() => {
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter]);

  async function handleResolve(id: number) {
    setSaving(true);
    setError(null);
    try {
      await resolveIncident(id, note);
      setResolvingId(null);
      setNote("");
      refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "No se pudo resolver la incidencia.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="card">
      <div className="row-between">
        <h2>Incidencias</h2>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
          <option value="open">Abiertas</option>
          <option value="resolved">Resueltas</option>
          <option value="">Todas</option>
        </select>
      </div>

      {incidents === null && <p>Cargando…</p>}
      {incidents && incidents.length === 0 && <p>No hay incidencias.</p>}

      {incidents && incidents.length > 0 && (
        <table className="table">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Descripción</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {incidents.map((incident) => (
              <Fragment key={incident.id}>
                <tr>
                  <td>{TYPE_LABELS[incident.type] ?? incident.type}</td>
                  <td>{incident.description ?? "—"}</td>
                  <td>
                    <span className={`badge badge-${incident.status}`}>{incident.status}</span>
                  </td>
                  <td>{new Date(incident.createdAt).toLocaleString()}</td>
                  <td>
                    {incident.status === "open" && (
                      <button type="button" onClick={() => setResolvingId(incident.id)}>
                        Resolver
                      </button>
                    )}
                  </td>
                </tr>
                {resolvingId === incident.id && (
                  <tr>
                    <td colSpan={5}>
                      <div className="card-inset">
                        <label>
                          Nota de resolución
                          <input value={note} onChange={(e) => setNote(e.target.value)} required />
                        </label>
                        {error && <p className="error-text">{error}</p>}
                        <div className="row-gap">
                          <button
                            type="button"
                            onClick={() => void handleResolve(incident.id)}
                            disabled={saving}
                          >
                            {saving ? "Guardando…" : "Confirmar"}
                          </button>
                          <button type="button" onClick={() => setResolvingId(null)}>
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
  );
}
