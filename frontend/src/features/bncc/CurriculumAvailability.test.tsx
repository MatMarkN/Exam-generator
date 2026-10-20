import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { render, screen } from "@testing-library/react"
import { MemoryRouter } from "react-router-dom"
import { beforeEach, expect, it, vi } from "vitest"
import { api } from "@/lib/api/client"
import type { BnccDocument, BnccRelease } from "@/lib/api/types"
import CurriculumAvailability from "./CurriculumAvailability"

vi.mock("@/lib/api/client", async (original) => ({ ...await original<typeof import("@/lib/api/client")>(), api: { bnccDocuments: vi.fn(), bnccReleases: vi.fn() } }))
const scope = { subject: { id: 5, code: "matematica", name: "Matemática" }, grade: { id: 6, year_number: 6, name: "6º ano", education_stage: "ensino_fundamental" }, status: "ready", released_at: "2026-10-20 12:00:00", review: { skills: { total: 1, approved: 1, pending: 0, rejected: 0 }, competencies: { total: 1, approved: 1, pending: 0, rejected: 0 } } } satisfies BnccRelease
beforeEach(() => { vi.clearAllMocks() })
function renderAvailability(grade = 6) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(<MemoryRouter><QueryClientProvider client={client}><CurriculumAvailability subject="matematica" grade={grade} /></QueryClientProvider></MemoryRouter>)
}
it("reconhece a liberação parcial mesmo com o documento ainda em rascunho", async () => {
  vi.mocked(api.bnccDocuments).mockResolvedValue({ items: [{ id: 1, status: "draft" } as BnccDocument] })
  vi.mocked(api.bnccReleases).mockResolvedValue({ document_id: 1, status: "draft", items: [scope] })
  renderAvailability()
  expect(await screen.findByText("A BNCC está liberada para esta matéria e ano escolar.")).toBeInTheDocument()
  expect(screen.queryByRole("link")).not.toBeInTheDocument()
})
it("orienta revisar o ano selecionado e ignora uma revisão arquivada", async () => {
  vi.mocked(api.bnccDocuments).mockResolvedValue({ items: [{ id: 2, status: "archived" } as BnccDocument, { id: 1, status: "draft" } as BnccDocument] })
  vi.mocked(api.bnccReleases).mockResolvedValue({ document_id: 1, status: "draft", items: [scope] })
  renderAvailability(7)
  expect(await screen.findByRole("link", { name: "Abrir revisão da BNCC" })).toHaveAttribute("href", "/bncc")
  expect(api.bnccReleases).not.toHaveBeenCalledWith(2)
  expect(screen.getByRole("status")).toHaveTextContent("ainda precisa de revisão e liberação")
})
