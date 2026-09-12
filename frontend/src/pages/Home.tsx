import { useAuth } from "../auth/AuthContext";
import { StudentDashboard } from "./student/StudentDashboard";
import { AdminAttendance } from "./admin/AdminAttendance";

export function Home() {
  const { user } = useAuth();
  return user?.role === "admin" ? <AdminAttendance /> : <StudentDashboard />;
}
