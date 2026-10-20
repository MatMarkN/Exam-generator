import type { ReactNode } from "react"
import { Link } from "react-router-dom"
import { BookOpen, CheckSquare2, ListChecks, RefreshCw, Type } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import type { SchoolGrade, Subject, Teacher } from "@/lib/api/types"
import type { CreateExamErrors, CreateExamValues } from "./create-exam-form"

export type ChangeExamValue = <K extends keyof CreateExamValues>(field: K, value: CreateExamValues[K]) => void

interface FormStepProps {
  values: CreateExamValues
  errors: CreateExamErrors
  onChange: ChangeExamValue
  isDemo: boolean
}

const selectClass = "flex h-11 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none transition-colors focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/15 disabled:cursor-not-allowed disabled:opacity-60"

function FormField({ id, label, error, hint, children, optional }: {
  id: string; label: string; error?: string; hint?: string; children: ReactNode; optional?: boolean
}) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id} className="flex items-center gap-2 text-sm font-semibold">
        {label}{optional && <span className="font-normal text-muted-foreground">(opcional)</span>}
      </Label>
      {children}
      <p className={error ? "text-sm text-destructive" : "text-xs leading-relaxed text-muted-foreground"} id={id + "-help"} role={error ? "alert" : undefined} hidden={!error && !hint}>{error ?? hint}</p>
    </div>
  )
}

function attributes(id: string, error?: string) {
  return { id, "aria-invalid": !!error, "aria-describedby": id + "-help" }
}

export function ContentStep({ values, errors, onChange, isDemo, subjects, grades }: FormStepProps & {
  subjects: Subject[]; grades: SchoolGrade[]
}) {
  return (
    <div className="space-y-7">
      <div className="grid gap-5 sm:grid-cols-2">
        <FormField id="subject" label="Matéria" error={errors.subject} hint={isDemo ? "Matéria disponível neste exemplo." : "Escolha o componente curricular."}>
          <select {...attributes("subject", errors.subject)} className={selectClass} value={values.subject} disabled={isDemo} onChange={(event) => onChange("subject", event.target.value)}>
            <option value="">Selecione a matéria</option>
            {subjects.map((subject) => <option key={subject.id} value={subject.code}>{subject.name}</option>)}
          </select>
        </FormField>
        <FormField id="grade" label="Ano escolar" error={errors.grade} hint="Ensino Fundamental">
          <select {...attributes("grade", errors.grade)} className={selectClass} value={values.grade} disabled={isDemo} onChange={(event) => onChange("grade", event.target.value)}>
            <option value="">Selecione o ano</option>
            {grades.map((grade) => <option key={grade.id} value={grade.year_number}>{grade.name}</option>)}
          </select>
        </FormField>
      </div>
      <FormField id="topicText" label="Tópicos da prova" error={errors.topicText} hint={isDemo ? "A simulação trabalha com frações para o 6º ano." : "Separe os tópicos por vírgulas ou quebras de linha. Até 8 tópicos."}>
        <Textarea {...attributes("topicText", errors.topicText)} value={values.topicText} onChange={(event) => onChange("topicText", event.target.value)} readOnly={isDemo} placeholder="Ex.: frações equivalentes, comparação de frações" className="min-h-24 resize-y" />
      </FormField>
      <FormField id="teacherNotes" label="O que foi trabalhado em sala?" optional error={errors.teacherNotes} hint={isDemo ? "As observações ficam salvas; o exemplo usa questões já preparadas." : "Conte com suas palavras o que os alunos estudaram e o que você quer avaliar."}>
        <Textarea {...attributes("teacherNotes", errors.teacherNotes)} value={values.teacherNotes} onChange={(event) => onChange("teacherNotes", event.target.value)} placeholder="Ex.: Trabalhamos frações com situações de partilha. Quero avaliar a comparação e pedir que os alunos expliquem o raciocínio." className="min-h-36 resize-y leading-relaxed" />
      </FormField>
      <details className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-3">
        <summary className="cursor-pointer text-sm font-medium text-foreground">Adicionar um texto de referência</summary>
        <div className="pt-4">
          <FormField id="referenceText" label="Referência de estilo" optional error={errors.referenceText} hint="Cole um trecho para indicar linguagem e estilo. A BNCC continua sendo a base curricular.">
            <Textarea {...attributes("referenceText", errors.referenceText)} value={values.referenceText} onChange={(event) => onChange("referenceText", event.target.value)} placeholder="Cole aqui um trecho de uma avaliação de referência." className="min-h-28" />
          </FormField>
        </div>
      </details>
    </div>
  )
}

