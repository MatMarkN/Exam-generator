/** Somente o token CSRF fica acessível ao JavaScript; a sessão pertence ao cookie HttpOnly. */
let csrfToken: string | null = null
let sessionVersion = 0
const listeners = new Set<() => void>()

export function setCsrfToken(value: string | null) { csrfToken = value; sessionVersion++ }
export function getCsrfToken() { return csrfToken }
export function getSessionVersion() { return sessionVersion }
export function onSessionExpired(listener: () => void) {
  listeners.add(listener)
  return () => { listeners.delete(listener) }
}
export function expireSession(expectedVersion: number) {
  if (expectedVersion !== sessionVersion) return
  setCsrfToken(null)
  for (const listener of listeners) listener()
}
