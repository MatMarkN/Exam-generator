import type { ReactNode } from "react"

export function PageHeader({ eyebrow, title, description, actions, children }: {
  eyebrow?: string; title: string; description?: string; actions?: ReactNode; children?: ReactNode
}) {
  return (
    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
      <div className="max-w-2xl">
        {eyebrow && <p className="eyebrow mb-3">{eyebrow}</p>}
        <h1 className="font-heading text-3xl font-semibold tracking-[-0.04em] sm:text-[34px]">{title}</h1>
        {description && <p className="mt-2.5 leading-relaxed text-muted-foreground">{description}</p>}
        {children}
      </div>
      {actions}
    </div>
  )
}
