import { useEffect, useState } from "react";
import { getStudentClusters } from "../../api/analytics";
import { ApiError } from "../../api/client";
import type { ClusterLabel, StudentClusterResult } from "../../types";

const LABEL_TO_BADGE: Record<ClusterLabel, string> = {
  "baja actividad": "badge-baja",
  "actividad moderada": "badge-moderada",
  "alta actividad": "badge-alta",
};

const LABEL_TO_COLOR: Record<ClusterLabel, string> = {
  "baja actividad": "#b91c1c",
  "actividad moderada": "#b45309",
  "alta actividad": "#15803d",
};

const CHART_SIZE = 360;
const CHART_PADDING = 32;

type IndexedCluster = StudentClusterResult["clusters"][number] & { index: number };

function ScatterChart({ points }: { points: IndexedCluster[] }) {
  const xs = points.map((c) => c.features.horasPromedioSemana);
  const ys = points.map((c) => c.features.sesionesPromedioSemana);
  const xMax = Math.max(1, ...xs);
  const yMax = Math.max(1, ...ys);

  const plotSize = CHART_SIZE - CHART_PADDING * 2;
  const toX = (v: number) => CHART_PADDING + (v / xMax) * plotSize;
  const toY = (v: number) => CHART_SIZE - CHART_PADDING - (v / yMax) * plotSize;

  return (
    <svg
      viewBox={`0 0 ${CHART_SIZE} ${CHART_SIZE}`}
      role="img"
      aria-label="Dispersión de horas promedio por semana contra sesiones promedio por semana, coloreada por grupo, con el número de cada estudiante (ver tabla)"
      style={{ width: "100%", maxWidth: 420, height: "auto" }}
    >
      <line
        x1={CHART_PADDING}
        y1={CHART_SIZE - CHART_PADDING}
        x2={CHART_SIZE - CHART_PADDING}
        y2={CHART_SIZE - CHART_PADDING}
        stroke="var(--border)"
      />
      <line
        x1={CHART_PADDING}
        y1={CHART_PADDING}
        x2={CHART_PADDING}
        y2={CHART_SIZE - CHART_PADDING}
        stroke="var(--border)"
      />
      <text x={CHART_SIZE / 2} y={CHART_SIZE - 6} textAnchor="middle" fontSize="11" fill="var(--text-muted)">
        Horas promedio por semana
      </text>
      <text
        x={12}
        y={CHART_SIZE / 2}
        textAnchor="middle"
        fontSize="11"
        fill="var(--text-muted)"
        transform={`rotate(-90 12 ${CHART_SIZE / 2})`}
      >
        Sesiones promedio por semana
      </text>

      {points.map((c) => (
        <g key={c.studentId}>
          <circle
            cx={toX(c.features.horasPromedioSemana)}
            cy={toY(c.features.sesionesPromedioSemana)}
            r={9}
            fill={LABEL_TO_COLOR[c.clusterLabel]}
            fillOpacity={0.75}
          >
            <title>
              #{c.index} — {c.nombre} ({c.matricula}) — {c.clusterLabel}
            </title>
          </circle>
          <text
            x={toX(c.features.horasPromedioSemana)}
            y={toY(c.features.sesionesPromedioSemana)}
            textAnchor="middle"
            dominantBaseline="central"
            fontSize="9"
            fontWeight="700"
            fill="#ffffff"
            pointerEvents="none"
          >
            {c.index}
          </text>
        </g>
      ))}
    </svg>
  );
}

const GROUP_OPTIONS: Array<{ value: ClusterLabel | ""; label: string }> = [
  { value: "", label: "Todos los grupos" },
  { value: "baja actividad", label: "Baja actividad" },
  { value: "actividad moderada", label: "Actividad moderada" },
  { value: "alta actividad", label: "Alta actividad" },
];

