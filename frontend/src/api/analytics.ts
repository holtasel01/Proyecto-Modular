import { apiRequest } from "./client";
import type { StudentClusterResult } from "../types";

export function getStudentClusters(): Promise<StudentClusterResult> {
  return apiRequest<StudentClusterResult>("/analytics/student-clusters");
}
