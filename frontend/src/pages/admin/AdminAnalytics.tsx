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
const PADDING_LEFT = 46;
const PADDING_RIGHT = 16;
const PADDING_TOP = 16;
const PADDING_BOTTOM = 46;

/** Redondea a un paso "bonito" (1, 2, 2.5, 5, 10 × 10^n) para que las marcas
 * del eje muestren números fáciles de leer en vez de decimales raros. */
function niceStep(max: number, targetTicks = 5): number {
  const rawStep = max / targetTicks;
  const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep || 1)));
  const normalized = rawStep / magnitude;
  let niceNormalized = 1;
  if (normalized > 5) niceNormalized = 10;
  else if (normalized > 2) niceNormalized = 5;
  else if (normalized > 1) niceNormalized = 2;
  return niceNormalized * magnitude;
}

function buildTicks(max: number): number[] {
  const step = niceStep(max);
  const ticks: number[] = [];
  for (let v = 0; v <= max + step / 2; v += step) {
    ticks.push(Math.round(v * 100) / 100);
  }
  return ticks;
}

type IndexedCluster = StudentClusterResult["clusters"][number] & { index: number };

function ScatterChart({ points }: { points: IndexedCluster[] }) {
  const xs = points.map((c) => c.features.horasPromedioSemana);
  const ys = points.map((c) => c.features.sesionesPromedioSemana);
  const xMax = Math.max(1, ...xs);
  const yMax = Math.max(1, ...ys);

  const xTicks = buildTicks(xMax);
  const yTicks = buildTicks(yMax);
  const xAxisMax = xTicks[xTicks.length - 1];
  const yAxisMax = yTicks[yTicks.length - 1];

  const plotWidth = CHART_SIZE - PADDING_LEFT - PADDING_RIGHT;
  const plotHeight = CHART_SIZE - PADDING_TOP - PADDING_BOTTOM;
  const toX = (v: number) => PADDING_LEFT + (v / xAxisMax) * plotWidth;
  const toY = (v: number) => CHART_SIZE - PADDING_BOTTOM - (v / yAxisMax) * plotHeight;

  return (
    <svg
      viewBox={`0 0 ${CHART_SIZE} ${CHART_SIZE}`}
      role="img"
      aria-label="Dispersión de horas promedio por semana contra sesiones promedio por semana, con escala numérica en ambos ejes y coloreada por grupo"
      style={{ width: "100%", maxWidth: 420, height: "auto" }}
    >
      {/* Líneas guía + números de la escala */}
      {xTicks.map((t) => (
        <g key={`x-${t}`}>
          <line
            x1={toX(t)}
            y1={PADDING_TOP}
            x2={toX(t)}
            y2={CHART_SIZE - PADDING_BOTTOM}
            stroke="var(--border)"
            strokeDasharray={t === 0 ? undefined : "3 3"}
          />
          <text x={toX(t)} y={CHART_SIZE - PADDING_BOTTOM + 14} textAnchor="middle" fontSize="9" fill="var(--text-muted)">
            {t}
          </text>
        </g>
      ))}
      {yTicks.map((t) => (
        <g key={`y-${t}`}>
          <line
            x1={PADDING_LEFT}
            y1={toY(t)}
            x2={CHART_SIZE - PADDING_RIGHT}
            y2={toY(t)}
            stroke="var(--border)"
            strokeDasharray={t === 0 ? undefined : "3 3"}
          />
          <text x={PADDING_LEFT - 6} y={toY(t)} textAnchor="end" dominantBaseline="middle" fontSize="9" fill="var(--text-muted)">
            {t}
          </text>
        </g>
      ))}

      <text x={(PADDING_LEFT + CHART_SIZE - PADDING_RIGHT) / 2} y={CHART_SIZE - 4} textAnchor="middle" fontSize="11" fill="var(--text-muted)">
        Horas promedio por semana
      </text>
      <text
        x={10}
        y={(PADDING_TOP + CHART_SIZE - PADDING_BOTTOM) / 2}
        textAnchor="middle"
        fontSize="11"
        fill="var(--text-muted)"
        transform={`rotate(-90 10 ${(PADDING_TOP + CHART_SIZE - PADDING_BOTTOM) / 2})`}
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
