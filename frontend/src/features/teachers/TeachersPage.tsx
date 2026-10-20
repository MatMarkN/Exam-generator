import { useEffect, useState } from "react"
import { useInfiniteQuery, useQuery } from "@tanstack/react-query"
import { ArrowUpRight, Check, ChevronDown, LoaderCircle, Plus, School, UserRound } from "lucide-react"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/page-header"
import { api, getErrorMessage } from "@/lib/api/client"
import { NewTeacherForm, TeacherEditor } from "./TeacherForms"
import { initials, Notice } from "./teacher-ui"

export default function TeachersPage() {
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [creating, setCreating] = useState(false)
  const teachers = useInfiniteQuery({
    queryKey: ["teachers", "list"],
    queryFn: ({ pageParam }) => api.teachers({ limit: 12, ...(pageParam ? { before_id: pageParam } : {}) }),
    initialPageParam: undefined as number | undefined,
    getNextPageParam: (page) => page.next_before_id ?? undefined,
  })
  const allTeachers = teachers.data?.pages.flatMap((page) => page.items) ?? []
  const firstId = allTeachers[0]?.id
  useEffect(() => {
    if (selectedId === null && !creating && firstId !== undefined) setSelectedId(firstId)
  }, [selectedId, creating, firstId])
  const detail = useQuery({
    queryKey: ["teachers", selectedId],
    queryFn: () => api.teacher(selectedId!),
    enabled: selectedId !== null && !creating,
  })
  return <div className="space-y-7">
    <PageHeader eyebrow="Organize seu dia a dia" title="Professores" description="Deixe seus dados prontos e ganhe tempo na próxima prova." actions={<Button onClick={() => { setCreating(true); setSelectedId(null) }}><Plus className="size-4" />Novo professor</Button>} />
    <div className="grid items-start gap-6 lg:grid-cols-[300px_minmax(0,1fr)] xl:grid-cols-[320px_minmax(0,1fr)]">
      <Card className="overflow-hidden border-border/70 shadow-none">
        <CardHeader className="pb-4"><CardTitle className="text-base">Seus professores</CardTitle><CardDescription>Selecione um cadastro para editar.</CardDescription></CardHeader>
        <CardContent className="space-y-3 px-3 pb-3">
          {teachers.isPending && <div role="status" className="space-y-3 p-2"><span className="sr-only">Carregando professores…</span>{[1, 2, 3].map((item) => <div key={item} className="h-17 animate-pulse rounded-xl bg-muted" />)}</div>}
          {teachers.isError && <div className="space-y-3 p-2"><Notice error>{getErrorMessage(teachers.error)}</Notice><Button variant="outline" size="sm" onClick={() => void teachers.refetch()}>Tentar novamente</Button></div>}
          {!teachers.isPending && !teachers.isError && allTeachers.length === 0 && <div className="px-3 py-8 text-center"><UserRound className="mx-auto mb-3 size-8 text-muted-foreground/60" /><p className="font-medium">Seu primeiro cadastro</p><p className="mt-2 text-sm leading-relaxed text-muted-foreground">Adicione um professor para reutilizar seus dados nas provas.</p><Button variant="outline" size="sm" className="mt-4" onClick={() => { setCreating(true); setSelectedId(null) }}>Cadastrar professor</Button></div>}
          {allTeachers.map((item) => <button key={item.id} type="button" aria-pressed={selectedId === item.id && !creating} onClick={() => { setSelectedId(item.id); setCreating(false) }} className={"flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 " + (selectedId === item.id && !creating ? "border-primary/20 bg-primary/5" : "border-transparent hover:bg-muted/60")}>
            <span className={"flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold " + (selectedId === item.id && !creating ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>{initials(item.name)}</span>
            <span className="min-w-0 flex-1"><span className="block truncate text-sm font-semibold">{item.name}</span><span className="mt-0.5 block truncate text-xs text-muted-foreground">{item.settings.school_name || "Escola ainda não informada"}</span></span>
            {selectedId === item.id && !creating && <Check className="size-4 shrink-0 text-primary" />}
          </button>)}
          {teachers.hasNextPage && <Button className="w-full" variant="ghost" size="sm" disabled={teachers.isFetchingNextPage} onClick={() => void teachers.fetchNextPage()}>{teachers.isFetchingNextPage ? <LoaderCircle className="size-4 animate-spin" /> : <ChevronDown className="size-4" />}Carregar mais</Button>}
        </CardContent>
      </Card>
      <div className="min-w-0">
        {creating ? <NewTeacherForm onCreated={(teacher) => { setSelectedId(teacher.id); setCreating(false) }} /> : selectedId !== null ? (
          detail.isPending ? <div role="status" className="flex min-h-64 items-center justify-center gap-3 rounded-2xl border border-border/70 bg-white text-muted-foreground"><LoaderCircle className="size-5 animate-spin" />Carregando cadastro…</div> :
          detail.isError ? <Card><CardContent className="space-y-4 pt-6"><Notice error>{getErrorMessage(detail.error)}</Notice><Button variant="outline" onClick={() => void detail.refetch()}>Tentar novamente</Button></CardContent></Card> :
          detail.data ? <TeacherEditor key={detail.data.id} teacher={detail.data} /> : null
        ) : <div className="flex min-h-96 flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-white/60 px-7 py-12 text-center">
          <div className="mb-5 flex size-16 items-center justify-center rounded-2xl bg-primary/5 text-primary"><School className="size-7" /></div>
          <h2 className="text-xl font-semibold tracking-tight">Mais tempo para ensinar</h2>
          <p className="mt-3 max-w-sm text-sm leading-relaxed text-muted-foreground">Salve os dados do professor uma vez e aproveite um cabeçalho pronto nas próximas avaliações.</p>
          <Button className="mt-6" onClick={() => setCreating(true)}><Plus className="size-4" />Cadastrar primeiro professor</Button>
          <Button asChild variant="ghost" size="sm" className="mt-3"><Link to="/">Criar uma prova sem cadastro<ArrowUpRight className="size-4" /></Link></Button>
        </div>}
      </div>
    </div>
  </div>
}
