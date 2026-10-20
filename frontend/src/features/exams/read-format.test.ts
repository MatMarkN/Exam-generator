import { describe, expect, it } from 'vitest'
import { formatCalendarDate, parseExamId, previewTypography } from './read-format'

describe('leitura da prova', () => {
  it('preserva o dia do cabeçalho sem aplicar o fuso horário do navegador', () => {
    expect(formatCalendarDate('2026-10-20')).toBe('20/10/2026')
    expect(formatCalendarDate(null)).toBeNull()
  })

  it('não faz consultas para identificadores fora do contrato da API', () => {
    expect(parseExamId('4294967295')).toBe(4294967295)
    for (const value of [undefined, '0', '-1', '1.5', '01', '4294967296', 'texto']) {
      expect(parseExamId(value)).toBeNull()
    }
  })

  it('limita fontes legadas somente na visualização, sem modificar o registro', () => {
    const legacy = { font_family: 'Fonte não instalada', font_size: 65535 }
    const copy = { ...legacy }
    const result = previewTypography(legacy)
    expect(result.style.fontSize).toBe('36pt')
    expect(result.style.fontFamily).toBe('Arial, Helvetica, sans-serif')
    expect(result.adjusted).toBe(true)
    expect(legacy).toEqual(copy)
  })

  it('respeita uma fonte suportada e usa o padrão para provas antigas', () => {
    expect(previewTypography({ font_family: 'Times New Roman', font_size: 14 })).toEqual({
      style: { fontFamily: '"Times New Roman", Georgia, serif', fontSize: '14pt' },
      adjusted: false,
    })
    expect(previewTypography().style.fontSize).toBe('12pt')
    expect(previewTypography().adjusted).toBe(false)
  })
})
