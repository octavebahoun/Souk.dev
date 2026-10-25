import '@testing-library/jest-dom/vitest'
import { configure, cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

configure({ asyncUtilTimeout: 5000 })

afterEach(cleanup)
