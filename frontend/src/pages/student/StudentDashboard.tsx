import { useEffect, useState } from "react";
import { useAuth } from "../../auth/AuthContext";
import { mySummary } from "../../api/attendance";
import { getFaceProfile, getStudent, uploadFacePhoto } from "../../api/students";
import { ApiError } from "../../api/client";
import type { AttendanceSummary, FaceProfile, Student } from "../../types";

export function StudentDashboard() {
  const { user } = useAuth();
  const [student, setStudent] = useState<Student | null>(null);
  const [summary, setSummary] = useState<AttendanceSummary | null>(null);
  const [faceProfile, setFaceProfile] = useState<FaceProfile | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  const studentId = user?.studentId ?? null;

  useEffect(() => {
    if (!studentId) return;
    getStudent(studentId).then(({ data }) => setStudent(data));
    getFaceProfile(studentId).then(setFaceProfile);
    mySummary().then(setSummary);
  }, [studentId]);

  async function handlePhotoSelected(file: File) {
    if (!studentId) return;
    setUploadError(null);
    setUploading(true);
    try {
      const profile = await uploadFacePhoto(studentId, file);
      setFaceProfile(profile);
    } catch (error) {
      setUploadError(error instanceof ApiError ? error.message : "No se pudo subir la foto.");
    } finally {
      setUploading(false);
    }
  }

  if (!studentId) {
    return <p>Tu cuenta no está vinculada a un registro de estudiante todavía.</p>;
  }

  return (
    <div className="stack">
      <section className="card">
        <h2>Mi perfil</h2>
        {student ? (
          <dl className="definition-list">
            <dt>Matrícula</dt>
            <dd>{student.matricula}</dd>
            <dt>Nombre</dt>
            <dd>{student.nombre}</dd>
            <dt>Carrera</dt>
            <dd>{student.carrera ?? "—"}</dd>
            <dt>Estado</dt>
            <dd>{student.estado}</dd>
          </dl>
        ) : (
          <p>Cargando…</p>
        )}
      </section>

      <section className="card">
        <h2>Horas de servicio social</h2>
        {summary ? (
          <>
            <p className="big-number">
              {summary.horasAcumuladas} / {summary.horasMeta} horas
            </p>
            <div className="progress-bar">
              <div
                className="progress-bar-fill"
                style={{ width: `${Math.min(100, summary.progresoPorcentaje)}%` }}
              />
            </div>
            <p className="muted">{summary.progresoPorcentaje}% completado</p>
          </>
        ) : (
          <p>Cargando…</p>
        )}
      </section>

      <section className="card">
        <h2>Enrolamiento facial</h2>
        {faceProfile === null && <p>Cargando…</p>}
        {faceProfile?.existe && (
          <p>
            ✅ Ya tienes un enrolamiento registrado ({faceProfile.modelo}). Si necesitas cambiar tu
            foto, pídele al administrador que la reemplace.
          </p>
        )}
        {faceProfile && !faceProfile.existe && (
          <>
            <p>
              Todavía no subes tu foto de enrolamiento — sin ella, la cámara del laboratorio no
              podrá reconocerte. Solo puedes hacerlo <strong>una vez</strong>; después, solo un
              administrador puede reemplazarla.
            </p>
            <input
              type="file"
              accept="image/png,image/jpeg"
              disabled={uploading}
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (file) void handlePhotoSelected(file);
              }}
            />
            {uploading && <p className="muted">Procesando…</p>}
            {uploadError && <p className="error-text">{uploadError}</p>}
          </>
        )}
      </section>
    </div>
  );
}
