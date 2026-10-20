const mode = import.meta.env.VITE_API_MODE || "local"

export const environment = Object.freeze({
  isDemo: mode === "demo",
  label: mode === "demo" ? "Demonstração" : "Ambiente local",
  apiBase: (import.meta.env.VITE_API_BASE_URL || "/api").replace(/\/$/, ""),
})
