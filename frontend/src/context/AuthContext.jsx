import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { authenticate, logoutRequest, refreshSession, setSessionLostHandler } from '../api/client.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [booting, setBooting] = useState(true); // true while we try to restore a session on page load

  useEffect(() => {
    setSessionLostHandler(() => setUser(null)); // refresh failed mid-session => back to login
    refreshSession()
      .then((data) => setUser(data.user))
      .catch(() => setUser(null))
      .finally(() => setBooting(false));
  }, []);

  const login = useCallback(async (email, password) => {
    const data = await authenticate('/auth/login', { email, password });
    setUser(data.user);
  }, []);

  const register = useCallback(async (name, email, password) => {
    const data = await authenticate('/auth/register', { name, email, password });
    setUser(data.user);
  }, []);

  const logout = useCallback(async () => {
    try {
      await logoutRequest();
    } finally {
      setUser(null);
    }
  }, []);

  const value = useMemo(
    () => ({ user, booting, isAdmin: user?.role === 'admin', login, register, logout }),
    [user, booting, login, register, logout]
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);
