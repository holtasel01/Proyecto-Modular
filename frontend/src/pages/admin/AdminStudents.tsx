import { useEffect, useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { createStudent, listStudents } from "../../api/students";
import { ApiError } from "../../api/client";
import type { Student } from "../../types";

export function AdminStudents() {
  const [students, setStudents] = useState<Student[] | null>(null);
  const [search, setSearch] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [matricula, setMatricula] = useState("");
  const [nombre, setNombre] = useState("");
  const [carrera, setCarrera] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function refresh(searchTerm = search) {
    listStudents(searchTerm || undefined).then((page) => setStudents(page.data));
  }

  useEffect(() => {
    refresh("");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setSubmitting(true);
    try {
      await createStudent({ matricula, nombre, carrera: carrera || undefined });
      setMatricula("");
      setNombre("");
      setCarrera("");
      setShowForm(false);
      refresh();
    } catch (error) {
      setFormError(error instanceof ApiError ? error.message : "No se pudo crear el estudiante.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="stack">
      <section className="card">
        <div className="row-between">
          <h2>Estudiantes</h2>
          <button type="button" onClick={() => setShowForm((v) => !v)}>
            {showForm ? "Cancelar" : "Nuevo estudiante"}
          </button>
        </div>

        <form
          className="inline-form"
          onSubmit={(e) => {
            e.preventDefault();
            refresh();
          }}
        >
          <input
            placeholder="Buscar por matrícula o nombre…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <button type="submit">Buscar</button>
        </form>

        {showForm && (
          <form className="card-inset" onSubmit={handleCreate}>
            <label>
              Matrícula
              <input value={matricula} onChange={(e) => setMatricula(e.target.value)} required />
            </label>
            <label>
              Nombre
              <input value={nombre} onChange={(e) => setNombre(e.target.value)} required />
            </label>
            <label>
              Carrera
              <input value={carrera} onChange={(e) => setCarrera(e.target.value)} />
            </label>
            {formError && <p className="error-text">{formError}</p>}
            <button type="submit" disabled={submitting}>
              {submitting ? "Creando…" : "Crear"}
            </button>
          </form>
        )}
      </section>

      <section className="card">
        {students === null && <p>Cargando…</p>}
        {students && students.length === 0 && <p>No hay estudiantes que coincidan.</p>}
        {students && students.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>Matrícula</th>
                <th>Nombre</th>
                <th>Carrera</th>
                <th>Estado</th>
                <th>Enrolamiento</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {students.map((student) => (
                <tr key={student.id}>
                  <td>{student.matricula}</td>
                  <td>{student.nombre}</td>
                  <td>{student.carrera ?? "—"}</td>
                  <td>{student.estado}</td>
                  <td>{student.tieneEnrolamientoFacial ? "✅" : "—"}</td>
                  <td>
                    <Link to={`/estudiantes/${student.id}`}>Ver</Link>
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
