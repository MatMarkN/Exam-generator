import { useState } from 'react'
import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { ArrowRight, Check, ChevronDown, ChevronUp, CircleAlert, Clock3, FileText, LoaderCircle, Plus, RefreshCw } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { api, getErrorMessage } from '@/lib/api/client'
import type { GenerationSummary } from '@/lib/api/types'
import { formatTimestamp } from '@/features/exams/read-format'

type Status = GenerationSummary['status']
type Filter = Status | 'all'

const filters: { value: Filter; label: string }[] = [
  { value: 'all', label: 'Todas' },
  { value: 'succeeded', label: 'Concluídas' },
  { value: 'failed', label: 'Não concluídas' },
  { value: 'running', label: 'Em andamento' },
]

const statusLabels: Record<Status, string> = {
  succeeded: 'Concluída', failed: 'Não concluída', running: 'Em andamento',
}

function StatusIndicator({ status }: { status: Status }) {
  const Icon = status === 'succeeded' ? Check : status === 'failed' ? CircleAlert : Clock3
  const color = status === 'succeeded' ? 'bg-emerald-50 text-emerald-800' : status === 'failed' ? 'bg-orange-50 text-orange-800' : 'bg-blue-50 text-blue-800'
  return (
    <span className={'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ' + color}>
      <Icon size={12} aria-hidden />{statusLabels[status]}
    </span>
  )
}

function FailureDetails({ id }: { id: number }) {
  const detail = useQuery({
    queryKey: ['generation', id],
    queryFn: () => api.generation(id),
  })
  return (
    <div className="border-t border-orange-100 bg-orange-50/40 px-5 py-4 text-sm">
      {detail.isPending ? (
        <p role="status" className="flex items-center gap-2 text-stone-500"><LoaderCircle size={15} className="animate-spin" aria-hidden />Consultando o motivo…</p>
      ) : detail.isError ? (
        <div role="alert">
          <p className="text-orange-900">{getErrorMessage(detail.error)}</p>
          <Button variant="ghost" size="sm" className="mt-2" onClick={() => void detail.refetch()}>Tentar novamente</Button>
        </div>
      ) : (
        <>
          <p className="font-medium text-stone-800">O que aconteceu</p>
          <p className="mt-1 leading-6 text-stone-600">{detail.data.error_message || 'Esta geração não pôde ser concluída. Revise as configurações antes de tentar novamente.'}</p>
          <p className="mt-2 text-xs text-stone-500">A tentativa ficou registrada. Iniciar outra prova cria uma nova geração.</p>
        </>
      )}
    </div>
  )
}

function HistoryItem({ item, subjectName }: { item: GenerationSummary; subjectName: string }) {
  const [expanded, setExpanded] = useState(false)
  return (
    <article className="overflow-hidden rounded-xl border border-stone-200 bg-white transition-shadow hover:shadow-sm">
      <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
        <div className="hidden size-11 shrink-0 items-center justify-center rounded-xl bg-stone-50 text-stone-500 sm:flex"><FileText size={21} strokeWidth={1.6} aria-hidden /></div>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2.5">
            <h2 className="font-semibold text-stone-800">{subjectName} <span className="font-normal text-stone-400">·</span> {item.grade}º ano</h2>
            <StatusIndicator status={item.status} />
          </div>
          <p className="mt-1.5 break-words text-sm leading-6 text-stone-500">{item.topics.join(' · ')}</p>
          <p className="mt-2 text-xs leading-5 text-stone-400">
            {item.question_count} {item.question_count === 1 ? 'questão' : 'questões'} · {formatTimestamp(item.started_at)}
            {item.requester_name ? ' · ' + item.requester_name : ''}
          </p>
        </div>
        {item.status === 'succeeded' && item.exam_id !== null ? (
          <Button asChild variant="outline" size="sm" className="shrink-0 gap-2 self-start sm:self-auto">
            <Link to={'/provas/' + item.exam_id}>Abrir prova<ArrowRight size={15} aria-hidden /></Link>
          </Button>
        ) : item.status === 'failed' ? (
          <Button variant="ghost" size="sm" className="shrink-0 gap-1.5 self-start text-stone-600 sm:self-auto" aria-expanded={expanded} onClick={() => setExpanded(!expanded)}>
            Ver motivo{expanded ? <ChevronUp size={15} aria-hidden /> : <ChevronDown size={15} aria-hidden />}
          </Button>
        ) : (
          <span className="max-w-40 text-xs leading-5 text-stone-400">Atualize a lista para conferir o resultado.</span>
        )}
      </div>
      {expanded && <FailureDetails id={item.id} />}
    </article>
  )
}

