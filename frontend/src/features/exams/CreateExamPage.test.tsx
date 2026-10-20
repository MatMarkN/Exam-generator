import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { fireEvent, render, screen } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { MemoryRouter } from "react-router-dom"
import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api/client"
import CreateExamPage from "./CreateExamPage"

vi.mock("@/lib/environment", () => ({
  environment: { isDemo: true, label: "Demonstração", apiBase: "/api" },
}))

vi.mock("@/lib/api/client", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api/client")>()
  return {
    ...actual,
    api: {
      ...actual.api,
      subjects: vi.fn(),
      grades: vi.fn(),
      teachers: vi.fn(),
      generate: vi.fn(),
    },
  }
})

beforeEach(() => {
  vi.mocked(api.subjects).mockResolvedValue([{ id: 5, code: "matematica", name: "Matemática" }])
  vi.mocked(api.grades).mockResolvedValue([{ id: 6, code: "ef_06", name: "6º ano", year_number: 6, education_stage: "ensino_fundamental" }])
  vi.mocked(api.teachers).mockResolvedValue({ items: [], next_before_id: null })
  vi.mocked(api.generate).mockReset()
})

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<MemoryRouter><QueryClientProvider client={client}><CreateExamPage /></QueryClientProvider></MemoryRouter>)
}

describe("criação explícita de uma prova", () => {
  it("não gera ao enviar o formulário por Enter e envia uma única vez no clique duplo", async () => {
    vi.mocked(api.generate).mockImplementation(() => new Promise(() => {}))
    const user = userEvent.setup()
    renderPage()
    await screen.findByLabelText("Matéria")
    await user.click(screen.getByRole("button", { name: /continuar/i }))
    await user.click(screen.getByRole("button", { name: /continuar/i }))

    fireEvent.submit(screen.getByRole("form", { name: "Criar prova" }))
    expect(api.generate).not.toHaveBeenCalled()

    await user.dblClick(screen.getByRole("button", { name: "Gerar prova" }))
    expect(api.generate).toHaveBeenCalledTimes(1)
    expect(api.generate).toHaveBeenCalledWith(expect.objectContaining({ topics: ["frações"], grade: 6 }))
    expect(screen.getByRole("button", { name: "Preparando…" })).toBeDisabled()
  })
})
