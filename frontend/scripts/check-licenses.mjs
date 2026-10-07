import { readFileSync } from 'node:fs'

const FORBIDDEN = ['GPL', 'AGPL', 'LGPL']

let packages

try {
  packages = JSON.parse(readFileSync(0, 'utf8'))
} catch (error) {
  console.error(`JSON invalide : ${error.message}`)
  process.exit(2)
}

const violations = []

for (const [name, info] of Object.entries(packages)) {
  const raw = info.licenses

  if (!raw) {
    continue
  }

  const licenses = (Array.isArray(raw) ? raw : [raw])
    .flatMap((license) => String(license).split(/\s+OR\s+/i))
    .map((license) => license.trim())
    .filter(Boolean)

  if (licenses.length === 0) {
    continue
  }

  const hasAllowedOption = licenses.some(
    (license) => !FORBIDDEN.some((needle) => license.toUpperCase().includes(needle)),
  )

  if (!hasAllowedOption) {
    violations.push(`${name} : ${[...new Set(licenses)].join(', ')}`)
  }
}

if (violations.length > 0) {
  console.error('Licences interdites (GPL, AGPL, LGPL) :')

  for (const violation of violations) {
    console.error(` - ${violation}`)
  }

  process.exit(1)
}

console.log('Licences : aucune licence interdite (GPL, AGPL, LGPL).')
