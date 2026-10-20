import type { TeacherSettings } from "@/lib/api/types"

export interface ProfileFormValues { name: string; email: string }
export interface SettingsFormValues { schoolName: string; fontFamily: string; fontSize: string }
export type FieldErrors<T> = Partial<Record<keyof T, string>>

function lineError(value: string, label: string, limit: number, optional = false) {
  if (/[\p{Cc}\p{Zl}\p{Zp}]/u.test(value)) return label + " deve ficar em uma única linha."
  const normalized = value.trim()
  if (!normalized && !optional) return "Informe " + label.toLocaleLowerCase("pt-BR") + "."
  if ([...normalized].length > limit) return "Use até " + limit + " caracteres."
  return undefined
}

export function validateProfile(values: ProfileFormValues): FieldErrors<ProfileFormValues> {
  const errors: FieldErrors<ProfileFormValues> = {}
  errors.name = lineError(values.name, "O nome do professor", 160)
  errors.email = lineError(values.email, "O e-mail", 254, true)
  if (values.email.trim() && !errors.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) {
    errors.email = "Informe um e-mail válido ou deixe este campo em branco."
  }
  return Object.fromEntries(Object.entries(errors).filter(([, value]) => value)) as FieldErrors<ProfileFormValues>
}

export function profilePayload(values: ProfileFormValues) {
  return { name: values.name.trim(), email: values.email.trim() || null }
}

export function settingsValues(settings: TeacherSettings): SettingsFormValues {
  return { schoolName: settings.school_name ?? "", fontFamily: settings.default_font_family, fontSize: String(settings.default_font_size) }
}

export function validateSettings(values: SettingsFormValues, initial: TeacherSettings): FieldErrors<SettingsFormValues> {
  const errors: FieldErrors<SettingsFormValues> = {}
  errors.schoolName = lineError(values.schoolName, "A escola", 180, true)
  errors.fontFamily = lineError(values.fontFamily, "A fonte", 80)
  const size = Number(values.fontSize)
  // Valores legados não são reenviados quando o professor altera outra preferência.
  const unchanged = values.fontSize === String(initial.default_font_size)
  if (!unchanged && (!/^[0-9]+$/.test(values.fontSize) || !Number.isInteger(size) || size < 8 || size > 36)) {
    errors.fontSize = "Escolha um tamanho inteiro entre 8 e 36."
  }
  return Object.fromEntries(Object.entries(errors).filter(([, value]) => value)) as FieldErrors<SettingsFormValues>
}

export function settingsPatch(values: SettingsFormValues, initial: TeacherSettings): Partial<TeacherSettings> {
  const patch: Partial<TeacherSettings> = {}
  const school = values.schoolName.trim() || null
  const font = values.fontFamily.trim()
  if (school !== initial.school_name) patch.school_name = school
  if (font !== initial.default_font_family) patch.default_font_family = font
  if (values.fontSize !== String(initial.default_font_size)) patch.default_font_size = Number(values.fontSize)
  return patch
}
