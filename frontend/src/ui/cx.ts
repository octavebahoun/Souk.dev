/** Assemble des classes en ignorant les valeurs vides : cx('a', actif && 'b'). */
export function cx(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ')
}
