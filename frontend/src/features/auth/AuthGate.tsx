import { Navigate, Outlet } from "react-router-dom"
import { LoaderCircle, RefreshCw } from "lucide-react"
import { Button } from "@/components/ui/button"
import { getErrorMessage } from "@/lib/api/client"
import { useAuth } from "./AuthProvider"

export default function AuthGate() {
  const auth = useAuth()
  if (auth.loading) return <main className="flex min-h-screen items-center justify-center gap-3 px-6 text-sm text-muted-foreground" role="status"><LoaderCircle className="size-5 animate-spin" aria-hidden="true" />Conferindo sua sessão…</main>
  if (auth.error) return <main className="mx-auto max-w-lg px-6 py-24"><h1 className="text-2xl font-semibold">Não foi possível conferir sua sessão</h1><p role="alert" className="mt-4 text-sm leading-relaxed text-muted-foreground">{getErrorMessage(auth.error)}</p><Button className="mt-6" onClick={() => void auth.refresh()}><RefreshCw className="size-4" />Tentar novamente</Button></main>
  return auth.user ? <Outlet /> : <Navigate to="/entrar" replace />
}
