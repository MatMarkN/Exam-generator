import { useRef, useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { ArrowLeft, ArrowRight, BookOpen, Check, CircleHelp, ClipboardList, FileCheck2, Layers3, LoaderCircle, NotebookPen, RefreshCw, Sparkles } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader } from "@/components/ui/card"
import { api, getErrorMessage } from "@/lib/api/client"
import { environment } from "@/lib/environment"
import type { ExamRequest } from "@/lib/api/types"
import { ContentStep, HeaderStep, QuestionsStep, type ChangeExamValue } from "./create-exam-fields"
import { buildCreateExamRequest, initialCreateExamValues, parseTopics, stepFields, validateCreateExam, type CreateExamErrors } from "./create-exam-form"

const steps = [
  { label: "Conteúdo", description: "O que avaliar", heading: "O que seus alunos aprenderam?", detail: "Escolha os conteúdos e conte o que foi trabalhado em sala.", Icon: BookOpen },
  { label: "Questões", description: "Como avaliar", heading: "Uma prova do seu jeito", detail: "Defina a quantidade e os tipos de questão que fazem sentido para a turma.", Icon: Layers3 },
  { label: "Cabeçalho", description: "Revisar e gerar", heading: "Os últimos detalhes", detail: "Personalize o cabeçalho e confira o resumo antes de gerar sua prova.", Icon: FileCheck2 },
]

