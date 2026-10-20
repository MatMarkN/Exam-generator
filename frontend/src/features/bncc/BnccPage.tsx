import { useState } from "react"
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { BookCheck, Check, ChevronDown, LoaderCircle, LockKeyhole } from "lucide-react"
import { Link } from "react-router-dom"
import { AsyncState } from "@/components/async-state"
import { PageHeader } from "@/components/page-header"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { NativeSelect } from "@/components/ui/native-select"
import { api, getErrorMessage } from "@/lib/api/client"
import { environment } from "@/lib/environment"
import type { BnccRelease } from "@/lib/api/types"
import { CompetencyReviewCard, SkillReviewCard } from "./review-ui"

export default function BnccPage() {
  const [documentSelection, setDocumentSelection] = useState("")
  const [subject, setSubject] = useState("matematica")
  const [grade, setGrade] = useState(6)
  const docs = useQuery({ queryKey: ["bncc", "documents"], queryFn: api.bnccDocuments })
  const subjects = useQuery({ queryKey: ["subjects"], queryFn: api.subjects })
  const grades = useQuery({ queryKey: ["grades"], queryFn: api.grades })
  const documents = (docs.data?.items ?? []).filter((document) => document.status !== "archived")
  const documentId = Number(documentSelection || documents[0]?.id || 0)
  const releases = useQuery({ queryKey: ["bncc", documentId, "releases"], queryFn: () => api.bnccReleases(documentId), enabled: documentId > 0 })
  const scope = releases.data?.items.find((item) => item.subject.code === subject && item.grade.year_number === grade)
  const pageError = docs.error || subjects.error || grades.error
  return <div className="space-y-6">
    <PageHeader eyebrow="Referência curricular" title="Revisão da BNCC" description="Confira o conteúdo extraído do PDF e libere uma matéria e ano de cada vez." />
    {environment.isDemo && <p className="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-900">Este ambiente usa currículo sintético. A revisão e a liberação do documento oficial acontecem no ambiente principal.</p>}
    <AsyncState loading={docs.isPending || subjects.isPending || grades.isPending} error={pageError ? getErrorMessage(pageError) : null} empty={!docs.isPending && !docs.isError && documents.length === 0 ? "Nenhum documento foi importado. Importe e estruture o PDF da BNCC pela API para iniciar a revisão." : false} onRetry={() => { void docs.refetch(); void subjects.refetch(); void grades.refetch() }}>
      <Card className="shadow-none"><CardContent className="grid gap-4 pt-6 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_160px]">
        <div className="min-w-0 space-y-2 [&_[data-slot=native-select-wrapper]]:w-full"><Label htmlFor="bncc-document">Documento</Label><NativeSelect id="bncc-document" className="w-full" value={String(documentId)} onChange={(event) => setDocumentSelection(event.target.value)}>{documents.map((document) => <option key={document.id} value={document.id}>{document.title} · revisão {document.revision}</option>)}</NativeSelect></div>
        <div className="min-w-0 space-y-2 [&_[data-slot=native-select-wrapper]]:w-full"><Label htmlFor="bncc-subject">Matéria</Label><NativeSelect id="bncc-subject" value={subject} onChange={(event) => setSubject(event.target.value)}>{subjects.data?.map((item) => <option key={item.id} value={item.code}>{item.name}</option>)}</NativeSelect></div>
        <div className="space-y-2 [&_[data-slot=native-select-wrapper]]:w-full"><Label htmlFor="bncc-grade">Ano escolar</Label><NativeSelect id="bncc-grade" value={grade} onChange={(event) => setGrade(Number(event.target.value))}>{grades.data?.map((item) => <option key={item.id} value={item.year_number}>{item.name}</option>)}</NativeSelect></div>
      </CardContent></Card>
      {documentId > 0 && <AsyncState loading={releases.isPending} error={releases.isError ? getErrorMessage(releases.error) : null} onRetry={() => void releases.refetch()}>
        {scope ? <ScopeReview key={documentId + ":" + subject + ":" + grade} documentId={documentId} scope={scope} /> : <p className="rounded-xl border border-dashed bg-white p-6 text-sm leading-relaxed text-muted-foreground">Esta combinação de matéria e ano não está disponível no documento estruturado. Selecione outra combinação ou conclua a estruturação pela API.</p>}
      </AsyncState>}
    </AsyncState>
  </div>
}

