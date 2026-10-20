/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_MODE?: "demo" | "local"
  readonly VITE_API_BASE_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
