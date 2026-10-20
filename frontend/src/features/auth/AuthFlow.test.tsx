import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { act, render, screen, waitFor } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { MemoryRouter, Route, Routes } from "react-router-dom"
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { api, ApiError } from "@/lib/api/client"
import type { AuthSession } from "@/lib/api/types"
import { AuthProvider, useAuth } from "./AuthProvider"
import AuthGate from "./AuthGate"
import AuthPage from "./AuthPage"
import { getCsrfToken, setCsrfToken } from "./session-state"

const session: AuthSession = { user: { id: 1, name: "Professora Marina", email: "marina@example.test", role: "teacher" }, csrf_token: "csrf-da-api" }
beforeEach(() => {
  setCsrfToken(null)
  vi.spyOn(api, "session").mockRejectedValue(new ApiError(401, { code: "auth_required" }))
  vi.spyOn(api, "login").mockResolvedValue(session)
  vi.spyOn(api, "register").mockResolvedValue(session)
  vi.spyOn(api, "logout").mockResolvedValue({ success: true })
})
afterEach(() => { vi.restoreAllMocks(); setCsrfToken(null) })

function PrivateArea() {
  const auth = useAuth()
  return <main><h1>Área privada de {auth.user?.name}</h1><button onClick={() => void auth.logout()}>Sair</button><button onClick={() => { void api.teachers().catch(() => {}) }}>Consultar dados</button></main>
}
function renderAuth(path = "/") {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<QueryClientProvider client={client}><AuthProvider><MemoryRouter initialEntries={[path]}><Routes>
    <Route path="entrar" element={<AuthPage mode="login" />} /><Route path="cadastro" element={<AuthPage mode="register" />} />
    <Route element={<AuthGate />}><Route index element={<PrivateArea />} /></Route>
  </Routes></MemoryRouter></AuthProvider></QueryClientProvider>)
  return client
}

