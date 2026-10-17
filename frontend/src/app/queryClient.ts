import { QueryClient } from '@tanstack/react-query'

export function creerQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: import.meta.env.MODE === 'test' ? false : 1,
      },
    },
  })
}