export function QuestionsStep({ values, errors, onChange, isDemo }: FormStepProps) {
  const total = Number(values.choiceCount) + Number(values.openCount) + Number(values.trueFalseCount)
  return (
    <div className="space-y-7">
      <div className="max-w-xs">
        <FormField id="questionCount" label="Total de questões" error={errors.questionCount} hint={isDemo ? "O exemplo tem uma questão de cada tipo." : "De 1 a 30 questões por prova."}>
          <Input {...attributes("questionCount", errors.questionCount)} type="number" inputMode="numeric" min={1} max={30} value={values.questionCount} readOnly={isDemo} onChange={(event) => onChange("questionCount", event.target.value)} />
        </FormField>
      </div>
      <fieldset className="space-y-3">
        <legend className="text-sm font-semibold">Como distribuir as questões?</legend>
        <div className="grid gap-3 sm:grid-cols-2">
          {[
            { value: "automatic" as const, title: "Deixar a IA escolher", description: "A distribuição acompanha os conteúdos e as habilidades." },
            { value: "manual" as const, title: "Escolher os tipos", description: "Você define a quantidade de cada tipo de questão." },
          ].map((mode) => (
            <label key={mode.value} className={"relative cursor-pointer rounded-xl border p-4 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary/30 " + (values.questionMode === mode.value ? "border-primary bg-primary/5" : "border-border bg-background") + (isDemo && mode.value === "automatic" ? " cursor-not-allowed opacity-50" : "")}>
              <input type="radio" name="questionMode" value={mode.value} checked={values.questionMode === mode.value} disabled={isDemo && mode.value === "automatic"} onChange={() => onChange("questionMode", mode.value)} className="absolute right-4 top-4 accent-primary" />
              <span className="block pr-5 text-sm font-semibold">{mode.title}</span>
              <span className="mt-1.5 block text-xs leading-relaxed text-muted-foreground">{mode.description}</span>
            </label>
          ))}
        </div>
        {errors.questionMode && <p className="text-sm text-destructive" role="alert">{errors.questionMode}</p>}
      </fieldset>
      {values.questionMode === "manual" && (
        <div className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-3">
            {[
              { field: "choiceCount" as const, title: "Múltipla escolha", Icon: ListChecks },
              { field: "openCount" as const, title: "Dissertativas", Icon: BookOpen },
              { field: "trueFalseCount" as const, title: "Verdadeiro ou falso", Icon: CheckSquare2 },
            ].map(({ field, title, Icon }) => (
              <div className="rounded-xl border border-border bg-muted/20 p-4" key={field}>
                <Icon className="mb-3 size-5 text-primary" aria-hidden="true" />
                <FormField id={field} label={title} error={errors[field]}>
                  <Input {...attributes(field, errors[field])} type="number" inputMode="numeric" min={0} max={30} value={values[field]} readOnly={isDemo} onChange={(event) => onChange(field, event.target.value)} />
                </FormField>
              </div>
            ))}
          </div>
          <p className={"text-xs " + (total === Number(values.questionCount) ? "text-muted-foreground" : "text-destructive")} aria-live="polite">
            {total} de {values.questionCount || "0"} questões distribuídas.
          </p>
        </div>
      )}
      <div className="grid gap-5 border-t border-border pt-6 sm:grid-cols-2">
        <FormField id="alternativesCount" label="Alternativas por questão" error={errors.alternativesCount} hint="Aplicado às questões de múltipla escolha.">
          <select {...attributes("alternativesCount", errors.alternativesCount)} value={values.alternativesCount} className={selectClass} onChange={(event) => onChange("alternativesCount", event.target.value)}>
            {[2, 3, 4, 5, 6].map((number) => <option key={number} value={number}>{number} alternativas</option>)}
          </select>
        </FormField>
        <FormField id="trueFalseStatements" label="Afirmações por questão" error={errors.trueFalseStatements} hint="Cada bloco de verdadeiro ou falso conta como uma questão.">
          <select {...attributes("trueFalseStatements", errors.trueFalseStatements)} value={values.trueFalseStatements} className={selectClass} onChange={(event) => onChange("trueFalseStatements", event.target.value)}>
            {Array.from({ length: 10 }, (_, index) => index + 1).map((number) => <option key={number} value={number}>{number} {number === 1 ? "afirmação" : "afirmações"}</option>)}
          </select>
        </FormField>
      </div>
      {!isDemo && (
        <FormField id="imageMode" label="Planejamento de imagens" error={errors.imageMode} hint="Esta versão pode planejar descrições de imagens; ainda não produz arquivos de imagem.">
          <select {...attributes("imageMode", errors.imageMode)} value={values.imageMode} className={selectClass} onChange={(event) => onChange("imageMode", event.target.value as CreateExamValues["imageMode"])}>
            <option value="none">Sem imagens</option>
            <option value="auto">Deixar a IA decidir onde uma imagem ajudaria</option>
            <option value="required">Pedir ao menos uma descrição de imagem</option>
          </select>
        </FormField>
      )}
    </div>
  )
}