export default function CreateExamPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [step, setStep] = useState(0)
  const [values, setValues] = useState(() => initialCreateExamValues(environment.isDemo))
  const [errors, setErrors] = useState<CreateExamErrors>({})
  const submitGuard = useRef(false)
  const headingRef = useRef<HTMLHeadingElement>(null)
  const subjectsQuery = useQuery({ queryKey: ["subjects"], queryFn: () => api.subjects() })
  const gradesQuery = useQuery({ queryKey: ["grades"], queryFn: () => api.grades() })
  const teachersQuery = useInfiniteQuery({
    queryKey: ["teachers", "selection"],
    initialPageParam: undefined as number | undefined,
    queryFn: ({ pageParam }) => api.teachers({ limit: 100, ...(pageParam ? { before_id: pageParam } : {}) }),
    getNextPageParam: (page) => page.next_before_id ?? undefined,
  })
  const teachers = teachersQuery.data?.pages.flatMap((page) => page.items) ?? []
  const subjects = subjectsQuery.data ?? []
  const grades = gradesQuery.data ?? []
  const selectedTeacher = teachers.find((teacher) => String(teacher.id) === values.teacherId)
  const selectedSubject = subjects.find((subject) => subject.code === values.subject)
  const selectedGrade = grades.find((grade) => String(grade.year_number) === values.grade)
  const topics = parseTopics(values.topicText)
  const catalogError = subjectsQuery.error || gradesQuery.error
  const catalogsLoading = subjectsQuery.isPending || gradesQuery.isPending

  const generation = useMutation({
    mutationFn: (request: ExamRequest) => api.generate(request),
    retry: false,
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: ["generations"] })
      navigate("/provas/" + result.exam.id)
    },
    onSettled: () => { submitGuard.current = false },
  })

  const onChange: ChangeExamValue = (field, value) => {
    setValues((previous) => ({ ...previous, [field]: value }))
    setErrors((previous) => {
      const next = { ...previous }
      delete next[field]
      if (["choiceCount", "openCount", "trueFalseCount"].includes(field)) delete next.questionCount
      return next
    })
    if (generation.isError) generation.reset()
  }

  function focusStepHeading() {
    requestAnimationFrame(() => headingRef.current?.focus())
  }

  function nextStep() {
    const validation = validateCreateExam(values, environment.isDemo)
    const relevant = Object.fromEntries(Object.entries(validation).filter(([field]) => stepFields[step].includes(field as keyof typeof values))) as CreateExamErrors
    setErrors(relevant)
    if (Object.keys(relevant).length) {
      document.getElementById(Object.keys(relevant)[0])?.focus()
      return
    }
    setStep((previous) => Math.min(previous + 1, steps.length - 1))
    focusStepHeading()
  }

  function generateExam() {
    if (submitGuard.current || generation.isPending) return
    const validation = validateCreateExam(values, environment.isDemo)
    if (values.teacherId && (!selectedTeacher || teachersQuery.isError)) validation.teacherId = "Carregue e selecione um professor válido antes de gerar."
    const invalidStep = stepFields.findIndex((fields) => fields.some((field) => validation[field]))
    setErrors(validation)
    if (invalidStep !== -1) {
      setStep(invalidStep)
      focusStepHeading()
      return
    }
    if (catalogsLoading || catalogError) return
    submitGuard.current = true
    generation.mutate(buildCreateExamRequest(values, environment.isDemo))
  }

  const active = steps[step]
  const questionTypeText = values.questionMode === "automatic"
    ? "Distribuição escolhida pela IA"
    : values.choiceCount + " múltipla escolha · " + values.openCount + " dissertativa · " + values.trueFalseCount + " V ou F"
  const fontFamily = values.useTeacherFormatting ? selectedTeacher?.settings.default_font_family ?? "Arial" : values.fontFamily
  const fontSize = values.useTeacherFormatting ? selectedTeacher?.settings.default_font_size ?? 12 : values.fontSize

  return (
    <div className="space-y-7">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary">Seu próximo planejamento</p>
          <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Vamos criar uma prova?</h1>
          <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">Você conhece sua turma. Transforme o que foi ensinado em uma avaliação bem organizada.</p>
        </div>
        <Link to="/provas" className="inline-flex items-center gap-2 text-sm font-semibold text-primary underline-offset-4 hover:underline">
          Minhas provas <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
      </header>

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <form onSubmit={(event) => event.preventDefault()} noValidate className="min-w-0 space-y-5" aria-label="Criar prova">
          <ol className="grid grid-cols-3 gap-2 rounded-2xl border border-border bg-card p-3 shadow-sm sm:gap-4 sm:p-4" aria-label="Etapas da criação">
            {steps.map(({ label, description }, index) => (
              <li key={label}>
                <button type="button" disabled={index > step || generation.isPending} onClick={() => { setStep(index); focusStepHeading() }} aria-current={step === index ? "step" : undefined}
                  className={"flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary/40 sm:gap-3 sm:px-2 " + (index > step ? "cursor-default text-muted-foreground" : "cursor-pointer")}>
                  <span className={"flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold sm:size-9 " + (index <= step ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
                    {index < step ? <Check className="size-4" aria-hidden="true" /> : String(index + 1).padStart(2, "0")}
                  </span>
                  <span className="min-w-0"><span className={"block text-xs font-semibold sm:text-sm " + (index === step ? "text-foreground" : "")}>{label}</span><span className="mt-0.5 hidden text-xs text-muted-foreground sm:block">{description}</span></span>
                </button>
              </li>
            ))}
          </ol>

          <Card className="gap-0 overflow-hidden rounded-2xl border-border py-0 shadow-sm">
            <CardHeader className="border-b border-border px-5 py-6 sm:px-7">
              <div className="flex items-start gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-primary"><active.Icon className="size-5" aria-hidden="true" /></div>
                <div>
                  <h2 ref={headingRef} tabIndex={-1} className="text-xl font-semibold tracking-tight outline-none">{active.heading}</h2>
                  <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{active.detail}</p>
                </div>
              </div>
            </CardHeader>
            <CardContent className="px-5 py-6 sm:px-7 sm:py-7">
              {catalogsLoading ? (
                <div className="space-y-4 py-5" role="status" aria-live="polite">
                  <p className="flex items-center gap-2 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin" aria-hidden="true" />Carregando matérias e anos escolares…</p>
                  <div className="h-11 animate-pulse rounded-lg bg-muted" /><div className="h-24 animate-pulse rounded-lg bg-muted" />
                </div>
              ) : catalogError ? (
                <div role="alert" className="rounded-xl border border-destructive/20 bg-destructive/5 p-5">
                  <h3 className="font-semibold">Não foi possível carregar os cadastros</h3>
                  <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{getErrorMessage(catalogError)}</p>
                  <Button type="button" className="mt-4" variant="outline" onClick={() => { void subjectsQuery.refetch(); void gradesQuery.refetch() }}><RefreshCw className="size-4" />Tentar novamente</Button>
                </div>
              ) : (
                <fieldset disabled={generation.isPending} className="min-w-0">
                  {step === 0 && <ContentStep values={values} errors={errors} onChange={onChange} isDemo={environment.isDemo} subjects={subjects} grades={grades} />}
                  {step === 1 && <QuestionsStep values={values} errors={errors} onChange={onChange} isDemo={environment.isDemo} />}
                  {step === 2 && <HeaderStep values={values} errors={errors} onChange={onChange} isDemo={environment.isDemo} teachers={teachers} teacherLoading={teachersQuery.isPending} teacherError={teachersQuery.isError ? getErrorMessage(teachersQuery.error) : undefined} onRetryTeachers={() => { void teachersQuery.refetch() }} onMoreTeachers={teachersQuery.hasNextPage ? () => { void teachersQuery.fetchNextPage() } : undefined} moreTeachersLoading={teachersQuery.isFetchingNextPage} />}
                </fieldset>
              )}
              {generation.isError && (
                <div className="mt-6 rounded-xl border border-destructive/25 bg-destructive/5 p-4" role="alert">
                  <h3 className="text-sm font-semibold">Não foi possível concluir o pedido</h3>
                  <p className="mt-2 text-sm leading-relaxed">{getErrorMessage(generation.error)}</p>
                  <Link to="/provas" className="mt-3 inline-block text-sm font-semibold text-primary underline underline-offset-4">Consultar minhas provas e tentativas</Link>
                </div>
              )}
              {generation.isPending && (
                <div className="mt-6 flex items-start gap-3 rounded-xl bg-primary/5 p-4 text-primary" role="status" aria-live="polite">
                  <LoaderCircle className="mt-0.5 size-5 shrink-0 animate-spin" aria-hidden="true" />
                  <div><p className="text-sm font-semibold">Preparando sua prova…</p><p className="mt-1 text-xs leading-relaxed">Estamos organizando as questões e o gabarito. Você será levado para a leitura quando estiver pronta.</p></div>
                </div>
              )}
            </CardContent>
            <div className="flex items-center justify-between gap-3 border-t border-border bg-muted/15 px-5 py-5 sm:px-7">
              <div>
                {step > 0 ? <Button type="button" variant="ghost" disabled={generation.isPending} onClick={() => { setStep((previous) => previous - 1); focusStepHeading() }}><ArrowLeft className="size-4" />Voltar</Button>
                  : <span className="hidden text-xs text-muted-foreground sm:inline">Etapa 1 de 3</span>}
              </div>
              {step < steps.length - 1
                ? <Button type="button" disabled={catalogsLoading || !!catalogError} onClick={nextStep}>Continuar <ArrowRight className="size-4" /></Button>
                : <Button type="button" disabled={generation.isPending || catalogsLoading || !!catalogError} onClick={generateExam}>{generation.isPending ? <LoaderCircle className="size-4 animate-spin" /> : <Sparkles className="size-4" />}{generation.isPending ? "Preparando…" : "Gerar prova"}</Button>}
            </div>
          </Card>
          <p className="flex items-start gap-2 px-1 text-xs leading-relaxed text-muted-foreground"><CircleHelp className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />As questões e o gabarito devem ser revisados pelo professor antes de usar a avaliação.</p>
        </form>

        <aside className="space-y-5 xl:sticky xl:top-6" aria-label="Resumo da configuração">
          <div className="overflow-hidden rounded-2xl border border-primary/15 bg-card shadow-sm">
            <div className="border-b border-primary/10 bg-primary/5 px-5 py-5">
              <div className="mb-4 flex size-11 items-center justify-center rounded-xl bg-background text-primary shadow-sm"><NotebookPen className="size-6" aria-hidden="true" /></div>
              <p className="text-xs font-semibold uppercase tracking-[0.15em] text-primary">Seu planejamento</p>
              <h2 className="mt-2 text-lg font-semibold">A prova está tomando forma</h2>
              <p className="mt-2 text-xs leading-relaxed text-muted-foreground">Confira suas escolhas enquanto prepara a avaliação.</p>
            </div>
            <div className="space-y-5 px-5 py-5">
              <SummaryItem label="Matéria e ano" value={selectedSubject && selectedGrade ? selectedSubject.name + " · " + selectedGrade.name : "Escolha na primeira etapa"} />
              <div><p className="text-xs text-muted-foreground">Tópicos</p><div className="mt-2 flex flex-wrap gap-1.5">{topics.length ? topics.map((topic) => <span key={topic} className="max-w-full break-words rounded-md bg-muted px-2 py-1 text-xs font-medium">{topic}</span>) : <p className="text-sm">Seus conteúdos aparecerão aqui</p>}</div></div>
              <SummaryItem label="Questões" value={(values.questionCount || "0") + " questões"} detail={questionTypeText} />
              <SummaryItem label="Professor" value={values.useTeacherHeader && selectedTeacher ? selectedTeacher.name : values.teacherName.trim() || "A definir no cabeçalho"} detail={values.className ? "Turma " + values.className : undefined} />
              <SummaryItem label="Apresentação" value={fontFamily + " · tamanho " + fontSize} />
            </div>
            <div className="flex items-start gap-2.5 border-t border-border px-5 py-4 text-xs leading-relaxed text-muted-foreground"><ClipboardList className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" /><span>Após gerar, confira a prova e abra o gabarito separadamente.</span></div>
          </div>
          {environment.isDemo && (
            <div className="rounded-xl border border-amber-200/80 bg-amber-50/70 p-4">
              <p className="text-xs font-semibold uppercase tracking-wide text-amber-900">Experimente com tranquilidade</p>
              <p className="mt-2 text-xs leading-relaxed text-amber-900/85">Este exemplo usa frações do 6º ano e três questões fixas. Você pode variar alternativas, afirmações e cabeçalho.</p>
            </div>
          )}
        </aside>
      </div>
    </div>
  )
}

function SummaryItem({ label, value, detail }: { label: string; value: string; detail?: string }) {
  return <div><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 break-words text-sm font-semibold leading-relaxed">{value}</p>{detail && <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{detail}</p>}</div>
}
