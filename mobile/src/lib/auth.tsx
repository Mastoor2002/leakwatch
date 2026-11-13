import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, session } from './api';
import type { Team, User } from './types';

type AuthState = {
  ready: boolean;
  user: User | null;
  team: Team | null;
  setTeam: (team: Team) => void;
  signIn: (email: string, password: string, apiUrl: string) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [user, setUser] = useState<User | null>(null);
  const [team, setTeamState] = useState<Team | null>(null);

  const refreshUser = useCallback(async () => {
    const { user } = await api.me();
    setUser(user);
    setTeamState((current) => user.teams.find((t) => t.id === current?.id) ?? user.current_team ?? user.teams[0] ?? null);
  }, []);

  useEffect(() => {
    (async () => {
      try {
        if (await session.getToken()) await refreshUser();
      } catch {
        // Token expired or server unreachable: start signed out.
      } finally {
        setReady(true);
      }
    })();
  }, [refreshUser]);

  const signIn = useCallback(async (email: string, password: string, apiUrl: string) => {
    await session.setApiUrl(apiUrl);
    const { token, user } = await api.login(email, password, deviceName());
    await session.setToken(token);
    setUser(user);
    setTeamState(user.current_team ?? user.teams[0] ?? null);
  }, []);

  const signOut = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // Already invalid server-side; clear locally regardless.
    }
    await session.clearToken();
    setUser(null);
    setTeamState(null);
  }, []);

  const value = useMemo<AuthState>(
    () => ({ ready, user, team, setTeam: setTeamState, signIn, signOut, refreshUser }),
    [ready, user, team, signIn, signOut, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider');
  return context;
}

function deviceName(): string {
  // Keep it simple: the API only uses this as a label in the token list.
  const { Platform } = require('react-native') as typeof import('react-native');
  return `${Platform.OS === 'ios' ? 'iPhone' : Platform.OS === 'android' ? 'Android' : 'Web'} app`;
}
