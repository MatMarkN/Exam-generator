import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, ArrowRight, BookOpen, CircleAlert, KeyRound, LoaderCircle, Plus, RefreshCw } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { api, getErrorMessage } from '@/lib/api/client'
import { AnswerSheet, ExamSheet } from './ExamSheet'
import { formatTimestamp, parseExamId, previewTypography } from './read-format'

export default function ExamPage() {
  const { id: routeId } = useParams()
  const id = parseExamId(routeId)
  const [answerExamId, setAnswerExamId] = useState<number | null>(null)
  const view = answerExamId === id && id !== null ? 'answers' : 'exam'
  const examQuery = useQuery({
    queryKey: ['exam', id],
    queryFn: () => api.exam(id!),
    enabled: id !== null,
  })
  const answersQuery = useQuery({
    queryKey: ['answer-key', id],
    queryFn: () => api.answers(id!),
    enabled: id !== null && !!examQuery.data && view === 'answers',
  })

  if (id === null) {
    return <ReadError message="O endereço desta prova não é válido." />
  }
  if (examQuery.isPending) {
    return (
      <div role="status" className="flex min-h-80 flex-col items-center justify-center gap-4 text-stone-500">
        <LoaderCircle className="size-7 animate-spin text-emerald-700" aria-hidden />
        <p className="text-sm">Preparando a leitura da sua prova…</p>
      </div>
    )
  }
  if (examQuery.isError) {
    return <ReadError message={getErrorMessage(examQuery.error)} onRetry={() => void examQuery.refetch()} />
  }

  const { exam, created_at: createdAt } = examQuery.data
  const adjustedTypography = previewTypography(exam.formatting).adjusted

  return (
    <div className="mx-auto max-w-6xl space-y-7 pb-10">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Button variant="ghost" asChild className="-ml-3 gap-2 text-stone-500">
          <Link to="/provas"><ArrowLeft size={16} aria-hidden />Minhas provas</Link>
        </Button>
        <Button variant="outline" asChild className="gap-2">
          <Link to="/"><Plus size={16} aria-hidden />Criar outra prova</Link>
        </Button>
      </div>
      <header>
        <p className="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Sua avaliação</p>
        <h1 className="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{exam.subject.name} <span className="font-normal text-stone-400">/</span> {exam.grade.name}</h1>
        <p className="mt-3 text-sm leading-6 text-stone-500">
          Prova #{exam.id} · {exam.questions.length} questões · Criada em {formatTimestamp(createdAt)}
        </p>
      </header>
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-5">
        <nav aria-label="Visualização da prova" className="inline-flex rounded-lg border border-stone-200 bg-white p-1">
          <button
            type="button"
            aria-pressed={view === 'exam'}
            onClick={() => setAnswerExamId(null)}
            className={'flex items-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition-colors ' + (view === 'exam' ? 'bg-emerald-50 text-emerald-900' : 'text-stone-500 hover:bg-stone-50')}
          >
            <BookOpen size={16} aria-hidden />Prova
          </button>
          <button
            type="button"
            aria-pressed={view === 'answers'}
            onClick={() => setAnswerExamId(id)}
            className={'flex items-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition-colors ' + (view === 'answers' ? 'bg-emerald-50 text-emerald-900' : 'text-stone-500 hover:bg-stone-50')}
          >
            <KeyRound size={16} aria-hidden />Ver gabarito
          </button>
        </nav>
        <p className="max-w-sm text-xs leading-5 text-stone-500">Revise os enunciados e as respostas antes de usar a prova em sala.</p>
      </div>
      {view === 'exam' ? (
        <>
          {adjustedTypography && (
            <p role="status" className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
              Esta prova usa uma preferência antiga ou uma fonte não disponível na prévia. Ajustamos a apresentação para facilitar a leitura; os dados salvos permanecem iguais.
            </p>
          )}
          <ExamSheet exam={exam} />
        </>
      ) : answersQuery.isPending ? (
        <div role="status" className="flex min-h-60 items-center justify-center gap-3 text-sm text-stone-500">
          <LoaderCircle className="size-5 animate-spin text-emerald-700" aria-hidden />Carregando o gabarito…
        </div>
      ) : answersQuery.isError ? (
        <div role="alert" className="mx-auto max-w-[840px] rounded-xl border border-red-200 bg-white p-7">
          <p className="text-sm text-red-800">{getErrorMessage(answersQuery.error)}</p>
          <Button type="button" variant="outline" className="mt-4 gap-2" onClick={() => void answersQuery.refetch()}><RefreshCw size={15} aria-hidden />Tentar novamente</Button>
        </div>
      ) : (
        <AnswerSheet exam={exam} answers={answersQuery.data.answer_key} />
      )}
      <div className="mx-auto flex max-w-[840px] justify-end">
        <Button asChild variant="ghost" className="gap-2 text-stone-500"><Link to="/provas">Voltar para minhas provas<ArrowRight size={16} aria-hidden /></Link></Button>
      </div>
    </div>
  )
}

function ReadError({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="mx-auto my-10 max-w-xl rounded-2xl border border-stone-200 bg-white p-8 text-center">
      <CircleAlert className="mx-auto mb-4 size-8 text-stone-400" aria-hidden />
      <h1 className="text-xl font-semibold text-stone-900">Não conseguimos abrir esta prova</h1>
      <p role="alert" className="mt-3 text-sm leading-6 text-stone-500">{message}</p>
      <div className="mt-6 flex flex-wrap justify-center gap-3">
        <Button asChild variant="outline"><Link to="/provas">Voltar para minhas provas</Link></Button>
        {onRetry && <Button type="button" onClick={onRetry}>Tentar novamente</Button>}
      </div>
    </div>
  )
}
