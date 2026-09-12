import { useEffect, useState, type FormEvent } from "react";
import { useParams, Link } from "react-router-dom";
import {
  getFaceProfile,
  getStudent,
  replaceFacePhoto,
  updateStudent,
} from "../../api/students";
import { ApiError } from "../../api/client";
import type { FaceProfile, Student } from "../../types";

export function AdminStudentDetail() {
  const { id } = useParams<{ id: string }>();
  const studentId = Number(id);

  const [student, setStudent] = useState<Student | null>(null);
  const [faceProfile, setFaceProfile] = useState<FaceProfile | null>(null);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({ nombre: "", carrera: "", horasMeta: 0, estado: "activo" });
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [replaceError, setReplaceError] = useState<string | null>(null);
  const [replacing, setReplacing] = useState(false);

  function loadStudent() {
    getStudent(studentId).then(({ data }) => {
      setStudent(data);
      setForm({
        nombre: data.nombre,
        carrera: data.carrera ?? "",
        horasMeta: data.horasMeta,
        estado: data.estado,
      });
    });
  }

  useEffect(() => {
    loadStudent();
    getFaceProfile(studentId).then(setFaceProfile);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [studentId]);

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await updateStudent(studentId, form);
      setEditing(false);
      loadStudent();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "No se pudo guardar.");
    } finally {
      setSaving(false);
    }
  }

  async function handleReplacePhoto(file: File) {
    setReplaceError(null);
    setReplacing(true);
    try {
      const profile = await replaceFacePhoto(studentId, file);
      setFaceProfile(profile);
    } catch (err) {
      setReplaceError(err instanceof ApiError ? err.message : "No se pudo reemplazar la foto.");
    } finally {
      setReplacing(false);
    }
  }

  if (!student) return <p>Cargando…</p>;

  return (
    <div className="stack">
      <Link to="/estudiantes">← Volver</Link>

      <section className="card">
        <div className="row-between">
          <h2>{student.nombre}</h2>
          <button type="button" onClick={() => setEditing((v) => !v)}>
            {editing ? "Cancelar" : "Editar"}
          </button>
        </div>

        {!editing && (
          <dl className="definition-list">
            <dt>Matrícula</dt>
            <dd>{student.matricula}</dd>
            <dt>Carrera</dt>
            <dd>{student.carrera ?? "—"}</dd>
            <dt>Meta de horas</dt>
            <dd>{student.horasMeta}</dd>
            <dt>Estado</dt>
            <dd>{student.estado}</dd>
          </dl>
        )}

        {editing && (
          <form onSubmit={handleSave} className="card-inset">
            <label>
              Nombre
              <input
                value={form.nombre}
                onChange={(e) => setForm({ ...form, nombre: e.target.value })}
              />
            </label>
            <label>
              Carrera
              <input
                value={form.carrera}
                onChange={(e) => setForm({ ...form, carrera: e.target.value })}
              />
            </label>
            <label>
              Meta de horas
              <input
                type="number"
                value={form.horasMeta}
                onChange={(e) => setForm({ ...form, horasMeta: Number(e.target.value) })}
              />
            </label>
            <label>
              Estado
              <select
                value={form.estado}
                onChange={(e) => setForm({ ...form, estado: e.target.value })}
              >
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
              </select>
            </label>
            {error && <p className="error-text">{error}</p>}
            <button type="submit" disabled={saving}>
              {saving ? "Guardando…" : "Guardar"}
            </button>
          </form>
        )}
      </section>

      <section className="card">
        <h2>Enrolamiento facial</h2>
        {faceProfile === null && <p>Cargando…</p>}
        {faceProfile && (
          <>
            <p>
              {faceProfile.existe
                ? `Enrolado con el modelo ${faceProfile.modelo}.`
                : "Sin enrolamiento todavía (el propio estudiante debe subir su foto inicial)."}
            </p>
            {faceProfile.existe && (
              <>
                <p className="muted">
                  Reemplazar la foto es una acción exclusiva de administrador y queda registrada
                  en el historial de auditoría.
                </p>
                <input
                  type="file"
                  accept="image/png,image/jpeg"
                  disabled={replacing}
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) void handleReplacePhoto(file);
                  }}
                />
                {replacing && <p className="muted">Procesando…</p>}
                {replaceError && <p className="error-text">{replaceError}</p>}
              </>
            )}
          </>
        )}
      </section>
    </div>
  );
}
