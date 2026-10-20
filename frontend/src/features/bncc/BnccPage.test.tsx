import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { render, screen, waitFor, within } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { MemoryRouter } from "react-router-dom"
import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api/client"
import type { BnccCompetency, BnccDocument, BnccRelease, BnccSkill, BnccSkillDetail } from "@/lib/api/types"
import BnccPage from "./BnccPage"

const authState = vi.hoisted(() => ({ user: { id: 1, name: "Administrador", email: "admin@example.test", role: "admin" as "admin" | "teacher" } }))
vi.mock("@/features/auth/AuthProvider", () => ({ useAuth: () => authState }))

vi.mock("@/lib/environment", () => ({ environment: { isDemo: false, label: "Ambiente local", apiBase: "/api" } }))
vi.mock("@/lib/api/client", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/lib/api/client")>(),
  api: { subjects: vi.fn(), grades: vi.fn(), bnccDocuments: vi.fn(), bnccReleases: vi.fn(), bnccSkills: vi.fn(), bnccSkill: vi.fn(), bnccCompetencies: vi.fn(), reviewBnccSkill: vi.fn(), reviewBnccCompetency: vi.fn(), publishBnccScope: vi.fn() },
}))

const subject = { id: 5, code: "matematica", name: "Matemática" }
const grade = { id: 6, year_number: 6, name: "6º ano", education_stage: "ensino_fundamental" }
const document: BnccDocument = { id: 1, title: "BNCC oficial", version_label: "2018", revision: 1, sha256: "a".repeat(64), page_count: 600, status: "draft", ready_at: null, structured_at: "2026-10-20 12:00:00", structure_profile: "bncc-2018", original_filename: "bncc.pdf", curriculum_status: "awaiting_review", created_at: "2026-10-20 12:00:00", extracted_at: "2026-10-20 12:00:00", extractor_version: "test", extraction_warnings: null, structure_report: null }
const skill: BnccSkill = { id: 7, code: "EF06MA07", description: "Compreender, comparar e ordenar frações.", review_status: "pending", reviewed_at: null, review_note: null, locked: false }
const skillDetail: BnccSkillDetail = { ...skill, document_id: 1, component_id: 5, subject, grades: [6], contexts: [{ id: 10, document_id: 1, skill_id: 7, section_id: 1, page_start: 303, page_end: 303, source_excerpt: "Texto de origem da habilidade para conferência.", context_metadata: null }] }
function release(approved = false, ready = false): BnccRelease {
  return { subject, grade, status: ready ? "ready" : "pending", released_at: ready ? "2026-10-20 12:00:00" : null, review: { skills: { pending: approved ? 0 : 1, approved: approved ? 1 : 0, rejected: 0, total: 1 }, competencies: { pending: 0, approved: 1, rejected: 0, total: 1 } } }
}
beforeEach(() => {
  authState.user.role = "admin"
  vi.clearAllMocks()
  vi.mocked(api.subjects).mockResolvedValue([subject, { id: 7, code: "historia", name: "História" }])
  vi.mocked(api.grades).mockResolvedValue([{ ...grade, code: "ef_06" }, { ...grade, id: 7, year_number: 7, code: "ef_07", name: "7º ano" }])
  vi.mocked(api.bnccDocuments).mockResolvedValue({ items: [document] })
  vi.mocked(api.bnccReleases).mockResolvedValue({ document_id: 1, status: "draft", items: [release(), { ...release(), subject: { id: 7, code: "historia", name: "História" }, grade: { ...grade, id: 7, year_number: 7, name: "7º ano" } }] })
  vi.mocked(api.bnccSkills).mockImplementation(async (_, query) => ({ document_id: 1, total: query.subject === "matematica" ? 1 : 0, offset: 0, limit: 20, skills: query.subject === "matematica" ? [skill] : [] }))
  vi.mocked(api.bnccSkill).mockResolvedValue(skillDetail)
  vi.mocked(api.bnccCompetencies).mockResolvedValue({ document_id: 1, competencies: [] })
  vi.mocked(api.reviewBnccSkill).mockResolvedValue({})
  vi.mocked(api.publishBnccScope).mockResolvedValue({ ...release(true, true), document_id: 1 })
})
function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<MemoryRouter><QueryClientProvider client={client}><BnccPage /></QueryClientProvider></MemoryRouter>)
}