export default function HistoryPage() {
  const [status, setStatus] = useState<Filter>('all')
  const subjects = useQuery({ queryKey: ['subjects'], queryFn: api.subjects })
  const history = useInfiniteQuery({
    queryKey: ['generations', status],
    queryFn: ({ pageParam }) => api.generations({
      limit: 12,
      ...(pageParam ? { before_id: pageParam } : {}),
      ...(status !== 'all' ? { status } : {}),
    }),
    initialPageParam: undefined as number | undefined,
    getNextPageParam: (page) => page.next_before_id ?? undefined,
  })
  const items = history.data?.pages.flatMap((page) => page.items) ?? []
  const subjectNames = new Map(subjects.data?.map((subject) => [subject.code, subject.name]) ?? [])

  return (
    <div className="mx-auto max-w-6xl space-y-8 pb-8">
      <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
          <p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Seu acervo</p>
          <h1 className="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">Minhas provas</h1>
          <p className="mt-3 max-w-xl text-sm leading-6 text-stone-500">Encontre suas avaliações, consulte os gabaritos e acompanhe cada geração.</p>
        </div>
        <Button asChild className="gap-2 self-start"><Link to="/"><Plus size={17} aria-hidden />Criar prova</Link></Button>
      </header>
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-5">
        <nav aria-label="Filtrar provas por situação" className="flex flex-wrap gap-1.5">
          {filters.map((filter) => (
            <button
              type="button"
              key={filter.value}
              aria-pressed={status === filter.value}
              onClick={() => setStatus(filter.value)}
              className={'rounded-lg px-3.5 py-2 text-sm font-medium transition-colors ' + (status === filter.value ? 'bg-emerald-100/70 text-emerald-900' : 'text-stone-500 hover:bg-stone-100')}
            >{filter.label}</button>
          ))}
        </nav>
        <Button type="button" size="sm" variant="ghost" className="gap-2 text-stone-500" disabled={history.isFetching} onClick={() => void history.refetch()}>
          <RefreshCw size={14} className={history.isFetching ? 'animate-spin' : ''} aria-hidden />Atualizar
        </Button>
      </div>
      {history.isPending ? (
        <div role="status" className="space-y-3">
          <span className="sr-only">Carregando suas provas…</span>
          {[1, 2, 3].map((item) => <div key={item} className="h-32 animate-pulse rounded-xl border border-stone-100 bg-white" aria-hidden />)}
        </div>
      ) : history.isError && items.length === 0 ? (
        <div className="rounded-2xl border border-stone-200 bg-white px-6 py-12 text-center">
          <CircleAlert className="mx-auto size-8 text-stone-400" aria-hidden />
          <h2 className="mt-4 text-lg font-semibold text-stone-800">Não conseguimos carregar suas provas</h2>
          <p role="alert" className="mx-auto mt-2 max-w-lg text-sm leading-6 text-stone-500">{getErrorMessage(history.error)}</p>
          <Button variant="outline" className="mt-5" onClick={() => void history.refetch()}>Tentar novamente</Button>
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-white/65 px-6 py-16 text-center">
          <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><FileText size={29} strokeWidth={1.5} aria-hidden /></div>
          <h2 className="mt-6 text-xl font-semibold tracking-tight text-stone-800">{status === 'all' ? 'Sua próxima prova começa aqui' : 'Nenhuma prova nesta situação'}</h2>
          <p className="mx-auto mt-3 max-w-sm text-sm leading-6 text-stone-500">{status === 'all' ? 'Depois de gerar uma avaliação, ela ficará guardada neste espaço para você consultar quando precisar.' : 'Você pode escolher outro filtro ou criar uma nova avaliação.'}</p>
          {status === 'all' && <Button asChild className="mt-6 gap-2"><Link to="/"><Plus size={16} aria-hidden />Criar minha primeira prova</Link></Button>}
        </div>
      ) : (
        <>
          <div className="space-y-3">{items.map((item) => <HistoryItem key={item.id} item={item} subjectName={subjectNames.get(item.subject) ?? item.subject.replaceAll('_', ' ')} />)}</div>
          {history.isFetchNextPageError && <p role="alert" className="text-center text-sm text-red-700">{getErrorMessage(history.error)}</p>}
          {history.hasNextPage && (
            <div className="flex justify-center pt-1">
              <Button type="button" variant="outline" className="gap-2" disabled={history.isFetching} onClick={() => void history.fetchNextPage()}>
                {history.isFetchingNextPage ? <LoaderCircle className="size-4 animate-spin" aria-hidden /> : <ChevronDown size={16} aria-hidden />}
                {history.isFetchingNextPage ? 'Carregando…' : 'Carregar mais provas'}
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
