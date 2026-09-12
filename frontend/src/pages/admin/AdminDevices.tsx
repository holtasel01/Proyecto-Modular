import { useEffect, useState, type FormEvent } from "react";
import { createDevice, listDevices, revokeDevice } from "../../api/devices";
import { ApiError } from "../../api/client";
import type { Device } from "../../types";

export function AdminDevices() {
  const [devices, setDevices] = useState<Device[] | null>(null);
  const [nombre, setNombre] = useState("");
  const [ubicacion, setUbicacion] = useState("");
  const [newToken, setNewToken] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function refresh() {
    listDevices().then((r) => setDevices(r.data));
  }

  useEffect(() => {
    refresh();
  }, []);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const { data } = await createDevice(nombre, ubicacion || undefined);
      setNewToken(data.plainTextToken ?? null);
      setNombre("");
      setUbicacion("");
      refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "No se pudo crear el dispositivo.");
    } finally {
      setSubmitting(false);
    }
  }

  async function handleRevoke(id: number) {
    if (!confirm("¿Revocar este dispositivo? Dejará de poder reportar asistencia.")) return;
    await revokeDevice(id);
    refresh();
  }

  return (
    <div className="stack">
      {newToken && (
        <section className="card card-warning">
          <h2>Token generado</h2>
          <p>
            Cópialo ahora y ponlo en <code>recognition-app/.env</code> como <code>DEVICE_TOKEN</code>.
            No se podrá volver a mostrar.
          </p>
          <pre className="token-box">{newToken}</pre>
          <button type="button" onClick={() => setNewToken(null)}>
            Ya lo copié
          </button>
        </section>
      )}

      <section className="card">
        <h2>Nuevo dispositivo del laboratorio</h2>
        <form className="card-inset" onSubmit={handleCreate}>
          <label>
            Nombre
            <input value={nombre} onChange={(e) => setNombre(e.target.value)} required />
          </label>
          <label>
            Ubicación
            <input value={ubicacion} onChange={(e) => setUbicacion(e.target.value)} />
          </label>
          {error && <p className="error-text">{error}</p>}
          <button type="submit" disabled={submitting}>
            {submitting ? "Creando…" : "Crear y generar token"}
          </button>
        </form>
      </section>

      <section className="card">
        <h2>Dispositivos</h2>
        {devices === null && <p>Cargando…</p>}
        {devices && devices.length === 0 && <p>No hay dispositivos registrados.</p>}
        {devices && devices.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Ubicación</th>
                <th>Última actividad</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {devices.map((device) => (
                <tr key={device.id}>
                  <td>{device.nombre}</td>
                  <td>{device.ubicacion ?? "—"}</td>
                  <td>{device.lastSeenAt ? new Date(device.lastSeenAt).toLocaleString() : "Nunca"}</td>
                  <td>
                    <button type="button" onClick={() => void handleRevoke(device.id)}>
                      Revocar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
