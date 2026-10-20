import { useQueries, useQuery } from "@tanstack/react-query"
import { BookCheck, LoaderCircle } from "lucide-react"
import { Link } from "react-router-dom"
import { api, getErrorMessage } from "@/lib/api/client"

export default function CurriculumAvailability({ subject, grade }: { subject: string; grade: number }) {
  const documents = useQuery({ queryKey: ["bncc", "documents"], queryFn: api.bnccDocuments })
  const releases = useQueries({ queries: (documents.data?.items ?? []).filter((document) => document.status !== "archived").map((document) => ({
    queryKey: ["bncc", document.id, "releases"], queryFn: () => api.bnccReleases(document.id),
    enabled: !!subject && grade >= 1 && grade <= 9,
  })) })
  if (!subject || grade < 1 || grade > 9) return null
  const available = releases.some((query) => query.data?.items.some((scope) => scope.subject.code === subject && scope.grade.year_number === grade && scope.status === "ready"))
  const loading = documents.isPending || releases.some((query) => query.isPending)
  const error = documents.error || releases.find((query) => query.isError)?.error
  return <div className={"mt-4 rounded-xl border px-4 py-3 text-sm leading-relaxed " + (available ? "border-primary/15 bg-primary/5 text-primary" : "border-amber-200 bg-amber-50/70 text-amber-950")} role="status">
    <p className="flex items-start gap-2">{loading && !available ? <LoaderCircle className="mt-0.5 size-4 shrink-0 animate-spin" aria-hidden="true" /> : <BookCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true" />}<span>{available ? "A BNCC está liberada para esta matéria e ano escolar." : loading ? "Conferindo a disponibilidade da BNCC…" : error ? getErrorMessage(error) : "A BNCC desta matéria e ano ainda precisa de revisão e liberação antes da geração."}</span></p>
    {!available && !loading && <Link to="/bncc" className="mt-2 inline-block font-semibold underline underline-offset-4">Abrir revisão da BNCC</Link>}
  </div>
}
