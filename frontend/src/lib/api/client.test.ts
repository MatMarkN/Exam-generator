import { beforeEach, describe, expect, it, vi } from "vitest"
import { api, ApiError, getErrorMessage } from "./client"
import { getCsrfToken, onSessionExpired, setCsrfToken } from "@/features/auth/session-state"

beforeEach(() => setCsrfToken(null))

describe("cliente HTTP", () => {
  it("envia o cookie e o CSRF em escritas autenticadas, mantendo login sem o token", async () => {
    setCsrfToken("csrf-em-memoria")
    const fetch = vi.fn().mockImplementation(async () => new Response(JSON.stringify({ success: true })))
    vi.stubGlobal("fetch", fetch)
    await api.logout()
    expect(fetch.mock.calls[0][1]).toMatchObject({ credentials: "same-origin", headers: { "X-CSRF-Token": "csrf-em-memoria" } })
    await api.login({ email: "professor@example.test", password: "senha longa de exemplo" })
    expect(fetch.mock.calls[1][1].headers).not.toHaveProperty("X-CSRF-Token")
    expect(localStorage.length).toBe(0)
  })

  it("encerra a sessão local quando um recurso protegido responde 401", async () => {
    setCsrfToken("csrf-antigo")
    const listener = vi.fn()
    const unsubscribe = onSessionExpired(listener)
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: { code: "auth_required" } }), { status: 401 })))
    try {
      await expect(api.teachers()).rejects.toMatchObject({ status: 401 })
      expect(getCsrfToken()).toBeNull()
      expect(listener).toHaveBeenCalledOnce()
    } finally { unsubscribe() }
  })

  it("preserva uma sessão nova se uma requisição anterior responde 401 depois da troca de conta", async () => {
    setCsrfToken("csrf-antigo")
    let resolve!: (value: Response) => void
    vi.stubGlobal("fetch", vi.fn().mockReturnValue(new Promise<Response>((done) => { resolve = done })))
    const pending = api.teachers()
    setCsrfToken("csrf-novo")
    resolve(new Response(JSON.stringify({ error: { code: "auth_required" } }), { status: 401 }))
    await expect(pending).rejects.toMatchObject({ status: 401 })
    expect(getCsrfToken()).toBe("csrf-novo")
  })

  it("comunica a espera da cota sem repetir o pedido de geração", async () => {
    const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: { code: "generation_rate_limited", message: "Limite de gerações atingido." } }), { status: 429, headers: { "Retry-After": "10" } }))
    vi.stubGlobal("fetch", fetch)
    try { await api.generate({ subject: "matematica", grade: 6, topics: ["frações"], question_count: 3, question_mode: "automatic" }) }
    catch (error) { expect(getErrorMessage(error)).toContain("Aguarde 10 segundos") }
    expect(fetch).toHaveBeenCalledOnce()
    expect(getErrorMessage(new ApiError(429, { message: "Cota diária atingida.", retry_after_seconds: 7200 }))).toContain("Aguarde 2 horas")
  })
  it("mantém uma falha de geração sem repetir o POST e preserva seu identificador", async () => {
    const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      error: { code: "ai_timeout", message: "Tempo esgotado.", generation_id: 42 },
    }), { status: 504 }))
    vi.stubGlobal("fetch", fetch)
    await expect(api.generate({ subject: "matematica", grade: 6, topics: ["frações"], question_count: 3, question_mode: "automatic" })).rejects.toMatchObject({
      status: 504, code: "ai_timeout", generationId: 42,
    })
    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch.mock.calls[0][0]).toBe("/api/exams/generate")
    expect(fetch.mock.calls[0][1].method).toBe("POST")
    expect(getErrorMessage(new ApiError(504, { message: "Tempo esgotado.", generation_id: 42 }))).toContain("42")
  })

  it("comunica indisponibilidade sem expor um corpo HTML do proxy", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("<html>upstream internal details</html>", { status: 502 })))
    await expect(api.subjects()).rejects.toMatchObject({ code: "invalid_response" })
    try { await api.subjects() } catch (error) { expect(getErrorMessage(error)).not.toContain("internal details") }
  })

  it("retorna catálogos e codifica os filtros da paginação", async () => {
    const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ items: [], next_before_id: null }), { status: 200 }))
    vi.stubGlobal("fetch", fetch)
    expect(await api.generations({ limit: 12, before_id: 100, status: "failed" })).toEqual({ items: [], next_before_id: null })
    expect(fetch.mock.calls[0][0]).toBe("/api/generations?limit=12&before_id=100&status=failed")
  })
})
