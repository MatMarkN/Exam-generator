import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { render, screen } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { Link, MemoryRouter, Route, Routes } from "react-router-dom"
import { beforeEach, describe, expect, it, vi } from "vitest"
import { ApiError, api } from "@/lib/api/client"
import type { AnswerKeyLookup, ExamLookup } from "@/lib/api/types"
import ExamPage from "./ExamPage"

vi.mock("@/lib/api/client", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api/client")>()
  return {
    ...actual,
    api: { ...actual.api, exam: vi.fn(), answers: vi.fn() },
  }
})

function examFixture(id: number): ExamLookup {
  return {
    generation_id: id + 100,
    created_at: "2026-10-20T12:00:00Z",
    exam: {
      id,
      title: "Prova de frações " + id,
      instructions: "Explique seu raciocínio.",
      subject: { id: 5, code: "matematica", name: "Matemática" },
      grade: { id: 6, year_number: 6, name: "6º ano", education_stage: "ensino_fundamental" },
      topics: ["frações"],
      questions: [{
        number: 1,
        type: "open_ended",
        statement: "Pergunta da prova " + id,
        bncc_skills: ["EF06MA07"],
        image_description: null,
      }],
    },
  }
}

function answerFixture(id: number): AnswerKeyLookup {
  return {
    exam_id: id,
    answer_key: [{
      question_number: 1,
      type: "open_ended",
      expected_answer: "RESPOSTA EXCLUSIVA DO GABARITO " + id,
      grading_criteria: ["Justificativa coerente com o enunciado."],
    }],
  }
}

beforeEach(() => {
  vi.mocked(api.exam).mockReset().mockImplementation(async (id) => examFixture(id))
  vi.mocked(api.answers).mockReset().mockImplementation(async (id) => answerFixture(id))
})

function renderReader() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, staleTime: Infinity, gcTime: Infinity } },
  })
  render(
    <MemoryRouter initialEntries={["/provas/1"]}>
      <QueryClientProvider client={client}>
        <Link to="/provas/2">Abrir outra prova de teste</Link>
        <Routes><Route path="/provas/:id" element={<ExamPage />} /></Routes>
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

describe("leitura da prova e acesso separado ao gabarito", () => {
  it("busca respostas somente por ação explícita e não revela o gabarito ao trocar de prova", async () => {
    const user = userEvent.setup()
    renderReader()
    await screen.findByText("Pergunta da prova 1")

    expect(api.answers).not.toHaveBeenCalled()
    expect(screen.getByRole("article", { name: "Conteúdo da prova" })).toBeInTheDocument()
    expect(screen.queryByText("RESPOSTA EXCLUSIVA DO GABARITO 1")).not.toBeInTheDocument()

    await user.click(screen.getByRole("button", { name: "Ver gabarito" }))
    await screen.findByText("RESPOSTA EXCLUSIVA DO GABARITO 1")
    expect(api.answers).toHaveBeenCalledTimes(1)
    expect(api.answers).toHaveBeenLastCalledWith(1)
    expect(screen.queryByRole("article", { name: "Conteúdo da prova" })).not.toBeInTheDocument()

    await user.click(screen.getByRole("button", { name: "Prova" }))
    expect(screen.getByText("Pergunta da prova 1")).toBeInTheDocument()
    expect(screen.queryByText("RESPOSTA EXCLUSIVA DO GABARITO 1")).not.toBeInTheDocument()
    expect(api.answers).toHaveBeenCalledTimes(1)

    // Abre novamente a resposta em cache e muda o ID sem desmontar o leitor.
    await user.click(screen.getByRole("button", { name: "Ver gabarito" }))
    expect(screen.getByText("RESPOSTA EXCLUSIVA DO GABARITO 1")).toBeInTheDocument()
    await user.click(screen.getByRole("link", { name: "Abrir outra prova de teste" }))
    await screen.findByText("Pergunta da prova 2")

    expect(screen.getByRole("button", { name: "Prova" })).toHaveAttribute("aria-pressed", "true")
    expect(screen.getByRole("button", { name: "Ver gabarito" })).toHaveAttribute("aria-pressed", "false")
    expect(screen.queryByRole("article", { name: "Gabarito da prova" })).not.toBeInTheDocument()
    expect(screen.queryByText("RESPOSTA EXCLUSIVA DO GABARITO 1")).not.toBeInTheDocument()
    expect(screen.queryByText("RESPOSTA EXCLUSIVA DO GABARITO 2")).not.toBeInTheDocument()
    expect(api.answers).toHaveBeenCalledTimes(1)
    expect(api.answers).not.toHaveBeenCalledWith(2)

    await user.click(screen.getByRole("button", { name: "Ver gabarito" }))
    await screen.findByText("RESPOSTA EXCLUSIVA DO GABARITO 2")
    expect(api.answers).toHaveBeenCalledTimes(2)
    expect(api.answers).toHaveBeenLastCalledWith(2)
  })

  it("apresenta erro 404 com opção de tentar novamente, sem consultar respostas", async () => {
    vi.mocked(api.exam).mockRejectedValueOnce(new ApiError(404, { code: "not_found", message: "Prova não encontrada." }))
    const user = userEvent.setup()
    renderReader()

    await screen.findByRole("heading", { name: "Não conseguimos abrir esta prova" })
    expect(screen.getByRole("alert")).toHaveTextContent("Este registro não foi encontrado.")
    expect(api.exam).toHaveBeenCalledTimes(1)
    expect(api.answers).not.toHaveBeenCalled()

    await user.click(screen.getByRole("button", { name: "Tentar novamente" }))
    await screen.findByText("Pergunta da prova 1")
    expect(api.exam).toHaveBeenCalledTimes(2)
    expect(api.answers).not.toHaveBeenCalled()
  })
})
