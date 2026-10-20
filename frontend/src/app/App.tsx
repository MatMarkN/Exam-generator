import { Component, type ReactNode } from "react"
import { BrowserRouter, Routes, Route } from "react-router-dom"
import AppShell, { NotFoundPage } from "./AppShell"
import CreateExamPage from "@/features/exams/CreateExamPage"
import ExamPage from "@/features/exams/ExamPage"
import HistoryPage from "@/features/history/HistoryPage"
import TeachersPage from "@/features/teachers/TeachersPage"
import BnccPage from "@/features/bncc/BnccPage"
import { AuthProvider } from "@/features/auth/AuthProvider"
import AuthGate from "@/features/auth/AuthGate"
import AuthPage from "@/features/auth/AuthPage"

class ErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }
  static getDerivedStateFromError() { return { failed: true } }
  render() {
    if (this.state.failed) return <main className="mx-auto max-w-xl px-6 py-24"><h1 className="text-2xl font-semibold">Algo interrompeu esta tela.</h1><p className="mt-4 leading-relaxed text-muted-foreground">Recarregue para continuar. Se você estava gerando uma prova, confira o histórico antes de tentar novamente.</p><button className="mt-6 rounded-lg bg-primary px-5 py-3 text-sm text-white" onClick={() => window.location.reload()}>Recarregar a página</button></main>
    return this.props.children
  }
}

export default function App() {
  return <ErrorBoundary><AuthProvider><BrowserRouter><Routes>
    <Route path="entrar" element={<AuthPage key="login" mode="login" />} />
    <Route path="cadastro" element={<AuthPage key="register" mode="register" />} />
    <Route element={<AuthGate />}><Route element={<AppShell />}>
    <Route index element={<CreateExamPage />} />
    <Route path="provas" element={<HistoryPage />} />
    <Route path="provas/:id" element={<ExamPage />} />
    <Route path="professores" element={<TeachersPage />} />
    <Route path="bncc" element={<BnccPage />} />
    <Route path="*" element={<NotFoundPage />} />
  </Route></Route></Routes></BrowserRouter></AuthProvider></ErrorBoundary>
}
