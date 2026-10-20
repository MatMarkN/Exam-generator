import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { api, ApiError } from "@/lib/api/client"
import type { AuthRegister, AuthSession, AuthUser } from "@/lib/api/types"
import { onSessionExpired, setCsrfToken } from "./session-state"

interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  error: unknown
  refresh: () => Promise<void>
  login: (email: string, password: string) => Promise<void>
  register: (input: AuthRegister) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const client = useQueryClient()
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<unknown>(null)
  const revision = useRef(0)
  const channel = useRef<BroadcastChannel | null>(null)
  const clearSession = useCallback(() => {
    revision.current++
    setCsrfToken(null)
    setUser(null)
    setLoading(false)
    client.clear()
  }, [client])
  const acceptSession = useCallback((session: AuthSession) => {
    client.clear()
    setCsrfToken(session.csrf_token)
    setUser(session.user)
    setError(null)
    setLoading(false)
  }, [client])

  const refresh = useCallback(async () => {
    const currentRevision = ++revision.current
    setLoading(true)
    setError(null)
    try {
      const session = await api.session()
      if (currentRevision === revision.current) acceptSession(session)
    }
    catch (failure) {
      if (currentRevision !== revision.current) return
      clearSession()
      if (!(failure instanceof ApiError && failure.status === 401)) setError(failure)
    } finally { if (currentRevision === revision.current) setLoading(false) }
  }, [acceptSession, clearSession])

  useEffect(() => onSessionExpired(clearSession), [clearSession])
  useEffect(() => { void refresh() }, [refresh])
  useEffect(() => {
    if (!window.BroadcastChannel) return
    const connection = new window.BroadcastChannel("prova-clara-auth")
    channel.current = connection
    connection.onmessage = (event: MessageEvent<unknown>) => { if (event.data === "auth-changed") void refresh() }
    return () => { channel.current = null; connection.close() }
  }, [refresh])

  const login = useCallback(async (email: string, password: string) => {
    revision.current++
    acceptSession(await api.login({ email, password }))
    channel.current?.postMessage("auth-changed")
  }, [acceptSession])
  const register = useCallback(async (input: AuthRegister) => {
    revision.current++
    acceptSession(await api.register(input))
    channel.current?.postMessage("auth-changed")
  }, [acceptSession])
  const logout = useCallback(async () => {
    revision.current++
    try { await api.logout() }
    catch (failure) { if (!(failure instanceof ApiError && failure.status === 401)) throw failure }
    clearSession()
    channel.current?.postMessage("auth-changed")
  }, [clearSession])

  return <AuthContext.Provider value={{ user, loading, error, refresh, login, register, logout }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error("A sessão precisa do AuthProvider.")
  return context
}
