import fs from "node:fs/promises"
import { fileURLToPath } from "node:url"
import openapiTS, { astToString } from "openapi-typescript"

const url = process.env.API_SCHEMA_URL || "http://127.0.0.1:8081/openapi.json"
const response = await fetch(url, { signal: AbortSignal.timeout(15000) })
if (!response.ok) throw new Error("A API não disponibilizou o contrato OpenAPI: HTTP " + response.status)
const schema = await response.json()
if (!schema.openapi?.startsWith("3.")) throw new Error("Contrato OpenAPI 3 esperado.")
const nodes = await openapiTS(schema, { defaultNonNullable: false })
const output = fileURLToPath(new URL("../src/lib/api/schema.d.ts", import.meta.url))
await fs.mkdir(fileURLToPath(new URL("../src/lib/api/", import.meta.url)), { recursive: true })
await fs.writeFile(output, "// Gerado pelo contrato OpenAPI do backend. Execute npm run api:types para atualizar.\n" + astToString(nodes))
console.log("Tipos gerados em src/lib/api/schema.d.ts")
