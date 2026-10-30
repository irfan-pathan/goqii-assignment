import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

export default function Layout() {
  const { user, isAdmin, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <>
      <header className="nav">
        <strong>Task Manager</strong>
        <nav>
          <NavLink to="/" end>Tasks</NavLink>
          {isAdmin && <NavLink to="/admin">Admin</NavLink>}
        </nav>
        <span className="spacer" />
        <span className="muted">
          {user.name} <span className={`badge role-${user.role}`}>{user.role}</span>
        </span>
        <button className="btn" onClick={handleLogout}>Log out</button>
      </header>
      <main className="container">
        <Outlet />
      </main>
    </>
  );
}
