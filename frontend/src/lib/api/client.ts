import { environment } from "@/lib/environment"
import type {
  Teacher, TeacherCreate, TeacherUpdate, TeacherSettings, TeacherSettingsUpdate,
  Subject, SchoolGrade, Page, ExamRequest, GenerationResult, GenerationSummary,
  GenerationDetail, ExamLookup, AnswerKeyLookup, GenerationStatus,
  BnccDocumentList, BnccReleaseList, BnccRelease, BnccSkillList,
  BnccSkillDetail, BnccCompetencyList, BnccReviewRequest,
} from "./types"

interface ErrorBody { code?: string; message?: string; generation_id?: number }

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly generationId?: number

  constructor(status: number, error: ErrorBody) {
    super(error.message || "Não foi possível concluir a operação.")
    this.name = "ApiError"
    this.status = status
    this.code = error.code || "unknown_error"
    this.generationId = error.generation_id
  }
}

/** Todas as chamadas passam por aqui. Uma chamada de escrita nunca é repetida automaticamente. */
export async function request<T>(path: string, options: { method?: "GET" | "POST" | "PATCH"; body?: unknown } = {}): Promise<T> {
  const controller = new AbortController()
  const timeout = window.setTimeout(() => controller.abort(), path === "/exams/generate" ? 100_000 : 20_000)
  try {
    const response = await fetch(environment.apiBase + path, {
      method: options.method || "GET",
      headers: { Accept: "application/json", ...(options.body !== undefined ? { "Content-Type": "application/json" } : {}) },
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      signal: controller.signal,
      cache: "no-store",
    })
    const raw = await response.text()
    let data: unknown
    try { data = JSON.parse(raw) } catch {
      throw new ApiError(response.status, { code: "invalid_response", message: "A API devolveu uma resposta inesperada. Confira se o backend está disponível." })
    }
    if (!response.ok) {
      const error = (data as { error?: ErrorBody })?.error
      throw new ApiError(response.status, error || {})
    }
    return data as T
  } catch (error) {
    if (error instanceof ApiError) throw error
    if (error instanceof Error && error.name === "AbortError") {
      throw new ApiError(0, { code: "request_timeout", message: path === "/exams/generate"
        ? "A resposta demorou mais que o esperado. Consulte Minhas provas antes de tentar gerar novamente."
        : "A resposta demorou mais que o esperado. Confira a conexão e tente novamente." })
    }
    throw new ApiError(0, { code: "network_error", message: path === "/exams/generate"
      ? "Não foi possível confirmar a resposta. Consulte Minhas provas antes de iniciar outra geração."
      : "Não foi possível acessar a API. Confira se o backend está em execução e tente novamente." })
  } finally { window.clearTimeout(timeout) }
}

export function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const message = ({
      curriculum_not_ready: "A BNCC ainda precisa de revisão e liberação para gerar provas neste ambiente.",
      scope_not_ready: "A BNCC ainda precisa de revisão e liberação para esta matéria e ano escolar.",
      review_incomplete: "Confira e aprove todas as habilidades e competências desta matéria e ano antes de liberar.",
      immutable_document: "Este conteúdo já faz parte de uma liberação. Reprocesse o PDF para criar uma nova revisão antes de alterá-lo.",
      document_not_ready: "A revisão curricular selecionada ainda não está liberada.",
      ai_configuration_missing: "A integração de IA ainda precisa ser configurada no servidor.",
      duplicate_email: "Este e-mail já está cadastrado para outro professor.",
      not_found: "Este registro não foi encontrado. Ele pode pertencer a outro ambiente.",
    } as Record<string, string>)[error.code] || error.message
    return error.generationId ? message + " Tentativa registrada: " + error.generationId + "." : message
  }
  return error instanceof Error ? error.message : "Não foi possível concluir a operação."
}

function queryString(values: Record<string, string | number | undefined>) {
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(values)) if (value !== undefined) query.set(key, String(value))
  return query.size ? "?" + query.toString() : ""
}

export const api = {
  subjects: async () => (await request<{ items: Subject[] }>("/subjects")).items,
  grades: async () => (await request<{ items: SchoolGrade[] }>("/school-grades")).items,
  teachers: (query: { limit?: number; before_id?: number } = {}) => request<Page<Teacher>>("/teachers" + queryString(query)),
  teacher: (id: number) => request<Teacher>("/teachers/" + id),
  createTeacher: (body: TeacherCreate) => request<Teacher>("/teachers", { method: "POST", body }),
  updateTeacher: (id: number, body: TeacherUpdate) => request<Teacher>("/teachers/" + id, { method: "PATCH", body }),
  updateSettings: (id: number, body: TeacherSettingsUpdate) => request<TeacherSettings>("/teachers/" + id + "/settings", { method: "PATCH", body }),
  generate: (body: ExamRequest) => request<GenerationResult>("/exams/generate", { method: "POST", body }),
  exam: (id: number) => request<ExamLookup>("/exams/" + id),
  answers: (id: number) => request<AnswerKeyLookup>("/exams/" + id + "/answer-key"),
  generations: (query: { limit?: number; before_id?: number; status?: GenerationStatus; teacher_id?: number } = {}) =>
    request<Page<GenerationSummary>>("/generations" + queryString(query)),
  generation: (id: number) => request<GenerationDetail>("/generations/" + id),
  bnccDocuments: () => request<BnccDocumentList>("/bncc/documents"),
  bnccReleases: (documentId: number) => request<BnccReleaseList>("/bncc/documents/" + documentId + "/releases"),
  publishBnccScope: (documentId: number, body: { subject: string; grade: number }) =>
    request<BnccRelease & { document_id: number }>("/bncc/documents/" + documentId + "/releases", { method: "POST", body }),
  bnccSkills: (documentId: number, query: { subject: string; grade: number; offset?: number; limit?: number }) =>
    request<BnccSkillList>("/bncc/documents/" + documentId + "/skills" + queryString(query)),
  bnccSkill: (documentId: number, code: string) => request<BnccSkillDetail>("/bncc/documents/" + documentId + "/skills/" + encodeURIComponent(code)),
  bnccCompetencies: (documentId: number, query: { subject: string; grade: number }) =>
    request<BnccCompetencyList>("/bncc/documents/" + documentId + "/competencies" + queryString(query)),
  reviewBnccSkill: (documentId: number, code: string, body: BnccReviewRequest) =>
    request("/bncc/documents/" + documentId + "/skills/" + encodeURIComponent(code) + "/review", { method: "PATCH", body }),
  reviewBnccCompetency: (documentId: number, id: number, body: BnccReviewRequest) =>
    request("/bncc/documents/" + documentId + "/competencies/" + id + "/review", { method: "PATCH", body }),
}
