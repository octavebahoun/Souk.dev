/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE: string
  readonly VITE_REVERB_HOST: string
  readonly VITE_REVERB_PORT: string
  readonly VITE_REVERB_KEY: string
  readonly VITE_REVERB_SCHEME: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
