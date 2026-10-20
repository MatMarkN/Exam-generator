import { useEffect } from "react"
import { BookOpen, BookCheck, FilePlus2, Files, Leaf, ArrowUpRight, UserRoundCog, FlaskConical, ChevronRight, PanelLeftClose } from "lucide-react"
import { NavLink, Outlet, useLocation } from "react-router-dom"
import { cn } from "@/lib/utils"
import { environment } from "@/lib/environment"

const navigation = [
  { to: "/", label: "Criar prova", icon: FilePlus2, end: true },
  { to: "/provas", label: "Minhas provas", icon: Files, end: false },
  { to: "/professores", label: "Professores", icon: UserRoundCog, end: false },
  { to: "/bncc", label: "BNCC", icon: BookCheck, end: false },
]

function Brand() {
  return <NavLink to="/" aria-label="Prova Clara — início" className="flex items-center gap-3">
    <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-white"><BookOpen className="size-5" strokeWidth={1.7} aria-hidden="true" /></span>
    <span className="font-heading text-[22px] font-bold tracking-[-0.06em]">prova<span className="font-normal text-primary">clara</span><span className="text-[#b5c588]">.</span></span>
  </NavLink>
}

function Navigation({ mobile = false }: { mobile?: boolean }) {
  return <nav aria-label={mobile ? "Navegação principal móvel" : "Navegação principal"} className={cn(mobile ? "grid grid-cols-4 gap-1 px-2 py-3 lg:hidden" : "space-y-2")}>
    {navigation.map(({ to, label, icon: Icon, end }) => <NavLink key={to} to={to} end={end} className={({ isActive }) => cn(
      "group flex items-center gap-3 rounded-lg px-3.5 py-3 text-sm font-medium transition-colors",
      mobile && "min-w-0 flex-col justify-center gap-1.5 whitespace-nowrap px-1 py-2 text-[10px] sm:text-xs",
      isActive ? "bg-[#e9f0e9] text-primary" : "text-muted-foreground hover:bg-muted hover:text-foreground",
    )}>
      <Icon className={mobile ? "size-4 shrink-0" : "size-[18px]"} strokeWidth={1.7} aria-hidden="true" /><span>{label}</span>
    </NavLink>)}
  </nav>
}

export default function AppShell() {
  const { pathname } = useLocation()
  const current = pathname === "/" ? "Criar prova" : pathname === "/bncc" ? "BNCC" : pathname === "/professores" ? "Professores" : pathname === "/provas" ? "Minhas provas" : "Visualizar prova"
  useEffect(() => {
    document.title = current + " — Prova Clara"
    window.scrollTo(0, 0)
  }, [pathname, current])
  return <div className="min-h-screen">
    <a href="#conteudo" className="sr-only fixed left-4 top-4 z-50 rounded-lg bg-primary px-4 py-3 text-white focus:not-sr-only">Pular para o conteúdo</a>
    <aside className="fixed inset-y-0 left-0 z-20 hidden w-[244px] flex-col border-r bg-[#fcfdfb] px-5 pb-5 pt-8 lg:flex">
      <div className="px-2"><Brand /></div>
      <p className="eyebrow mb-4 mt-12 px-3.5">ESPAÇO DO PROFESSOR</p>
      <Navigation />
      <div className="mt-auto">
        <div className="relative overflow-hidden rounded-xl border border-[#e0e7d9] bg-[#eef3e8] px-4 pb-5 pt-6">
          <div className="mb-4 flex size-9 items-center justify-center rounded-full bg-white/80 text-primary"><Leaf className="size-4" aria-hidden="true" /></div>
          <h2 className="font-heading text-sm font-semibold">Mais espaço para ensinar.</h2>
          <p className="mt-2 text-xs leading-relaxed text-[#61735e]">Organize o que foi trabalhado e prepare sua próxima avaliação com cuidado.</p>
          <div className="pointer-events-none absolute -right-5 -top-7 size-24 rounded-full border-[18px] border-[#dfe7d1]/65" />
        </div>
        <div className="mt-5 flex items-center gap-3 border-t px-2 pt-5">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-full border bg-white"><BookOpen className="size-4 text-muted-foreground" aria-hidden="true" /></span>
          <div><p className="text-xs font-medium">Ensino Fundamental</p><p className="mt-0.5 text-[11px] text-muted-foreground">Do 1º ao 9º ano</p></div>
        </div>
      </div>
    </aside>
    <div className="lg:pl-[244px]">
      <header className="border-b bg-[#fcfdfb]">
        <div className="flex h-[76px] items-center justify-between gap-3 px-5 sm:px-8 lg:px-10">
          <div className="lg:hidden"><Brand /></div>
          <div className="hidden items-center gap-2 text-xs text-muted-foreground lg:flex"><PanelLeftClose className="mr-4 size-[17px] text-[#8b9890]" aria-hidden="true" /><span>Seu espaço</span><ChevronRight className="size-3 text-[#a8b1aa]" aria-hidden="true" /><span className="text-foreground">{current}</span></div>
          <div className="flex items-center gap-4">
            <span className="inline-flex items-center gap-2 rounded-md border border-[#dfdfcf] bg-[#faf9ed] px-2.5 py-1.5 text-[11px] font-medium text-[#706c3d]">
              <FlaskConical className="size-3.5" aria-hidden="true" />{environment.label}
            </span>
            <span className="hidden text-xs text-muted-foreground xl:block">Uma boa prova começa com clareza.</span>
          </div>
        </div>
        <Navigation mobile />
      </header>
      <main id="conteudo" tabIndex={-1} className="mx-auto min-h-[calc(100vh-76px)] max-w-[1420px] px-4 pb-10 pt-8 outline-none sm:px-8 lg:px-10 lg:pt-10">
        <Outlet />
        <footer className="mt-10 flex flex-wrap items-center justify-between gap-3 border-t pt-5 text-[11px] leading-relaxed text-muted-foreground">
          <span>Feito para o tempo de quem ensina.</span>
          <span className="inline-flex items-center gap-1.5"><BookOpen className="size-3.5" aria-hidden="true" />{environment.isDemo ? "Questões de exemplo · Frações, 6º ano" : "Currículo como referência, professor no comando."}</span>
        </footer>
      </main>
    </div>
  </div>
}

export function NotFoundPage() {
  return <section className="mx-auto max-w-lg py-20 text-center">
    <p className="eyebrow">Página não encontrada</p><h1 className="mt-4 text-3xl font-semibold">Vamos voltar ao seu espaço?</h1>
    <p className="mt-4 leading-relaxed text-muted-foreground">O endereço pode ter mudado. Suas provas continuam disponíveis na navegação.</p>
    <NavLink to="/" className="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-medium text-white">Criar uma prova<ArrowUpRight className="size-4" aria-hidden="true" /></NavLink>
  </section>
}
