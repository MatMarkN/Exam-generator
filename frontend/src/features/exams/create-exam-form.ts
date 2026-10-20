import type { ExamRequest } from "@/lib/api/types"

export type QuestionMode = "manual" | "automatic"
export type ImageMode = "none" | "required" | "auto"

export interface CreateExamValues {
  subject: string
  grade: string
  topicText: string
  teacherNotes: string
  referenceText: string
  questionCount: string
  questionMode: QuestionMode
  choiceCount: string
  openCount: string
  trueFalseCount: string
  alternativesCount: string
  trueFalseStatements: string
  imageMode: ImageMode
  teacherId: string
  useTeacherHeader: boolean
  schoolName: string
  teacherName: string
  studentName: string
  className: string
  date: string
  useTeacherFormatting: boolean
  fontFamily: string
  fontSize: string
}

export type CreateExamErrors = Partial<Record<keyof CreateExamValues, string>>

export const stepFields: (keyof CreateExamValues)[][] = [
  ["subject", "grade", "topicText", "teacherNotes", "referenceText"],
  ["questionCount", "questionMode", "choiceCount", "openCount", "trueFalseCount", "alternativesCount", "trueFalseStatements", "imageMode"],
  ["teacherId", "schoolName", "teacherName", "studentName", "className", "date", "fontFamily", "fontSize"],
]

export function initialCreateExamValues(isDemo: boolean): CreateExamValues {
  return {
    subject: isDemo ? "matematica" : "",
    grade: isDemo ? "6" : "",
    topicText: isDemo ? "Frações" : "",
    teacherNotes: "",
    referenceText: "",
    questionCount: isDemo ? "3" : "10",
    questionMode: isDemo ? "manual" : "automatic",
    choiceCount: "1",
    openCount: "1",
    trueFalseCount: "1",
    alternativesCount: "4",
    trueFalseStatements: "1",
    imageMode: "none",
    teacherId: "",
    useTeacherHeader: true,
    schoolName: "",
    teacherName: "",
    studentName: "",
    className: "",
    date: "",
    useTeacherFormatting: true,
    fontFamily: "Arial",
    fontSize: "12",
  }
}