export function AdminAnalytics() {
  const [result, setResult] = useState<StudentClusterResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");
  const [groupFilter, setGroupFilter] = useState<ClusterLabel | "">("");

  async function load(forceRefresh = false) {
    setLoading(true);
    setError(null);
    try {
      const data = await getStudentClusters(forceRefresh);
      setResult(data);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : "No se pudo calcular el agrupamiento de estudiantes.",
      );
      setResult(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    // Carga inicial: usa el resultado cacheado si hay uno reciente (rápido).
    // "Recalcular" sí fuerza un cálculo fresco — ver el botón más abajo.
    load(false);
  }, []);

  const indexedClusters: IndexedCluster[] = result
    ? [...result.clusters]
        .sort((a, b) => b.cluster - a.cluster || b.features.horasAcumuladas - a.features.horasAcumuladas)
        .map((c, i) => ({ ...c, index: i + 1 }))
    : [];

  const searchNormalized = search.trim().toLowerCase();
  const filteredClusters = indexedClusters.filter((c) => {
    const matchesGroup = !groupFilter || c.clusterLabel === groupFilter;
    const matchesSearch =
      !searchNormalized ||
      c.nombre.toLowerCase().includes(searchNormalized) ||
      c.matricula.toLowerCase().includes(searchNormalized);
    return matchesGroup && matchesSearch;
  });

  return (
    <section className="card">
      <div className="row-between">
        <h2>Patrones de asistencia (K-Means)</h2>
        <button type="button" onClick={() => void load(true)} disabled={loading}>
          {loading ? "Calculando…" : "Recalcular"}
        </button>
      </div>
      <p className="muted">
        Agrupa a los estudiantes activos con al menos una sesión de asistencia cerrada, según horas
        acumuladas, frecuencia y duración de sus sesiones, y qué tan constantes son semana a semana.
      </p>

      {loading && !result && <p>Calculando…</p>}
      {error && <p className="error-text">{error}</p>}

      {result && (
        <>
          <div className="card-inset">
            <h3>Perfiles encontrados</h3>
            <div className="row-gap" style={{ flexWrap: "wrap" }}>
              {result.centroids.map((centroid) => (
                <div key={centroid.cluster} className="card-inset">
                  <span className={`badge ${LABEL_TO_BADGE[centroid.clusterLabel]}`}>
                    {centroid.clusterLabel}
                  </span>
                  <p className="muted" style={{ marginTop: 6, fontSize: "0.85rem" }}>
                    ~{centroid.horasPromedioSemana} h/semana · ~{centroid.sesionesPromedioSemana}{" "}
                    sesiones/semana · {centroid.horasAcumuladas} h acumuladas en promedio
                  </p>
                </div>
              ))}
            </div>
          </div>

          <div className="card-inset">
            <ScatterChart points={filteredClusters} />
          </div>

          <div className="inline-form">
            <input
              type="text"
              placeholder="Buscar por nombre o matrícula…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <select value={groupFilter} onChange={(e) => setGroupFilter(e.target.value as ClusterLabel | "")}>
              {GROUP_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          <table className="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Matrícula</th>
                <th>Nombre</th>
                <th>Horas acum.</th>
                <th>Sesiones</th>
                <th>Duración prom. (min)</th>
                <th>Horas/semana</th>
                <th>Sesiones/semana</th>
                <th>Variabilidad</th>
                <th>Grupo</th>
              </tr>
            </thead>
            <tbody>
              {filteredClusters.length === 0 && (
                <tr>
                  <td colSpan={9} className="muted">
                    Ningún estudiante coincide con el filtro.
                  </td>
                </tr>
              )}
              {filteredClusters.map((c) => (
                <tr key={c.studentId}>
                  <td>{c.index}</td>
                  <td>{c.matricula}</td>
                  <td>{c.nombre}</td>
                  <td>{c.features.horasAcumuladas}</td>
                  <td>{c.features.numeroSesiones}</td>
                  <td>{c.features.duracionPromedioSesionMin}</td>
                  <td>{c.features.horasPromedioSemana}</td>
                  <td>{c.features.sesionesPromedioSemana}</td>
                  <td>{c.features.variabilidadSemanal}</td>
                  <td>
                    <span className={`badge ${LABEL_TO_BADGE[c.clusterLabel]}`}>{c.clusterLabel}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </section>
  );
}