function ScopeReview({ documentId, scope }: { documentId: number; scope: BnccRelease }) {
  const client = useQueryClient()
  const filter = { subject: scope.subject.code, grade: scope.grade.year_number }
  const skills = useInfiniteQuery({
    queryKey: ["bncc", documentId, "skills", filter], initialPageParam: 0,
    queryFn: ({ pageParam }) => api.bnccSkills(documentId, { ...filter, offset: pageParam, limit: 20 }),
    getNextPageParam: (page) => page.offset + page.skills.length < page.total ? page.offset + page.skills.length : undefined,
  })
  const competencies = useQuery({ queryKey: ["bncc", documentId, "competencies", filter], queryFn: () => api.bnccCompetencies(documentId, filter) })
  const publish = useMutation({
    mutationFn: () => api.publishBnccScope(documentId, filter), retry: false,
    onSuccess: async () => { await client.invalidateQueries({ queryKey: ["bncc"] }) },
  })
  const ready = scope.status === "ready"
  const reviewed = scope.review.skills.total > 0 && scope.review.competencies.total > 0 && scope.review.skills.approved === scope.review.skills.total && scope.review.competencies.approved === scope.review.competencies.total
  const readOnly = ready || environment.isDemo
  return <div className="space-y-7">
    <section className={"rounded-2xl border p-5 sm:p-6 " + (ready ? "border-primary/20 bg-primary/5" : "border-border bg-white")} aria-label="Liberação curricular">
      <div className="flex flex-wrap items-start justify-between gap-5"><div className="max-w-2xl"><p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary"><BookCheck className="size-4" aria-hidden="true" />{scope.subject.name} · {scope.grade.name}</p><h2 className="mt-3 text-xl font-semibold">{ready ? "Conteúdo liberado para gerar provas" : reviewed ? "Revisão concluída. Pronto para liberar." : "Confira os itens antes de liberar"}</h2><p className="mt-2 text-sm leading-relaxed text-muted-foreground">{ready ? "A geração pode utilizar as habilidades e competências desta combinação. Os demais conteúdos mantêm sua própria revisão." : "Abra cada item, compare a descrição e os dados com as páginas do PDF oficial e registre sua decisão. A liberação exige todos os itens desta combinação aprovados."}</p></div>{ready ? <Button asChild><Link to="/"><Check className="size-4" />Criar prova</Link></Button> : <Button type="button" disabled={!reviewed || publish.isPending || environment.isDemo} onClick={() => publish.mutate()}>{publish.isPending ? <LoaderCircle className="size-4 animate-spin" /> : <LockKeyhole className="size-4" />}Liberar matéria e ano</Button>}</div>
      <div className="mt-5 grid gap-3 sm:grid-cols-2">{(["skills", "competencies"] as const).map((kind) => { const counts = scope.review[kind]; return <div key={kind} className="rounded-xl border border-border/70 bg-white/70 px-4 py-3"><p className="text-sm font-semibold">{counts.approved} de {counts.total} {kind === "skills" ? "habilidades aprovadas" : "competências aprovadas"}</p><p className="mt-1 text-xs text-muted-foreground">{counts.pending} pendentes · {counts.rejected} rejeitadas</p></div> })}</div>
      <p className="mt-4 text-xs leading-relaxed text-muted-foreground">As competências gerais e de área, e algumas habilidades, podem ser compartilhadas entre anos. Itens utilizados por uma liberação ficam protegidos para preservar as provas e os contextos já gerados.</p>
      {publish.isError && <p role="alert" className="mt-4 text-sm text-destructive">{getErrorMessage(publish.error)}</p>}
    </section>
    <section aria-label="Habilidades curriculares"><h2 className="mb-2 text-xl font-semibold">Habilidades</h2><p className="mb-4 text-sm text-muted-foreground">Confira o código, os anos escolares, a descrição e os trechos de origem.</p><AsyncState loading={skills.isPending} error={skills.isError ? getErrorMessage(skills.error) : null} onRetry={() => void skills.refetch()}><div className="space-y-3">{skills.data?.pages.flatMap((page) => page.skills).map((skill) => <SkillReviewCard key={skill.id} documentId={documentId} skill={skill} readOnly={readOnly} />)}{skills.hasNextPage && <Button variant="outline" className="w-full" disabled={skills.isFetchingNextPage} onClick={() => void skills.fetchNextPage()}>{skills.isFetchingNextPage ? <LoaderCircle className="size-4 animate-spin" /> : <ChevronDown className="size-4" />}Carregar mais habilidades</Button>}</div></AsyncState></section>
    <section aria-label="Competências curriculares"><h2 className="mb-2 text-xl font-semibold">Competências</h2><p className="mb-4 text-sm text-muted-foreground">Revise as competências gerais e as competências de área ou matéria aplicáveis.</p><AsyncState loading={competencies.isPending} error={competencies.isError ? getErrorMessage(competencies.error) : null} onRetry={() => void competencies.refetch()}><div className="space-y-3">{competencies.data?.competencies.map((item) => <CompetencyReviewCard key={item.id} documentId={documentId} competency={item} readOnly={readOnly} />)}</div></AsyncState></section>
  </div>
}
