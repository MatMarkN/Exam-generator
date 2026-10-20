import { ImageIcon } from 'lucide-react'
import type { AnswerKey, Exam } from '@/lib/api/types'
import { formatCalendarDate, previewTypography, questionTypeLabels } from './read-format'

type Question = Exam['questions'][number]
type Answer = AnswerKey[number]

function BlankField({ value }: { value?: string | null }) {
  return value
    ? <span className="break-words">{value}</span>
    : <span className="inline-block min-w-20 flex-1 border-b border-stone-300 pb-0.5" aria-label="A preencher">&nbsp;</span>
}

function ExamHeader({ exam }: { exam: Exam }) {
  return (
    <header className="mb-9 border-b-2 border-stone-800 pb-7">
      <p className="mb-5 text-center text-[1.08em] font-bold">
        {exam.header?.school_name || 'Escola: __________________________________'}
      </p>
      <dl className="grid gap-x-7 gap-y-3 text-[0.88em] sm:grid-cols-2">
        <div className="flex gap-2 sm:col-span-2">
          <dt className="shrink-0 font-semibold">Professor(a):</dt>
          <dd className="flex flex-1"><BlankField value={exam.header?.teacher_name} /></dd>
        </div>
        <div className="flex gap-2 sm:col-span-2">
          <dt className="shrink-0 font-semibold">Aluno(a):</dt>
          <dd className="flex flex-1"><BlankField value={exam.header?.student_name} /></dd>
        </div>
        <div className="flex gap-2">
          <dt className="shrink-0 font-semibold">Turma:</dt>
          <dd className="flex flex-1"><BlankField value={exam.header?.class_name} /></dd>
        </div>
        <div className="flex gap-2">
          <dt className="shrink-0 font-semibold">Data:</dt>
          <dd className="flex flex-1"><BlankField value={formatCalendarDate(exam.header?.date)} /></dd>
        </div>
        <div><dt className="inline font-semibold">Matéria: </dt><dd className="inline">{exam.subject.name}</dd></div>
        <div><dt className="inline font-semibold">Ano: </dt><dd className="inline">{exam.grade.name}</dd></div>
      </dl>
    </header>
  )
}

function QuestionContent({ question }: { question: Question }) {
  return (
    <section className="break-inside-avoid border-b border-stone-100 pb-8 last:border-0 last:pb-0" aria-labelledby={'question-' + question.number}>
      <div className="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h3 id={'question-' + question.number} className="font-bold">Questão {String(question.number).padStart(2, '0')}</h3>
        <span className="text-[0.72em] text-stone-500">{questionTypeLabels[question.type]}</span>
      </div>
      <p className="whitespace-pre-wrap leading-[1.85]">{question.statement}</p>
      {question.image_description && (
        <figure className="my-5 rounded-lg border border-dashed border-stone-300 bg-stone-50 p-5 text-[0.85em]">
          <figcaption className="mb-2 flex items-center gap-2 font-semibold"><ImageIcon size={17} aria-hidden />Imagem planejada</figcaption>
          <p className="leading-relaxed text-stone-600">{question.image_description}</p>
        </figure>
      )}
      {question.type === 'multiple_choice' && (
        <ul className="mt-5 space-y-3">
          {question.alternatives.map((alternative) => (
            <li key={alternative.key} className="flex gap-3 leading-relaxed">
              <span className="flex size-[1.75em] shrink-0 items-center justify-center rounded-full border border-stone-300 text-[0.8em] font-semibold">{alternative.key}</span>
              <span className="whitespace-pre-wrap pt-[0.1em]">{alternative.text}</span>
            </li>
          ))}
        </ul>
      )}
      {question.type === 'true_false' && (
        <ol className="mt-5 space-y-4">
          {question.statements.map((statement) => (
            <li key={statement.number} className="flex gap-3 leading-relaxed">
              <span className="shrink-0 text-stone-400" aria-hidden>( &nbsp; )</span>
              <span className="whitespace-pre-wrap">{statement.text}</span>
            </li>
          ))}
        </ol>
      )}
      {question.type === 'open_ended' && (
        <div className="mt-6 space-y-7" aria-label="Espaço para resposta">
          {[1, 2, 3].map((line) => <div key={line} className="border-b border-stone-200" />)}
        </div>
      )}
      <p className="mt-5 text-[0.65em] tracking-wide text-stone-400">BNCC · {question.bncc_skills.join(' · ')}</p>
    </section>
  )
}

