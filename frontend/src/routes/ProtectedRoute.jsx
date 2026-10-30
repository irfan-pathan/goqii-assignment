import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

/** Requires a logged-in user; `adminOnly` additionally requires the admin role. */
export default function ProtectedRoute({ adminOnly = false }) {
  const { user, booting, isAdmin } = useAuth();
  const location = useLocation();

  if (booting) return <p className="center muted">Loading…</p>;
  if (!user) return <Navigate to="/login" state={{ from: location }} replace />;
  if (adminOnly && !isAdmin) return <Navigate to="/" replace />;
  return <Outlet />;
}
