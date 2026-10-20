import { useState, type FormEvent } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { LoaderCircle, Plus, School, Type, UserRound } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { api, getErrorMessage } from "@/lib/api/client"
import type { Teacher, TeacherSettings } from "@/lib/api/types"
import { profilePayload, settingsPatch, settingsValues, validateProfile, validateSettings, type FieldErrors, type ProfileFormValues, type SettingsFormValues } from "./teacher-form"
import { initials, Notice, TeacherField } from "./teacher-ui"

export function NewTeacherForm({ onCreated }: { onCreated: (teacher: Teacher) => void }) {
  const queryClient = useQueryClient()
  const [values, setValues] = useState<ProfileFormValues>({ name: "", email: "" })
  const [errors, setErrors] = useState<FieldErrors<ProfileFormValues>>({})
  const mutation = useMutation({
    mutationFn: () => api.createTeacher(profilePayload(values)),
    retry: false,
    onSuccess: (teacher) => {
      queryClient.setQueryData(["teachers", teacher.id], teacher)
      void queryClient.invalidateQueries({ queryKey: ["teachers"] })
      onCreated(teacher)
    },
  })
  function change(field: keyof ProfileFormValues, value: string) {
    setValues((previous) => ({ ...previous, [field]: value }))
    setErrors((previous) => ({ ...previous, [field]: undefined }))
    mutation.reset()
  }
  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (mutation.isPending) return
    const validation = validateProfile(values)
    setErrors(validation)
    if (!Object.keys(validation).length) mutation.mutate()
  }
  return <Card className="border-border/70 shadow-none">
    <CardHeader className="border-b border-border/60">
      <div className="mb-2 flex size-11 items-center justify-center rounded-xl bg-primary/8 text-primary"><UserRound className="size-5" /></div>
      <CardTitle>Novo professor</CardTitle>
      <CardDescription>Comece pelo nome. Depois você pode salvar a escola e as preferências para suas próximas provas.</CardDescription>
    </CardHeader>
    <CardContent className="pt-6">
      <form onSubmit={submit} noValidate className="space-y-5">
        <fieldset disabled={mutation.isPending} className="space-y-5 disabled:opacity-70">
          <TeacherField id="new-teacher-name" label="Nome do professor" value={values.name} onChange={(value) => change("name", value)} error={errors.name} autoComplete="name" autoFocus placeholder="Como você quer aparecer nas provas?" />
          <TeacherField id="new-teacher-email" label="E-mail" optional value={values.email} onChange={(value) => change("email", value)} error={errors.email} type="email" autoComplete="email" placeholder="professor@escola.com.br" />
        </fieldset>
        {mutation.isError && <Notice error>{getErrorMessage(mutation.error)}</Notice>}
        <Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? <LoaderCircle className="size-4 animate-spin" /> : <Plus className="size-4" />}Cadastrar professor</Button>
      </form>
    </CardContent>
  </Card>
}

