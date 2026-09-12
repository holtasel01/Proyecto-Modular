import { Navigate, Route, Routes } from "react-router-dom";
import { LoginPage } from "./auth/LoginPage";
import { ProtectedRoute } from "./auth/ProtectedRoute";
import { Layout } from "./components/Layout";
import { Home } from "./pages/Home";
import { StudentHistory } from "./pages/student/StudentHistory";
import { AdminStudents } from "./pages/admin/AdminStudents";
import { AdminStudentDetail } from "./pages/admin/AdminStudentDetail";
import { AdminAttendance } from "./pages/admin/AdminAttendance";
import { AdminIncidents } from "./pages/admin/AdminIncidents";
import { AdminDevices } from "./pages/admin/AdminDevices";
import { AdminSettings } from "./pages/admin/AdminSettings";

export function AppRouter() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<Layout />}>
          <Route index element={<Home />} />

          <Route element={<ProtectedRoute role="student" />}>
            <Route path="historial" element={<StudentHistory />} />
          </Route>

          <Route element={<ProtectedRoute role="admin" />}>
            <Route path="estudiantes" element={<AdminStudents />} />
            <Route path="estudiantes/:id" element={<AdminStudentDetail />} />
            <Route path="asistencias" element={<AdminAttendance />} />
            <Route path="incidencias" element={<AdminIncidents />} />
            <Route path="dispositivos" element={<AdminDevices />} />
            <Route path="configuracion" element={<AdminSettings />} />
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
