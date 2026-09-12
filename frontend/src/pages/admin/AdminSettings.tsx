import { useEffect, useState, type FormEvent } from "react";
import { listSettings, updateSettings } from "../../api/settings";
import { ApiError } from "../../api/client";

const LABELS: Record<string, string> = {
  min_confidence_reject: "Confianza mínima para aceptar (rechazo por debajo de esto)",
  min_confidence_trust: "Confianza mínima para confiar sin revisión",
  duplicate_window_seconds: "Ventana anti-duplicado (segundos)",
  max_session_hours: "Horas máximas antes de marcar una sesión como inconsistente",
  default_horas_meta: "Meta de horas por defecto para nuevos estudiantes",
};

export function AdminSettings() {
  const [values, setValues] = useState<Record<string, string> | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    listSettings().then(({ data }) => {
      setValues(Object.fromEntries(data.map((s) => [s.key, s.value])));
    });
  }, []);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!values) return;
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await updateSettings(values);
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "No se pudo guardar la configuración.");
    } finally {
      setSaving(false);
    }
  }

  if (!values) return <p>Cargando…</p>;

  return (
    <section className="card">
      <h2>Configuración del sistema</h2>
      <p className="muted">
        Estos valores se aplican de inmediato, sin necesidad de reiniciar el backend.
      </p>

      <form className="stack" onSubmit={handleSubmit}>
        {Object.entries(values).map(([key, value]) => (
          <label key={key}>
            {LABELS[key] ?? key}
            <input
              value={value}
              onChange={(e) => setValues({ ...values, [key]: e.target.value })}
            />
          </label>
        ))}

        {error && <p className="error-text">{error}</p>}
        {saved && <p className="success-text">Guardado.</p>}

        <button type="submit" disabled={saving}>
          {saving ? "Guardando…" : "Guardar cambios"}
        </button>
      </form>
    </section>
  );
}
