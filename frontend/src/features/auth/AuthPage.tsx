import { useEffect, useRef, useState, type FormEvent } from "react"
import { BookOpen, Check, ArrowRight, LoaderCircle, LockKeyhole, Leaf } from "lucide-react"
import { Link, Navigate } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { getErrorMessage } from "@/lib/api/client"
import { useAuth } from "./AuthProvider"

export default function AuthPage({ mode }: { mode: "login" | "register" }) {
  const auth = useAuth()
  const register = mode === "register"
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [confirmation, setConfirmation] = useState("")
  const [pending, setPending] = useState(false)
  const [error, setError] = useState("")
  const guard = useRef(false)

  useEffect(() => { document.title = (register ? "Criar conta" : "Entrar") + " — Prova Clara" }, [register])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (guard.current) return
    setError("")
    if (register && name.trim().length < 2) { setError("Informe seu nome com pelo menos dois caracteres."); return }
    if (!email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) { setError("Informe um e-mail válido."); return }
    if (!password || (register && Array.from(password).length < 12)) { setError(register ? "Use uma senha com pelo menos 12 caracteres." : "Informe sua senha."); return }
    if (register && password !== confirmation) { setError("A confirmação precisa ser igual à senha."); return }
    guard.current = true
    setPending(true)
    try {
      if (register) await auth.register({ name: name.trim(), email: email.trim(), password, password_confirmation: confirmation })
      else await auth.login(email.trim(), password)
      setPassword("")
      setConfirmation("")
    } catch (failure) { setError(getErrorMessage(failure)) }
    finally { guard.current = false; setPending(false) }
  }

  if (auth.user) return <Navigate to="/" replace />
  return <div className="min-h-screen bg-[#f7f8f4] lg:grid lg:grid-cols-[minmax(380px,0.9fr)_minmax(0,1.1fr)]">
    <aside className="relative hidden overflow-hidden border-r border-[#dde5d8] bg-[#eef3e8] px-12 py-12 lg:flex lg:flex-col xl:px-20">
      <Brand />
      <div className="relative z-10 my-auto max-w-md py-16"><span className="mb-8 flex size-12 items-center justify-center rounded-2xl bg-white/70 text-primary"><Leaf className="size-6" /></span><p className="eyebrow">MAIS ESPAÇO PARA ENSINAR</p><h1 className="mt-5 font-heading text-5xl font-semibold leading-[1.14] tracking-tight">O seu cuidado,<br />em cada questão.</h1><p className="mt-6 max-w-sm text-base leading-relaxed text-[#61735e]">Prepare avaliações a partir do que foi trabalhado em sala, com a BNCC como referência e suas escolhas no comando.</p><ul className="mt-9 space-y-4 text-sm text-[#486043]">{["Provas e gabaritos organizados", "Preferências salvas no seu espaço", "Conteúdo curricular como referência"].map((item) => <li key={item} className="flex items-center gap-3"><Check className="size-4 shrink-0" />{item}</li>)}</ul></div>
      <p className="relative z-10 text-xs text-[#61735e]">Ensino Fundamental · 1º ao 9º ano</p>
      <div className="pointer-events-none absolute -bottom-24 -right-32 size-[420px] rounded-full border-[60px] border-[#e0e8d6]/80" />
    </aside>
    <main className="flex min-h-screen flex-col px-6 py-8 sm:px-10 lg:justify-center lg:px-14 xl:px-24">
      <div className="mb-12 lg:hidden"><Brand /></div>
      <div className="mx-auto w-full max-w-[420px] py-4">
        <p className="eyebrow">SEU ESPAÇO DO PROFESSOR</p><h1 className="mt-4 text-3xl font-semibold tracking-tight">{register ? "Vamos criar sua conta?" : "Que bom ter você por aqui."}</h1><p className="mt-3 text-sm leading-relaxed text-muted-foreground">{register ? "Guarde suas preferências e organize suas próximas avaliações em um só lugar." : "Entre para continuar seu planejamento e acessar suas provas."}</p>
        <form onSubmit={submit} noValidate className="mt-8 space-y-5" aria-label={register ? "Criar conta" : "Entrar"}>
          <fieldset disabled={pending || auth.loading} className="space-y-5">
            {register && <div className="space-y-2"><Label htmlFor="auth-name">Seu nome</Label><Input id="auth-name" autoComplete="name" maxLength={160} value={name} onChange={(event) => setName(event.target.value)} placeholder="Como você gostaria de ser chamado?" required /></div>}
            <div className="space-y-2"><Label htmlFor="auth-email">E-mail</Label><Input id="auth-email" type="email" autoComplete="email" maxLength={254} value={email} onChange={(event) => setEmail(event.target.value)} placeholder="voce@exemplo.com" required /></div>
            <div className="space-y-2"><Label htmlFor="auth-password">Senha</Label><Input id="auth-password" type="password" autoComplete={register ? "new-password" : "current-password"} maxLength={128} value={password} onChange={(event) => setPassword(event.target.value)} required />{register && <p className="text-xs text-muted-foreground">Use pelo menos 12 caracteres. Uma frase longa pode ajudar.</p>}</div>
            {register && <div className="space-y-2"><Label htmlFor="auth-confirmation">Confirmar senha</Label><Input id="auth-confirmation" type="password" autoComplete="new-password" maxLength={128} value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required /></div>}
            {error && <p role="alert" className="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm leading-relaxed text-destructive">{error}</p>}
            <Button type="submit" className="h-11 w-full">{pending || auth.loading ? <LoaderCircle className="size-4 animate-spin" /> : null}{pending ? "Aguarde…" : register ? "Criar minha conta" : "Entrar no meu espaço"}{!pending && !auth.loading && <ArrowRight className="size-4" />}</Button>
          </fieldset>
        </form>
        {!!auth.error && <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm"><p role="alert">{getErrorMessage(auth.error)}</p><Button variant="outline" size="sm" className="mt-3" onClick={() => void auth.refresh()}>Conferir conexão novamente</Button></div>}
        <p className="mt-6 text-center text-sm text-muted-foreground">{register ? "Já tem uma conta?" : "Primeira vez por aqui?"} <Link className="font-semibold text-primary underline-offset-4 hover:underline" to={register ? "/entrar" : "/cadastro"}>{register ? "Entrar" : "Criar conta"}</Link></p>
        <p className="mt-10 flex items-center justify-center gap-2 text-xs leading-relaxed text-muted-foreground"><LockKeyhole className="size-3.5 shrink-0" />Acesso protegido ao seu espaço.</p>
      </div>
    </main>
  </div>
}

function Brand() {
  return <Link to="/entrar" className="inline-flex items-center gap-3" aria-label="Prova Clara — entrar"><span className="flex size-10 items-center justify-center rounded-xl bg-primary text-white"><BookOpen className="size-5" /></span><span className="font-heading text-[24px] font-bold tracking-[-0.06em]">prova<span className="font-normal text-primary">clara</span><span className="text-[#b5c588]">.</span></span></Link>
}
