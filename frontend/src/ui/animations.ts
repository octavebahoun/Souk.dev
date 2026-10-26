/**
 * Les animations (menus, modales, toasts, résultat d'un paiement) passent par les composants
 * légers « m » de motion ; leur moteur se charge à part, après le premier affichage, pour ne
 * pas retarder l'arrivée de la page.
 */
export const chargerAnimations = () => import('./fonctionsAnimation').then((module) => module.default)
