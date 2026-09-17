import { apiRequest } from "./client";
import type { StudentClusterResult } from "../types";

export function getStudentClusters(forceRefresh = false): Promise<StudentClusterResult> {
  return apiRequest<StudentClusterResult>(
    `/analytics/student-clusters${forceRefresh ? "?refresh=1" : ""}`,
  );
}
