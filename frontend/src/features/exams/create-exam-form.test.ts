import { describe, expect, it } from "vitest"
import { buildCreateExamRequest, initialCreateExamValues, parseTopics, validateCreateExam } from "./create-exam-form"

describe("configuração da prova", () => {
  it("produz um pedido demo completo sem supor um professor cadastrado", () => {
    const values = initialCreateExamValues(true)
    expect(validateCreateExam(values, true)).toEqual({})
    const request = buildCreateExamRequest(values, true)
    expect(request).toMatchObject({
      subject: "matematica", grade: 6, topics: ["frações"], question_count: 3,
      question_mode: "manual", question_distribution: { multiple_choice: 1, open_ended: 1, true_false: 1 },
    })
    expect(request).not.toHaveProperty("teacher_id")
    expect(request).not.toHaveProperty("formatting")
  })

  it("omite distribuição no automático e preferências que devem ser herdadas", () => {
    const values = { ...initialCreateExamValues(true), questionMode: "automatic" as const, teacherId: "42" }
    const request = buildCreateExamRequest(values, false)
    expect(request.teacher_id).toBe(42)
    expect(request).not.toHaveProperty("question_distribution")
    expect(request.exam_header).not.toHaveProperty("teacher_name")
    expect(request.exam_header).not.toHaveProperty("school_name")
    expect(request).not.toHaveProperty("formatting")
  })

  it("preserva sobrescritas de uma prova e permite deixar o nome em branco", () => {
    const values = { ...initialCreateExamValues(true), teacherId: "42", useTeacherHeader: false, schoolName: " Outra escola ", teacherName: "", useTeacherFormatting: false, fontFamily: "Calibri", fontSize: "18" }
    const request = buildCreateExamRequest(values, true)
    expect(request.exam_header).toMatchObject({ school_name: "Outra escola", teacher_name: null })
    expect(request.formatting).toEqual({ font_family: "Calibri", font_size: 18 })
  })

  it("detecta distribuição diferente do total e bloqueia o pedido", () => {
    const values = { ...initialCreateExamValues(true), questionCount: "4" }
    expect(validateCreateExam(values, false).questionCount).toBeTruthy()
    expect(() => buildCreateExamRequest(values, false)).toThrow()
  })

  it("não envia um cenário que o provedor demo não consegue gerar", () => {
    const values = { ...initialCreateExamValues(true), subject: "historia", grade: "4", topicText: "Brasil" }
    expect(validateCreateExam(values, true)).toHaveProperty("subject")
    expect(validateCreateExam(values, true)).toHaveProperty("grade")
    expect(validateCreateExam(values, true)).toHaveProperty("topicText")
  })

  it("remove duplicatas de tópicos sem perder a escrita original", () => {
    expect(parseTopics("Frações\n frações; Equivalência,FRACOES")).toEqual(["Frações", "Equivalência"])
  })

  it("confere datas reais, incluindo anos bissextos", () => {
    expect(validateCreateExam({ ...initialCreateExamValues(true), date: "2026-02-29" }, true).date).toBeTruthy()
    expect(validateCreateExam({ ...initialCreateExamValues(true), date: "2028-02-29" }, true).date).toBeUndefined()
    expect(validateCreateExam({ ...initialCreateExamValues(true), date: "2026-04-31" }, true).date).toBeTruthy()
  })

  it("usa os limites em bytes para os textos enviados ao provedor", () => {
    expect(validateCreateExam({ ...initialCreateExamValues(true), teacherNotes: "á".repeat(4097) }, true).teacherNotes).toBeTruthy()
    expect(validateCreateExam({ ...initialCreateExamValues(true), teacherNotes: "á".repeat(4096) }, true).teacherNotes).toBeUndefined()
  })

  it("valida apenas as preferências substituídas, preservando a herança legada", () => {
    const values = { ...initialCreateExamValues(true), fontSize: "40" }
    expect(validateCreateExam(values, true).fontSize).toBeUndefined()
    expect(validateCreateExam({ ...values, useTeacherFormatting: false }, true).fontSize).toBeTruthy()
  })
})
