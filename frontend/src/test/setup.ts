import "@testing-library/jest-dom/vitest"
import { afterEach, vi } from "vitest"
import { cleanup } from "@testing-library/react"

afterEach(() => { cleanup(); vi.unstubAllGlobals() })
if (!window.requestAnimationFrame) window.requestAnimationFrame = (callback) => window.setTimeout(() => callback(performance.now()), 0)
