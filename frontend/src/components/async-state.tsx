import type { ReactNode } from "react"
import { LoaderCircle, TriangleAlert } from "lucide-react"
import { Button } from "@/components/ui/button"

export function AsyncState({ loading, error, empty, onRetry, children }: {
  loading?: boolean; error?: string | null; empty?: string | boolean; onRetry?: () => void; children?: ReactNode
}) {
  if (loading) return <div role="status" className="flex min-h-48 items-center justify-center gap-3 text-sm text-muted-foreground"><LoaderCircle className="size-5 animate-spin" aria-hidden="true" />Carregando…</div>
  if (error) return <div role="alert" className="rounded-xl border border-destructive/20 bg-white p-6"><TriangleAlert className="mb-3 size-5 text-destructive" aria-hidden="true" /><p className="text-sm">{error}</p>{onRetry && <Button variant="outline" className="mt-4" onClick={onRetry}>Tentar novamente</Button>}</div>
  if (empty) return <div className="rounded-xl border border-dashed bg-white px-6 py-12 text-center text-muted-foreground">{typeof empty === "string" ? empty : "Nenhum registro por aqui ainda."}</div>
  return children
}