export function HeaderStep({ values, errors, onChange, teachers, teacherLoading, teacherError, onRetryTeachers, onMoreTeachers, moreTeachersLoading }: FormStepProps & {
  teachers: Teacher[]
  teacherLoading: boolean
  teacherError?: string
  onRetryTeachers: () => void
  onMoreTeachers?: () => void
  moreTeachersLoading?: boolean
}) {
  const selectedTeacher = teachers.find((teacher) => String(teacher.id) === values.teacherId)
  const inheritHeader = !!selectedTeacher && values.useTeacherHeader
  return (
    <div className="space-y-7">
      <div className="space-y-3">
        <FormField id="teacherId" label="Professor" optional error={errors.teacherId} hint="Selecione um cadastro para usar o nome, a escola e a fonte preferida.">
          <select {...attributes("teacherId", errors.teacherId)} value={values.teacherId} className={selectClass} disabled={teacherLoading || !!teacherError} onChange={(event) => onChange("teacherId", event.target.value)}>
            <option value="">{teacherLoading ? "Carregando professores…" : "Continuar sem cadastro"}</option>
            {teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
          </select>
        </FormField>
        {teacherError && (
          <div role="alert" className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm">
            <p>{teacherError}</p>
            <Button type="button" variant="ghost" size="sm" onClick={onRetryTeachers}><RefreshCw className="size-3.5" />Tentar carregar professores</Button>
          </div>
        )}
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
          <Link to="/professores" className="font-semibold text-primary underline-offset-4 hover:underline">Cadastrar ou editar professor</Link>
          {onMoreTeachers && <Button type="button" variant="ghost" size="sm" disabled={moreTeachersLoading} onClick={onMoreTeachers}>{moreTeachersLoading ? "Carregando…" : "Carregar mais professores"}</Button>}
        </div>
      </div>
      <div className="space-y-5 border-t border-border pt-6">
        {selectedTeacher && (
          <label className="flex cursor-pointer items-start gap-3 rounded-lg bg-primary/5 p-3.5">
            <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={values.useTeacherHeader} onChange={(event) => {
              onChange("useTeacherHeader", event.target.checked)
              if (!event.target.checked) {
                onChange("teacherName", selectedTeacher.name)
                onChange("schoolName", selectedTeacher.settings.school_name ?? "")
              }
            }} />
            <span><span className="block text-sm font-medium">Usar nome e escola do cadastro</span><span className="mt-0.5 block text-xs text-muted-foreground">Desmarque para personalizar somente esta prova.</span></span>
          </label>
        )}
        <div className="grid gap-5 sm:grid-cols-2">
          <FormField id="schoolName" label="Escola" optional error={errors.schoolName} hint={inheritHeader ? "Herdada das preferências do professor." : "Deixe em branco para não preencher."}>
            <Input {...attributes("schoolName", errors.schoolName)} value={inheritHeader ? selectedTeacher.settings.school_name ?? "" : values.schoolName} disabled={inheritHeader} onChange={(event) => onChange("schoolName", event.target.value)} placeholder="Nome da escola" />
          </FormField>
          <FormField id="teacherName" label="Nome no cabeçalho" optional error={errors.teacherName} hint={inheritHeader ? "Herdado do cadastro selecionado." : "Professor responsável pela avaliação."}>
            <Input {...attributes("teacherName", errors.teacherName)} value={inheritHeader ? selectedTeacher.name : values.teacherName} disabled={inheritHeader} onChange={(event) => onChange("teacherName", event.target.value)} placeholder="Nome do professor" />
          </FormField>
          <FormField id="studentName" label="Aluno" optional error={errors.studentName} hint="Em branco, o aluno poderá preencher o nome.">
            <Input {...attributes("studentName", errors.studentName)} value={values.studentName} onChange={(event) => onChange("studentName", event.target.value)} placeholder="Deixar espaço para preencher" />
          </FormField>
          <FormField id="className" label="Turma" optional error={errors.className}>
            <Input {...attributes("className", errors.className)} value={values.className} onChange={(event) => onChange("className", event.target.value)} placeholder="Ex.: 6º A" />
          </FormField>
          <FormField id="date" label="Data da prova" optional error={errors.date}>
            <Input {...attributes("date", errors.date)} type="date" min="0001-01-01" max="9999-12-31" value={values.date} onChange={(event) => onChange("date", event.target.value)} />
          </FormField>
        </div>
      </div>
      <div className="space-y-4 rounded-xl border border-border bg-muted/20 p-4">
        <div className="flex items-center gap-2 text-sm font-semibold"><Type className="size-4 text-primary" aria-hidden="true" />Apresentação da prova</div>
        <label className="flex cursor-pointer items-start gap-3">
          <input type="checkbox" checked={values.useTeacherFormatting} className="mt-0.5 size-4 accent-primary" onChange={(event) => {
            onChange("useTeacherFormatting", event.target.checked)
            if (!event.target.checked) {
              const family = selectedTeacher?.settings.default_font_family
              const size = selectedTeacher?.settings.default_font_size
              onChange("fontFamily", family && ["Arial", "Calibri", "Times New Roman", "Verdana", "Georgia"].includes(family) ? family : "Arial")
              onChange("fontSize", String(size && size >= 8 && size <= 36 ? size : 12))
            }
          }} />
          <span><span className="block text-sm">{selectedTeacher ? "Usar a fonte preferida do professor" : "Usar a fonte padrão"}</span><span className="mt-1 block text-xs text-muted-foreground">{selectedTeacher ? selectedTeacher.settings.default_font_family + " · tamanho " + selectedTeacher.settings.default_font_size : "Arial · tamanho 12"}</span></span>
        </label>
        {!values.useTeacherFormatting && (
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField id="fontFamily" label="Fonte" error={errors.fontFamily}>
              <select {...attributes("fontFamily", errors.fontFamily)} value={values.fontFamily} className={selectClass} onChange={(event) => onChange("fontFamily", event.target.value)}>
                {["Arial", "Calibri", "Times New Roman", "Verdana", "Georgia"].map((font) => <option key={font}>{font}</option>)}
              </select>
            </FormField>
            <FormField id="fontSize" label="Tamanho" error={errors.fontSize}>
              <Input {...attributes("fontSize", errors.fontSize)} type="number" inputMode="numeric" min={8} max={36} value={values.fontSize} onChange={(event) => onChange("fontSize", event.target.value)} />
            </FormField>
          </div>
        )}
        <p className="text-xs leading-relaxed text-muted-foreground">As escolhas ficam salvas com esta prova. Alterar o cadastro depois preserva o conteúdo já gerado.</p>
      </div>
    </div>
  )
}