export function TeacherEditor({ teacher }: { teacher: Teacher }) {
  const queryClient = useQueryClient()
  const [savedTeacher, setSavedTeacher] = useState(teacher)
  const [profile, setProfile] = useState<ProfileFormValues>({ name: teacher.name, email: teacher.email ?? "" })
  const [settings, setSettings] = useState<SettingsFormValues>(() => settingsValues(teacher.settings))
  const [profileErrors, setProfileErrors] = useState<FieldErrors<ProfileFormValues>>({})
  const [settingsErrors, setSettingsErrors] = useState<FieldErrors<SettingsFormValues>>({})

  function refresh(updated: Teacher) {
    setSavedTeacher(updated)
    queryClient.setQueryData(["teachers", updated.id], updated)
    void queryClient.invalidateQueries({ queryKey: ["teachers"] })
  }
  const profileMutation = useMutation({
    mutationFn: () => api.updateTeacher(teacher.id, profilePayload(profile)),
    retry: false,
    onSuccess: (updated) => {
      refresh(updated)
      setProfile({ name: updated.name, email: updated.email ?? "" })
    },
  })
  const settingsMutation = useMutation({
    mutationFn: () => api.updateSettings(teacher.id, settingsPatch(settings, savedTeacher.settings)),
    retry: false,
    onSuccess: (updated: TeacherSettings) => {
      refresh({ ...savedTeacher, settings: updated })
      setSettings(settingsValues(updated))
    },
  })
  const busy = profileMutation.isPending || settingsMutation.isPending
  const profileChanged = profile.name.trim() !== savedTeacher.name || (profile.email.trim() || null) !== savedTeacher.email
  const settingsChanged = Object.keys(settingsPatch(settings, savedTeacher.settings)).length > 0
  const legacySize = savedTeacher.settings.default_font_size < 8 || savedTeacher.settings.default_font_size > 36
  function profileChange(field: keyof ProfileFormValues, value: string) {
    setProfile((previous) => ({ ...previous, [field]: value }))
    setProfileErrors((previous) => ({ ...previous, [field]: undefined }))
    profileMutation.reset()
  }
  function settingsChange(field: keyof SettingsFormValues, value: string) {
    setSettings((previous) => ({ ...previous, [field]: value }))
    setSettingsErrors((previous) => ({ ...previous, [field]: undefined }))
    settingsMutation.reset()
  }
  function submitProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || !profileChanged) return
    const errors = validateProfile(profile)
    setProfileErrors(errors)
    if (!Object.keys(errors).length) profileMutation.mutate()
  }
  function submitSettings(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || !settingsChanged) return
    const errors = validateSettings(settings, savedTeacher.settings)
    setSettingsErrors(errors)
    if (!Object.keys(errors).length) settingsMutation.mutate()
  }
  return <div className="space-y-5">
    <Card className="border-border/70 shadow-none">
      <CardHeader className="border-b border-border/60">
        <div className="flex items-center gap-3">
          <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-sm font-semibold text-primary">{initials(savedTeacher.name)}</div>
          <div><CardTitle>Dados do professor</CardTitle><CardDescription>Seu nome aparece no cabeçalho das próximas provas.</CardDescription></div>
        </div>
      </CardHeader>
      <CardContent className="pt-6">
        <form onSubmit={submitProfile} noValidate className="space-y-5">
          <fieldset disabled={busy} className="space-y-5 disabled:opacity-70">
            <TeacherField id="teacher-name" label="Nome do professor" value={profile.name} onChange={(value) => profileChange("name", value)} error={profileErrors.name} autoComplete="name" />
            <TeacherField id="teacher-email" label="E-mail" optional value={profile.email} onChange={(value) => profileChange("email", value)} error={profileErrors.email} type="email" autoComplete="email" />
          </fieldset>
          {profileMutation.isError && <Notice error>{getErrorMessage(profileMutation.error)}</Notice>}
          {profileMutation.isSuccess && <Notice>Dados do professor salvos.</Notice>}
          <div className="flex justify-end"><Button type="submit" disabled={busy || !profileChanged}>{profileMutation.isPending && <LoaderCircle className="size-4 animate-spin" />}Salvar dados</Button></div>
        </form>
      </CardContent>
    </Card>
    <Card className="border-border/70 shadow-none">
      <CardHeader className="border-b border-border/60">
        <div className="flex items-center gap-3">
          <div className="flex size-11 items-center justify-center rounded-xl bg-primary/8 text-primary"><School className="size-5" /></div>
          <div><CardTitle>Escola e apresentação</CardTitle><CardDescription>Preferências que você pode ajustar em cada nova prova.</CardDescription></div>
        </div>
      </CardHeader>
      <CardContent className="pt-6">
        <form onSubmit={submitSettings} noValidate className="space-y-5">
          <fieldset disabled={busy} className="space-y-5 disabled:opacity-70">
            <TeacherField id="teacher-school" label="Nome da escola" optional value={settings.schoolName} onChange={(value) => settingsChange("schoolName", value)} error={settingsErrors.schoolName} placeholder="Ex.: Escola Municipal Monteiro Lobato" />
            <div className="grid gap-5 sm:grid-cols-[1fr_130px]">
              <div>
                <TeacherField id="teacher-font" label="Fonte padrão" value={settings.fontFamily} onChange={(value) => settingsChange("fontFamily", value)} error={settingsErrors.fontFamily} list="teacher-font-options" />
                <datalist id="teacher-font-options"><option value="Arial" /><option value="Calibri" /><option value="Georgia" /><option value="Times New Roman" /><option value="Verdana" /></datalist>
              </div>
              <TeacherField id="teacher-size" label="Tamanho" type="number" min={8} max={36} value={settings.fontSize} onChange={(value) => settingsChange("fontSize", value)} error={settingsErrors.fontSize} />
            </div>
            <p className="text-sm leading-relaxed text-muted-foreground">A fonte será usada na leitura da prova quando estiver disponível no seu dispositivo.</p>
            <p className="text-sm text-muted-foreground">{legacySize ? "Este cadastro usa o tamanho " + savedTeacher.settings.default_font_size + ". Ele será preservado; para alterar, escolha de 8 a 36." : "Escolha um tamanho de 8 a 36."}</p>
            <div className="rounded-xl border border-dashed border-border bg-muted/25 px-5 py-4">
              <p className="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-muted-foreground"><Type className="size-3.5" /> Prévia da fonte</p>
              <p className="break-words leading-relaxed" style={{ fontFamily: '"' + settings.fontFamily.replace(/["\\]/g, "") + '", sans-serif', fontSize: Math.min(36, Math.max(8, Number(settings.fontSize) || 12)) + "px" }}>Uma boa avaliação começa com clareza.</p>
            </div>
          </fieldset>
          {settingsMutation.isError && <Notice error>{getErrorMessage(settingsMutation.error)}</Notice>}
          {settingsMutation.isSuccess && <Notice>Preferências salvas para as próximas provas.</Notice>}
          <div className="flex justify-end"><Button type="submit" disabled={busy || !settingsChanged}>{settingsMutation.isPending && <LoaderCircle className="size-4 animate-spin" />}Salvar preferências</Button></div>
        </form>
      </CardContent>
    </Card>
    <p className="px-1 text-sm leading-relaxed text-muted-foreground">As provas já geradas mantêm seus dados originais. Estas mudanças valem para as próximas gerações.</p>
  </div>
}
