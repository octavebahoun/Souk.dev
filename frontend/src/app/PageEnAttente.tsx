type PageEnAttenteProps = {
  titre: string
}

export function PageEnAttente({ titre }: PageEnAttenteProps) {
  return (
    <div className="mx-auto max-w-[1200px] px-6 py-10">
      <h1 className="m-0 text-[30px] font-bold tracking-tight text-ink">{titre}</h1>
      <p className="mt-3 text-muted">Cet écran arrive dans une prochaine étape.</p>
    </div>
  )
}
