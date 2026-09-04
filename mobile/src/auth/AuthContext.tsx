import { createContext, type PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react';
import { login as apiLogin } from '../api/auth';
import { clearToken, loadToken, saveToken } from './tokenStorage';

interface AuthContextValue {
  token: string | null;
  loading: boolean;
  login: (email: string, password: string, deviceName: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadToken()
      .then(setToken)
      .finally(() => setLoading(false));
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      token,
      loading,
      async login(email, password, deviceName) {
        const newToken = await apiLogin(email, password, deviceName);
        await saveToken(newToken);
        setToken(newToken);
      },
      async logout() {
        await clearToken();
        setToken(null);
      },
    }),
    [token, loading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (context === null) {
    throw new Error('useAuth doit être utilisé sous AuthProvider.');
  }

  return context;
}
