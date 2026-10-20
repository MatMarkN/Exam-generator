import { describe, expect, it, vi } from "vitest"
import { api, ApiError, getErrorMessage } from "./client"

describe("cliente HTTP", () => {
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
