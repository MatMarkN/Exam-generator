import { describe, expect, it } from "vitest"
import { profilePayload, settingsPatch, settingsValues, validateProfile, validateSettings } from "./teacher-form"

const initial = { school_name: "Escola Exemplo", default_font_family: "Arial", default_font_size: 12 }

describe("perfil do professor", () => {
  it("normaliza campos e remove e-mail opcional em branco", () => {
    expect(profilePayload({ name: "  Ana Júlia  ", email: " " })).toEqual({ name: "Ana Júlia", email: null })
    expect(validateProfile({ name: "é".repeat(160), email: "" })).toEqual({})
    expect(validateProfile({ name: "é".repeat(161), email: "" })).toHaveProperty("name")
  })
  it("rejeita controles e e-mail inválido", () => {
    expect(validateProfile({ name: "Ana\nJúlia", email: "email inválido" })).toEqual({
      name: "O nome do professor deve ficar em uma única linha.",
      email: "Informe um e-mail válido ou deixe este campo em branco.",
    })
  })
})

describe("preferências do professor", () => {
  it("envia apenas mudanças e permite remover escola", () => {
    expect(settingsPatch({ ...settingsValues(initial), schoolName: " " }, initial)).toEqual({ school_name: null })
    expect(settingsPatch(settingsValues(initial), initial)).toEqual({})
    expect(settingsPatch({ ...settingsValues(initial), fontSize: "18" }, initial)).toEqual({ default_font_size: 18 })
  })
  it("preserva tamanho legado quando somente a escola muda", () => {
    const legacy = { ...initial, default_font_size: 40 }
    const values = { ...settingsValues(legacy), schoolName: "Outra escola" }
    expect(validateSettings(values, legacy)).toEqual({})
    expect(settingsPatch(values, legacy)).toEqual({ school_name: "Outra escola" })
    expect(validateSettings({ ...values, fontSize: "41" }, legacy)).toHaveProperty("fontSize")
  })
  it.each(["", "0", "7", "37", "12.5", "1e1"])("rejeita novo tamanho %j", (fontSize) => {
    expect(validateSettings({ ...settingsValues(initial), fontSize }, initial)).toHaveProperty("fontSize")
  })
})
