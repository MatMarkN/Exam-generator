const shortDateTime = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

const fontFamilies: Record<string, string> = {
  arial: 'Arial, Helvetica, sans-serif',
  calibri: 'Calibri, Arial, sans-serif',
  'times new roman': '"Times New Roman", Georgia, serif',
  verdana: 'Verdana, Geneva, sans-serif',
}

export function formatTimestamp(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? 'Data indisponível' : shortDateTime.format(date)
}

/** Datas do cabeçalho são dias do calendário, sem conversão de fuso horário. */
export function formatCalendarDate(value: string | null | undefined): string | null {
  if (!value) return null
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  return match ? match[3] + '/' + match[2] + '/' + match[1] : null
}

export function parseExamId(value: string | undefined): number | null {
  if (!value || !/^[1-9]\d{0,9}$/.test(value)) return null
  const id = Number(value)
  return Number.isSafeInteger(id) && id <= 4294967295 ? id : null
}

export function previewTypography(formatting?: { font_family: string; font_size: number }) {
  const family = formatting?.font_family.trim() || 'Arial'
  const size = formatting?.font_size ?? 12
  const supportedFamily = fontFamilies[family.toLocaleLowerCase('pt-BR')]
  const safeSize = Number.isFinite(size) ? Math.min(36, Math.max(8, size)) : 12
  return {
    style: { fontFamily: supportedFamily ?? fontFamilies.arial, fontSize: String(safeSize) + 'pt' },
    adjusted: !supportedFamily || safeSize !== size,
  }
}

export const questionTypeLabels = {
  multiple_choice: 'Múltipla escolha',
  open_ended: 'Dissertativa',
  true_false: 'Verdadeiro ou falso',
} as const
