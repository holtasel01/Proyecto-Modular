import { useEffect, useState } from "react";
import { myPrediction } from "../../api/attendance";
import type { StudentPrediction as StudentPredictionData } from "../../types";

function formatDate(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString("es-MX", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function constanciaLabel(variabilidad: number): string {
  if (variabilidad <= 0.5) return "Muy constante — casi las mismas horas cada semana.";
  if (variabilidad <= 2) return "Razonablemente constante, con algo de variación semana a semana.";
  return "Irregular — tus horas cambian bastante de una semana a otra.";
}

export function StudentPrediction() {
  const [data, setData] = useState<StudentPredictionData | null>(null);

  useEffect(() => {
    myPrediction().then(setData);
  }, []);

  if (!data) {
    return <p>Cargando…</p>;
  }

  return (
    <div className="stack">
      <section className="card">
        <h2>Tu progreso hacia la meta</h2>
        <p className="big-number">
          {data.horasAcumuladas} / {data.horasMeta} horas
        </p>
        <div className="progress-bar">
          <div
            className="progress-bar-fill"
            style={{ width: `${Math.min(100, data.progresoPorcentaje)}%` }}
          />
        </div>
        <p className="muted">{data.progresoPorcentaje}% completado</p>

        {data.metaCumplida && (
          <p className="muted" style={{ marginTop: 8 }}>
            🎉 Ya completaste tu meta de horas de servicio social.
          </p>
        )}
      </section>

      {!data.tieneDatos && (
        <section className="card">
          <p className="muted">
            Todavía no tienes sesiones de asistencia registradas. Una vez que empieces a asistir al
            laboratorio, aquí verás tu ritmo y una fecha estimada para completar tu meta.
          </p>
        </section>
      )}

      {data.tieneDatos && (
        <>
          <section className="card">
            <h2>Tu ritmo actual</h2>
            <dl className="definition-list">
              <dt>Horas por semana</dt>
              <dd>{data.horasPromedioSemana} h/semana</dd>
              <dt>Horas por día</dt>
              <dd>{data.horasPromedioDia} h/día (promedio, contando todos los días)</dd>
              <dt>Sesiones por semana</dt>
              <dd>{data.sesionesPromedioSemana}</dd>
              <dt>Duración típica de una sesión</dt>
              <dd>{data.duracionPromedioSesionMin} min</dd>
              <dt>Sesiones registradas en total</dt>
              <dd>{data.numeroSesiones}</dd>
            </dl>
            {data.variabilidadSemanal !== null && (
              <p className="muted" style={{ marginTop: 8 }}>
                Constancia: {constanciaLabel(data.variabilidadSemanal)}
              </p>
            )}
          </section>

          {!data.metaCumplida && data.semanasRestantesEstimadas !== null && (
            <section className="card">
              <h2>Estimación para terminar</h2>
              <p className="muted">A tu ritmo actual, si lo mantienes:</p>
              <dl className="definition-list">
                <dt>Horas que te faltan</dt>
                <dd>{data.horasRestantes} h</dd>
                <dt>Semanas estimadas</dt>
                <dd>≈ {data.semanasRestantesEstimadas} semanas</dd>
                <dt>Días estimados</dt>
                <dd>≈ {data.diasRestantesEstimados} días</dd>
                {data.sesionesRestantesEstimadas !== null && (
                  <>
                    <dt>Sesiones estimadas</dt>
                    <dd>≈ {data.sesionesRestantesEstimadas} sesiones más</dd>
                  </>
                )}
                {data.fechaEstimadaFinalizacion && (
                  <>
                    <dt>Fecha estimada de finalización</dt>
                    <dd>{formatDate(data.fechaEstimadaFinalizacion)}</dd>
                  </>
                )}
              </dl>
              <p className="muted" style={{ marginTop: 8 }}>
                Es solo una estimación basada en tu historial — si cambias tu ritmo, esta proyección
                también cambia.
              </p>
            </section>
          )}

          {!data.metaCumplida && data.semanasRestantesEstimadas === null && (
            <section className="card">
              <p className="muted">
                Todavía no hay suficiente ritmo reciente para estimar una fecha de finalización.
              </p>
            </section>
          )}
        </>
      )}
    </div>
  );
}