describe("autenticação pela API", () => {
  it("consulta a API e redireciona uma visita anônima sem renderizar dados privados", async () => {
    renderAuth()
    expect(screen.getByRole("status")).toHaveTextContent("Conferindo sua sessão")
    expect(await screen.findByRole("button", { name: "Entrar no meu espaço" })).toBeEnabled()
    expect(api.session).toHaveBeenCalledOnce()
    expect(screen.queryByText(/Área privada/)).not.toBeInTheDocument()
  })

  it("restaura a sessão do servidor após recarregar e mantém somente o CSRF em memória", async () => {
    vi.mocked(api.session).mockResolvedValue(session)
    renderAuth()
    expect(await screen.findByRole("heading", { name: "Área privada de Professora Marina" })).toBeInTheDocument()
    expect(getCsrfToken()).toBe("csrf-da-api")
    expect(localStorage.length).toBe(0)
  })

  it("faz login pela API e elimina o cache antes de abrir os dados da conta", async () => {
    const user = userEvent.setup()
    const client = renderAuth("/entrar")
    await screen.findByRole("button", { name: "Entrar no meu espaço" })
    await waitFor(() => expect(screen.getByLabelText("E-mail")).toBeEnabled())
    client.setQueryData(["dados-de-outra-conta"], { prova: "privada" })
    await user.type(screen.getByLabelText("E-mail"), "marina@example.test")
    await user.type(screen.getByLabelText("Senha", { exact: true }), "uma senha longa de exemplo")
    await user.click(screen.getByRole("button", { name: "Entrar no meu espaço" }))
    expect(api.login).toHaveBeenCalledExactlyOnceWith({ email: "marina@example.test", password: "uma senha longa de exemplo" })
    await screen.findByRole("heading", { name: /Área privada/ })
    expect(client.getQueryData(["dados-de-outra-conta"])).toBeUndefined()
  })

  it("exige confirmação de senha e envia o cadastro sem papel administrativo", async () => {
    const user = userEvent.setup()
    renderAuth("/cadastro")
    await waitFor(() => expect(screen.getByLabelText("Seu nome")).toBeEnabled())
    await user.type(screen.getByLabelText("Seu nome"), "Professora Marina")
    await user.type(screen.getByLabelText("E-mail"), "marina@example.test")
    await user.type(screen.getByLabelText("Senha", { exact: true }), "uma frase longa segura")
    await user.type(screen.getByLabelText("Confirmar senha"), "uma frase diferente")
    await user.click(screen.getByRole("button", { name: "Criar minha conta" }))
    expect(screen.getByRole("alert")).toHaveTextContent("A confirmação precisa ser igual à senha")
    expect(api.register).not.toHaveBeenCalled()
    await user.clear(screen.getByLabelText("Confirmar senha"))
    await user.type(screen.getByLabelText("Confirmar senha"), "uma frase longa segura")
    await user.click(screen.getByRole("button", { name: "Criar minha conta" }))
    expect(api.register).toHaveBeenCalledExactlyOnceWith({ name: "Professora Marina", email: "marina@example.test", password: "uma frase longa segura", password_confirmation: "uma frase longa segura" })
    await screen.findByRole("heading", { name: /Área privada/ })
  })

  it("apresenta erro de credenciais sem entrar na área privada", async () => {
    vi.mocked(api.login).mockRejectedValue(new ApiError(401, { code: "invalid_credentials" }))
    const user = userEvent.setup()
    renderAuth("/entrar")
    await waitFor(() => expect(screen.getByLabelText("E-mail")).toBeEnabled())
    await user.type(screen.getByLabelText("E-mail"), "marina@example.test")
    await user.type(screen.getByLabelText("Senha", { exact: true }), "incorreta")
    await user.click(screen.getByRole("button", { name: "Entrar no meu espaço" }))
    expect(await screen.findByRole("alert")).toHaveTextContent("E-mail ou senha incorretos")
    expect(screen.queryByText(/Área privada/)).not.toBeInTheDocument()
  })

  it("uma falha ao conferir a sessão oferece recuperação em vez de um carregamento sem fim", async () => {
    vi.mocked(api.session).mockRejectedValue(new ApiError(0, { code: "network_error", message: "API indisponível." }))
    const user = userEvent.setup()
    renderAuth()
    expect(await screen.findByRole("alert")).toHaveTextContent("API indisponível")
    vi.mocked(api.session).mockResolvedValue(session)
    await user.click(screen.getByRole("button", { name: "Tentar novamente" }))
    await screen.findByRole("heading", { name: /Área privada/ })
  })

  it("remove dados e CSRF no logout e retorna ao login", async () => {
    vi.mocked(api.session).mockResolvedValue(session)
    const user = userEvent.setup()
    const client = renderAuth()
    await screen.findByRole("heading", { name: /Área privada/ })
    client.setQueryData(["provas"], ["conteúdo privado"])
    await user.click(screen.getByRole("button", { name: "Sair" }))
    await screen.findByRole("button", { name: "Entrar no meu espaço" })
    expect(api.logout).toHaveBeenCalledOnce()
    expect(client.getQueryData(["provas"])).toBeUndefined()
    expect(getCsrfToken()).toBeNull()
  })

  it("um 401 de qualquer recurso protegido limpa o cache e exige novo login", async () => {
    vi.mocked(api.session).mockResolvedValue(session)
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: { code: "auth_required" } }), { status: 401 })))
    const user = userEvent.setup()
    const client = renderAuth()
    await screen.findByRole("heading", { name: /Área privada/ })
    client.setQueryData(["provas"], ["conteúdo privado"])
    await user.click(screen.getByRole("button", { name: "Consultar dados" }))
    await screen.findByRole("button", { name: "Entrar no meu espaço" })
    expect(client.getQueryData(["provas"])).toBeUndefined()
    expect(getCsrfToken()).toBeNull()
  })

  it("uma troca de conta em outra aba revalida a API e remove o cache da conta anterior", async () => {
    let connection: { onmessage: ((event: MessageEvent<unknown>) => void) | null } | undefined
    class Channel {
      onmessage: ((event: MessageEvent<unknown>) => void) | null = null
      constructor() { connection = this }
      postMessage() {}
      close() {}
    }
    vi.stubGlobal("BroadcastChannel", Channel)
    vi.mocked(api.session).mockResolvedValue(session)
    const client = renderAuth()
    await screen.findByRole("heading", { name: /Área privada de Professora Marina/ })
    client.setQueryData(["provas"], ["conteúdo da conta anterior"])
    vi.mocked(api.session).mockResolvedValue({ user: { ...session.user, id: 2, name: "Professor Lucas" }, csrf_token: "csrf-lucas" })
    act(() => { connection?.onmessage?.(new MessageEvent("message", { data: "auth-changed" })) })
    expect(await screen.findByRole("heading", { name: "Área privada de Professor Lucas" })).toBeInTheDocument()
    expect(client.getQueryData(["provas"])).toBeUndefined()
    expect(getCsrfToken()).toBe("csrf-lucas")
  })
})
