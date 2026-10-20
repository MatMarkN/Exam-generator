import { fileURLToPath, URL } from "node:url"
import { defineConfig, loadEnv } from "vite"
import react from "@vitejs/plugin-react"
import tailwindcss from "@tailwindcss/vite"

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "")
  const apiMode = env.VITE_API_MODE || "local"
  if (!["demo", "local"].includes(apiMode)) throw new Error("VITE_API_MODE deve ser demo ou local.")
  const target = env.API_PROXY_TARGET || (apiMode === "demo" ? "http://127.0.0.1:8081" : "http://127.0.0.1:8080")
  return {
    plugins: [react(), tailwindcss()],
    resolve: { alias: { "@": fileURLToPath(new URL("./src", import.meta.url)) } },
    server: {
      port: 5173, strictPort: true,
      proxy: { "/api": { target, changeOrigin: true, rewrite: (path) => path.replace(/^\/api/, "") } },
    },
    preview: { port: 4173, strictPort: true, proxy: { "/api": { target, changeOrigin: true, rewrite: (path) => path.replace(/^\/api/, "") } } },
  }
})