describe("revisão explícita da BNCC", () => {
  it("permite ao professor consultar as fontes sem oferecer aprovação ou liberação", async () => {
    authState.user.role = "teacher"
    const user = userEvent.setup()
    renderPage()
    await user.click(await screen.findByRole("button", { name: /EF06MA07/ }))
    expect(await screen.findByText("Texto de origem da habilidade para conferência.")).toBeInTheDocument()
    expect(screen.queryByRole("button", { name: "Liberar matéria e ano" })).not.toBeInTheDocument()
    expect(screen.queryByRole("button", { name: "Aprovar item" })).not.toBeInTheDocument()
    expect(screen.queryByLabelText("Nota da revisão")).not.toBeInTheDocument()
    expect(api.reviewBnccSkill).not.toHaveBeenCalled()
    expect(api.publishBnccScope).not.toHaveBeenCalled()
  })
  it("mantém a liberação bloqueada com itens pendentes e não aprova nada ao carregar", async () => {
    renderPage()
    expect(await screen.findByRole("button", { name: "Liberar matéria e ano" })).toBeDisabled()
    expect(api.reviewBnccSkill).not.toHaveBeenCalled()
    expect(api.publishBnccScope).not.toHaveBeenCalled()
    expect(api.bnccSkill).not.toHaveBeenCalled()
  })

  it("carrega a fonte ao abrir o item e exige uma nota antes da aprovação", async () => {
    const user = userEvent.setup()
    renderPage()
    await user.click(await screen.findByRole("button", { name: /EF06MA07/ }))
    expect(await screen.findByText("Texto de origem da habilidade para conferência.")).toBeInTheDocument()
    await user.click(screen.getByRole("button", { name: "Aprovar item" }))
    expect(api.reviewBnccSkill).not.toHaveBeenCalled()
    expect(screen.getByRole("alert")).toHaveTextContent("Registre o que foi conferido")
    await user.type(screen.getByLabelText("Nota da revisão"), "Descrição e ano conferidos na página 303.")
    await user.click(screen.getByRole("button", { name: "Aprovar item" }))
    await waitFor(() => expect(api.reviewBnccSkill).toHaveBeenCalledExactlyOnceWith(1, "EF06MA07", { status: "approved", note: "Descrição e ano conferidos na página 303." }))
    expect(api.publishBnccScope).not.toHaveBeenCalled()
  })

  it("envia somente a matéria e ano selecionados ao liberar uma revisão concluída", async () => {
    vi.mocked(api.bnccReleases).mockResolvedValue({ document_id: 1, status: "draft", items: [release(true)] })
    const user = userEvent.setup()
    renderPage()
    await user.click(await screen.findByRole("button", { name: "Liberar matéria e ano" }))
    await waitFor(() => expect(api.publishBnccScope).toHaveBeenCalledExactlyOnceWith(1, { subject: "matematica", grade: 6 }))
  })

  it("troca os filtros de revisão ao escolher outra matéria e ano", async () => {
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole("button", { name: /EF06MA07/ })
    await user.selectOptions(screen.getByLabelText("Matéria"), "historia")
    await user.selectOptions(screen.getByLabelText("Ano escolar"), "7")
    await waitFor(() => expect(api.bnccSkills).toHaveBeenCalledWith(1, { subject: "historia", grade: 7, offset: 0, limit: 20 }))
    expect(api.bnccCompetencies).toHaveBeenCalledWith(1, { subject: "historia", grade: 7 })
    expect(within(screen.getByRole("region", { name: "Habilidades curriculares" })).queryByText("EF06MA07")).not.toBeInTheDocument()
  })

  it("preserva a revisão publicada e oferece ir para a geração", async () => {
    vi.mocked(api.bnccReleases).mockResolvedValue({ document_id: 1, status: "draft", items: [release(true, true)] })
    const user = userEvent.setup()
    renderPage()
    expect(await screen.findByRole("link", { name: "Criar prova" })).toHaveAttribute("href", "/")
    await user.click(await screen.findByRole("button", { name: /EF06MA07/ }))
    await screen.findByText("Texto de origem da habilidade para conferência.")
    expect(screen.queryByRole("button", { name: "Aprovar item" })).not.toBeInTheDocument()
    expect(api.reviewBnccSkill).not.toHaveBeenCalled()
  })

  it("mantém uma competência compartilhada como leitura mesmo com o ano selecionado pendente", async () => {
    const competency: BnccCompetency = { id: 2, scope: "general", area_id: null, component_id: null, number: 1, description: "Competência compartilhada entre anos escolares.", source_section_id: 1, page_start: 11, page_end: 11, source_excerpt: "Trecho de origem da competência geral.", review_status: "approved", reviewed_at: "2026-10-20 12:00:00", review_note: "Conferida no PDF.", document_id: 1, source_metadata: null, locked: true }
    vi.mocked(api.bnccCompetencies).mockResolvedValue({ document_id: 1, competencies: [competency] })
    const user = userEvent.setup()
    renderPage()
    await user.click(await screen.findByRole("button", { name: /Competência geral 1/ }))
    const region = screen.getByRole("region", { name: "Competências curriculares" })
    expect(within(region).getByText(/pode ser de outra matéria ou ano/)).toBeInTheDocument()
    expect(within(region).queryByLabelText("Nota da revisão")).not.toBeInTheDocument()
    expect(within(region).queryByRole("button", { name: "Aprovar item" })).not.toBeInTheDocument()
    expect(screen.getByRole("button", { name: "Liberar matéria e ano" })).toBeDisabled()
    expect(api.reviewBnccCompetency).not.toHaveBeenCalled()
  })
})
