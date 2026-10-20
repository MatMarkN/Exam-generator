import { useId, useRef, useState, type ReactNode } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { Check, ChevronDown, FileText, LoaderCircle, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { api, getErrorMessage } from "@/lib/api/client"
import type { BnccCompetency, BnccReviewRequest, BnccSkill } from "@/lib/api/types"
import { AsyncState } from "@/components/async-state"

export function ReviewStatus({ status }: { status: string }) {
  const label = status === "approved" ? "Aprovado" : status === "rejected" ? "Rejeitado" : "Pendente"
  return <span className={"inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold " + (status === "approved" ? "bg-primary/10 text-primary" : status === "rejected" ? "bg-destructive/10 text-destructive" : "bg-amber-50 text-amber-800")}>{label}</span>
}

export function SourceExcerpt({ start, end, children }: { start: number; end: number; children: ReactNode }) {
  return <div className="space-y-2 rounded-xl border bg-muted/35 p-4">
    <p className="flex items-center gap-2 text-xs font-semibold text-muted-foreground"><FileText className="size-3.5" aria-hidden="true" />{start === end ? "Página " + start : "Páginas " + start + "–" + end} do PDF</p>
    <blockquote className="whitespace-pre-wrap break-words text-sm leading-relaxed text-muted-foreground">{children}</blockquote>
  </div>
}

function ReviewEditor({ documentId, kind, itemKey, note, readOnly }: { documentId: number; kind: "skill" | "competency"; itemKey: number | string; note?: string | null; readOnly: boolean }) {
  const id = useId()
  const [reviewNote, setReviewNote] = useState(note || "")
  const [noteError, setNoteError] = useState("")
  const guard = useRef(false)
  const client = useQueryClient()
  const mutation = useMutation({
    retry: false,
    mutationFn: (body: BnccReviewRequest) => kind === "skill" ? api.reviewBnccSkill(documentId, String(itemKey), body) : api.reviewBnccCompetency(documentId, Number(itemKey), body),
    onSuccess: async () => { await client.invalidateQueries({ queryKey: ["bncc"] }) },
    onSettled: () => { guard.current = false },
  })
  function review(status: BnccReviewRequest["status"]) {
    if (guard.current || readOnly) return
    if (!reviewNote.trim()) { setNoteError("Registre o que foi conferido ou o motivo da rejeição."); document.getElementById(id)?.focus(); return }
    guard.current = true
    setNoteError("")
    mutation.mutate({ status, note: reviewNote.trim() })
  }
  if (readOnly) return <p className="rounded-lg bg-primary/5 p-3 text-xs leading-relaxed text-muted-foreground">Este item está disponível para consulta. A revisão exige uma conta de administrador e um conteúdo ainda editável. Itens que já integram uma liberação curricular, que pode ser de outra matéria ou ano, só podem ser corrigidos em uma nova revisão do documento.</p>
  return <div className="space-y-3 border-t pt-4">
    <div className="space-y-2"><Label htmlFor={id}>Nota da revisão</Label><Textarea id={id} value={reviewNote} maxLength={1000} disabled={mutation.isPending} aria-invalid={!!noteError} aria-describedby={noteError ? id + "-error" : undefined} onChange={(event) => { setReviewNote(event.target.value); setNoteError(""); mutation.reset() }} placeholder="Ex.: descrição, ano e página conferidos com o PDF oficial." />{noteError && <p id={id + "-error"} role="alert" className="text-xs text-destructive">{noteError}</p>}</div>
    <div className="flex flex-wrap gap-2"><Button type="button" size="sm" disabled={mutation.isPending} onClick={() => review("approved")}>{mutation.isPending ? <LoaderCircle className="size-4 animate-spin" /> : <Check className="size-4" />}Aprovar item</Button><Button type="button" size="sm" variant="outline" disabled={mutation.isPending} onClick={() => review("rejected")}><X className="size-4" />Rejeitar item</Button><Button type="button" size="sm" variant="ghost" disabled={mutation.isPending} onClick={() => review("pending")}>Voltar a pendente</Button></div>
    {mutation.isError && <p role="alert" className="text-sm text-destructive">{getErrorMessage(mutation.error)}</p>}
    {mutation.isSuccess && <p role="status" className="text-sm text-primary">Revisão registrada.</p>}
  </div>
}

function ReviewCard({ title, status, description, children }: { title: string; status: string; description: string; children: (open: boolean) => ReactNode }) {
  const [open, setOpen] = useState(false)
  const id = useId()
  return <article className="min-w-0 overflow-hidden rounded-xl border bg-white">
    <button type="button" className="flex w-full items-start gap-3 p-4 text-left outline-none hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring" onClick={() => setOpen(!open)} aria-expanded={open} aria-controls={id}>
      <div className="min-w-0 flex-1"><p className="font-semibold text-primary">{title}</p><p className={"mt-2 text-sm leading-relaxed " + (open ? "" : "line-clamp-2")}>{description}</p></div><ReviewStatus status={status} /><ChevronDown className={"mt-1 size-4 shrink-0 text-muted-foreground transition-transform " + (open ? "rotate-180" : "")} aria-hidden="true" />
    </button>
    <div id={id} hidden={!open} className="space-y-4 border-t p-4">{children(open)}</div>
  </article>
}

export function SkillReviewCard({ documentId, skill, readOnly }: { documentId: number; skill: BnccSkill; readOnly: boolean }) {
  return <ReviewCard title={skill.code} status={skill.review_status} description={skill.description}>{(open) => <SkillDetail documentId={documentId} skill={skill} open={open} readOnly={readOnly || skill.locked} />}</ReviewCard>
}

function SkillDetail({ documentId, skill, open, readOnly }: { documentId: number; skill: BnccSkill; open: boolean; readOnly: boolean }) {
  const detail = useQuery({ queryKey: ["bncc", documentId, "skill", skill.code], queryFn: () => api.bnccSkill(documentId, skill.code), enabled: open })
  return <AsyncState loading={detail.isPending} error={detail.isError ? getErrorMessage(detail.error) : null} onRetry={() => void detail.refetch()}>
    {detail.data && <><p className="text-xs text-muted-foreground">{detail.data.subject.name} · {detail.data.grades.map((grade) => grade + "º ano").join(", ")}</p>{detail.data.contexts.map((context) => <SourceExcerpt key={context.id} start={context.page_start} end={context.page_end}>{context.source_excerpt}</SourceExcerpt>)}<ReviewEditor documentId={documentId} kind="skill" itemKey={skill.code} note={skill.review_note} readOnly={readOnly || detail.data.locked} /></>}
  </AsyncState>
}

export function CompetencyReviewCard({ documentId, competency, readOnly }: { documentId: number; competency: BnccCompetency; readOnly: boolean }) {
  const label = competency.scope === "general" ? "Competência geral" : competency.scope === "area" ? "Competência da área" : "Competência da matéria"
  return <ReviewCard title={label + " " + competency.number} status={competency.review_status} description={competency.description}>{() => <><SourceExcerpt start={competency.page_start} end={competency.page_end}>{competency.source_excerpt}</SourceExcerpt><ReviewEditor documentId={documentId} kind="competency" itemKey={competency.id} note={competency.review_note} readOnly={readOnly || competency.locked} /></>}</ReviewCard>
}
