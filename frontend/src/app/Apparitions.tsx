import { useEffect } from 'react'

const cible = '[data-reveal-item]'

/** Déclenche une seule fois l’apparition des cartes quand elles entrent dans l’écran. */
export function Apparitions() {
  useEffect(() => {
    const racine = document.getElementById('root') ?? document.body

    if (!('IntersectionObserver' in window)) {
      const reveler = (element: Element) => {
        if (element.matches(cible)) element.setAttribute('data-revealed', 'true')
        element.querySelectorAll(cible).forEach((enfant) => enfant.setAttribute('data-revealed', 'true'))
      }
      racine.querySelectorAll(cible).forEach((element) => element.setAttribute('data-revealed', 'true'))
      const mutations = new MutationObserver((changements) => {
        for (const changement of changements) {
          changement.addedNodes.forEach((noeud) => {
            if (noeud instanceof Element) reveler(noeud)
          })
        }
      })
      mutations.observe(racine, { childList: true, subtree: true })
      return () => mutations.disconnect()
    }

    const observation = new IntersectionObserver(
      (entrees) => {
        for (const entree of entrees) {
          if (!entree.isIntersecting) continue
          entree.target.setAttribute('data-revealed', 'true')
          observation.unobserve(entree.target)
        }
      },
      { threshold: 0.04, rootMargin: '0px 0px -24px 0px' },
    )

    let animationEnAttente = 0

    function revelerVisibles() {
      animationEnAttente = 0
      const hauteur = window.innerHeight
      racine.querySelectorAll(cible).forEach((element) => {
        if (element.getAttribute('data-revealed') === 'true') return
        const cadre = element.getBoundingClientRect()
        if (cadre.top < hauteur - 20 && cadre.bottom > 20) {
          element.setAttribute('data-revealed', 'true')
          observation.unobserve(element)
        }
      })
    }

    function planifierRevelation() {
      if (!animationEnAttente) animationEnAttente = window.requestAnimationFrame(revelerVisibles)
    }

    function observerElement(element: Element) {
      if (element.matches(cible) && element.getAttribute('data-revealed') !== 'true') {
        observation.observe(element)
      }
      element.querySelectorAll(cible).forEach((enfant) => {
        if (enfant.getAttribute('data-revealed') !== 'true') observation.observe(enfant)
      })
    }

    racine.querySelectorAll(cible).forEach((element) => observation.observe(element))
    planifierRevelation()
    const mutations = new MutationObserver((changements) => {
      for (const changement of changements) {
        changement.addedNodes.forEach((noeud) => {
          if (noeud instanceof Element) observerElement(noeud)
        })
      }
      planifierRevelation()
    })
    mutations.observe(racine, { childList: true, subtree: true })
    window.addEventListener('scroll', planifierRevelation, { passive: true })
    window.addEventListener('resize', planifierRevelation)

    return () => {
      mutations.disconnect()
      observation.disconnect()
      window.removeEventListener('scroll', planifierRevelation)
      window.removeEventListener('resize', planifierRevelation)
      if (animationEnAttente) window.cancelAnimationFrame(animationEnAttente)
    }
  }, [])

  return null
}
