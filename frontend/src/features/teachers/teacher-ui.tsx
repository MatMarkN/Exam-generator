import type { ReactNode } from "react"
import { AlertCircle, Check } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

export function Notice({ children, error = false }: { children: ReactNode; error?: boolean }) {
  return <div role={error ? "alert" : "status"} className={"flex items-start gap-2.5 rounded-xl px-4 py-3 text-sm " + (error ? "bg-destructive/5 text-destructive" : "bg-primary/5 text-primary")}>
    {error ? <AlertCircle className="mt-0.5 size-4 shrink-0" /> : <Check className="mt-0.5 size-4 shrink-0" />}
    <span>{children}</span>
  </div>
}

interface TeacherFieldProps {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  error?: string
  hint?: string
  optional?: boolean
  placeholder?: string
  type?: "text" | "email" | "number"
  autoComplete?: string
  autoFocus?: boolean
  list?: string
  min?: number
  max?: number
}

export function TeacherField({ id, label, value, onChange, error, hint, optional, ...inputProps }: TeacherFieldProps) {
  const descriptionId = error ? id + "-error" : hint ? id + "-hint" : undefined
  return <div>
    <Label htmlFor={id}>{label}{optional && <span className="ml-1 font-normal text-muted-foreground">(opcional)</span>}</Label>
    <Input {...inputProps} id={id} value={value} onChange={(event) => onChange(event.target.value)} className="mt-2" aria-invalid={!!error} aria-describedby={descriptionId} />
    {error ? <p id={id + "-error"} className="mt-1.5 text-sm text-destructive">{error}</p> : hint ? <p id={id + "-hint"} className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{hint}</p> : null}
  </div>
}

export function initials(name: string) {
  return name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => Array.from(part)[0]).join("").toLocaleUpperCase("pt-BR")
}