export function ExamSheet({ exam }: { exam: Exam }) {
  const typography = previewTypography(exam.formatting)
  return (
    <article
      className="mx-auto w-full max-w-[840px] rounded-sm border border-stone-200 bg-white px-6 py-9 text-stone-800 shadow-[0_4px_30px_-15px_rgba(28,43,40,0.18)] [overflow-wrap:anywhere] sm:px-12 sm:py-12 lg:px-16"
      style={typography.style}
      aria-label="Conteúdo da prova"
    >
      <ExamHeader exam={exam} />
      <div className="mb-9">
        <h2 className="text-[1.35em] font-bold leading-snug">{exam.title}</h2>
        <p className="mt-4 whitespace-pre-wrap text-[0.88em] leading-[1.8] text-stone-600">{exam.instructions}</p>
      </div>
      <div className="space-y-8">
        {exam.questions.map((question) => <QuestionContent key={question.number} question={question} />)}
      </div>
    </article>
  )
}

function AnswerContent({ answer, exam }: { answer: Answer; exam: Exam }) {
  const question = exam.questions.find((item) => item.number === answer.question_number)
  return (
    <section className="border-b border-stone-100 pb-7 last:border-0 last:pb-0" aria-labelledby={'answer-' + answer.question_number}>
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <h3 id={'answer-' + answer.question_number} className="text-base font-semibold">Questão {String(answer.question_number).padStart(2, '0')}</h3>
        <span className="text-xs text-stone-500">{questionTypeLabels[answer.type]}</span>
      </div>
      {answer.type === 'multiple_choice' && (
        <div className="flex items-start gap-4 rounded-xl bg-emerald-50 px-5 py-4">
          <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-700 text-base font-bold text-white">{answer.correct_alternative}</span>
          <div>
            <p className="text-sm font-semibold text-emerald-950">Alternativa correta</p>
            {question?.type === 'multiple_choice' && <p className="mt-1 text-sm leading-relaxed text-emerald-900">{question.alternatives.find((item) => item.key === answer.correct_alternative)?.text}</p>}
          </div>
        </div>
      )}
      {answer.type === 'open_ended' && (
        <>
          <h4 className="mb-2 text-sm font-semibold text-stone-700">Resposta esperada</h4>
          <p className="whitespace-pre-wrap text-sm leading-7 text-stone-600">{answer.expected_answer}</p>
          <h4 className="mb-2 mt-5 text-sm font-semibold text-stone-700">Critérios de correção</h4>
          <ul className="ml-5 list-disc space-y-2 text-sm leading-6 text-stone-600">
            {answer.grading_criteria.map((criterion, index) => <li key={index}>{criterion}</li>)}
          </ul>
        </>
      )}
      {answer.type === 'true_false' && (
        <ol className="space-y-3">
          {answer.answers.map((item) => (
            <li key={item.statement_number} className="flex items-start gap-3 rounded-lg border border-stone-100 px-4 py-3">
              <span className={'flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-bold ' + (item.correct ? 'bg-emerald-50 text-emerald-800' : 'bg-orange-50 text-orange-800')}>{item.correct ? 'V' : 'F'}</span>
              <div>
                <p className="text-sm font-medium">Afirmação {item.statement_number} · {item.correct ? 'Verdadeira' : 'Falsa'}</p>
                {question?.type === 'true_false' && <p className="mt-1 text-sm leading-6 text-stone-500">{question.statements.find((statement) => statement.number === item.statement_number)?.text}</p>}
              </div>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}

export function AnswerSheet({ exam, answers }: { exam: Exam; answers: AnswerKey }) {
  return (
    <article className="mx-auto w-full max-w-[840px] rounded-xl border border-stone-200 bg-white p-6 [overflow-wrap:anywhere] sm:p-10" aria-label="Gabarito da prova">
      <header className="mb-8 border-b border-stone-100 pb-6">
        <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Material do professor</p>
        <h2 className="text-2xl font-semibold tracking-tight text-stone-900">Gabarito</h2>
        <p className="mt-2 text-sm leading-relaxed text-stone-500">{exam.title}</p>
      </header>
      <div className="space-y-7">
        {answers.map((answer) => <AnswerContent key={answer.question_number} answer={answer} exam={exam} />)}
      </div>
    </article>
  )
}