export function parseTopics(text: string): string[] {
  const seen = new Set<string>()
  return text.split(/[\n,;]+/u).map((topic) => topic.trim()).filter((topic) => {
    if (!topic) return false
    const key = normalizeTopic(topic)
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

function normalizeTopic(text: string): string {
  return text.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase().replace(/\s+/gu, " ").trim()
}

function integerWithin(value: string, min: number, max: number): boolean {
  return /^\d+$/u.test(value) && Number(value) >= min && Number(value) <= max
}

function singleLineError(value: string, limit: number, allowBlank = true): string | undefined {
  if (/[\p{Cc}\p{Zl}\p{Zp}]/u.test(value)) return "Use somente uma linha, sem caracteres de controle."
  if (!allowBlank && !value.trim()) return "Preencha este campo."
  if ([...value.trim()].length > limit) return "Use até " + limit + " caracteres."
  return undefined
}

function validDate(date: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/u.test(date)) return false
  const [year, month, day] = date.split("-").map(Number)
  if (year < 1 || month < 1 || month > 12 || day < 1) return false
  const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)
  const days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  return day <= days[month - 1]
}

export function validateCreateExam(values: CreateExamValues, isDemo: boolean): CreateExamErrors {
  const errors: CreateExamErrors = {}
  if (!/^[a-z][a-z0-9_]{1,39}$/u.test(values.subject)) errors.subject = "Selecione uma matéria."
  if (!integerWithin(values.grade, 1, 9)) errors.grade = "Selecione um ano do Ensino Fundamental."
  const topics = parseTopics(values.topicText)
  if (topics.length < 1 || topics.length > 8) errors.topicText = "Informe de 1 a 8 tópicos, separados por linha ou vírgula."
  else if (topics.some((topic) => [...topic].length > 120 || /[\p{Cc}\p{Zl}\p{Zp}]/u.test(topic))) errors.topicText = "Cada tópico deve ter até 120 caracteres, em uma única linha."
  const encoder = new TextEncoder()
  for (const [field, limit] of [["teacherNotes", 8192], ["referenceText", 16384]] as const) {
    if (encoder.encode(values[field]).length > limit) errors[field] = "Este texto ultrapassou o limite permitido. Reduza seu tamanho."
    else if (/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u.test(values[field])) errors[field] = "Remova caracteres de controle deste texto."
  }
  if (!integerWithin(values.questionCount, 1, 30)) errors.questionCount = "Escolha de 1 a 30 questões."
  if (values.questionMode === "manual") {
    for (const field of ["choiceCount", "openCount", "trueFalseCount"] as const) {
      if (!integerWithin(values[field], 0, 30)) errors[field] = "Use um número inteiro entre 0 e 30."
    }
    if (Number(values.choiceCount) + Number(values.openCount) + Number(values.trueFalseCount) !== Number(values.questionCount)) {
      errors.questionCount = "A soma dos três tipos deve ser igual ao total de questões."
    }
  }
  if (!integerWithin(values.alternativesCount, 2, 6)) errors.alternativesCount = "Escolha de 2 a 6 alternativas."
  if (!integerWithin(values.trueFalseStatements, 1, 10)) errors.trueFalseStatements = "Escolha de 1 a 10 afirmações por questão."
  if (values.teacherId && !integerWithin(values.teacherId, 1, 4294967295)) errors.teacherId = "Selecione um professor válido."
  const headerFields = [["studentName", 160], ["className", 80]] as const
  for (const [field, limit] of headerFields) {
    const error = singleLineError(values[field], limit)
    if (error) errors[field] = error
  }
  if (!values.useTeacherHeader || !values.teacherId) {
    for (const [field, limit] of [["schoolName", 180], ["teacherName", 160]] as const) {
      const error = singleLineError(values[field], limit)
      if (error) errors[field] = error
    }
  }
  if (values.date && !validDate(values.date)) errors.date = "Informe uma data válida."
  if (!values.useTeacherFormatting) {
    const fontError = singleLineError(values.fontFamily, 80, false)
    if (fontError) errors.fontFamily = fontError
    if (!integerWithin(values.fontSize, 8, 36)) errors.fontSize = "Escolha um tamanho de 8 a 36."
  }
  if (isDemo) {
    if (values.subject !== "matematica") errors.subject = "A simulação usa Matemática."
    if (values.grade !== "6") errors.grade = "A simulação usa o 6º ano."
    if (topics.length !== 1 || normalizeTopic(topics[0] ?? "") !== "fracoes") errors.topicText = "Use o tópico Frações na simulação."
    if (values.questionCount !== "3") errors.questionCount = "A simulação contém 3 questões."
    if (values.questionMode !== "manual") errors.questionMode = "A simulação usa uma questão de cada tipo."
    if (values.choiceCount !== "1") errors.choiceCount = "A simulação usa uma questão deste tipo."
    if (values.openCount !== "1") errors.openCount = "A simulação usa uma questão deste tipo."
    if (values.trueFalseCount !== "1") errors.trueFalseCount = "A simulação usa uma questão deste tipo."
    if (values.imageMode !== "none") errors.imageMode = "A simulação não usa imagens."
  }
  return errors
}

/** Omitir os padrões herdados evita sobrescrever o cadastro do professor. */
export function buildCreateExamRequest(values: CreateExamValues, isDemo: boolean): ExamRequest {
  if (Object.keys(validateCreateExam(values, isDemo)).length) throw new Error("Confira os campos da prova.")
  const header: NonNullable<ExamRequest["exam_header"]> = {
    student_name: values.studentName.trim() || null,
    class_name: values.className.trim() || null,
    date: values.date || null,
  }
  if (!values.useTeacherHeader || !values.teacherId) {
    header.school_name = values.schoolName.trim() || null
    header.teacher_name = values.teacherName.trim() || null
  }
  const base = {
    subject: values.subject,
    grade: Number(values.grade),
    topics: isDemo ? ["frações"] : parseTopics(values.topicText),
    question_count: Number(values.questionCount),
    alternatives_count: Number(values.alternativesCount),
    true_false_statements: Number(values.trueFalseStatements),
    image_mode: values.imageMode,
    teacher_notes: values.teacherNotes.trim(),
    reference_text: values.referenceText.trim(),
    exam_header: header,
    ...(values.teacherId ? { teacher_id: Number(values.teacherId) } : {}),
    ...(!values.useTeacherFormatting ? { formatting: { font_family: values.fontFamily.trim(), font_size: Number(values.fontSize) } } : {}),
  }
  if (values.questionMode === "manual") {
    return {
      ...base,
      question_mode: "manual",
      question_distribution: {
        multiple_choice: Number(values.choiceCount),
        open_ended: Number(values.openCount),
        true_false: Number(values.trueFalseCount),
      },
    }
  }
  return { ...base, question_mode: "automatic" }
}
