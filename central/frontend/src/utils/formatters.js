/**
 * Formate un montant en Ariary malgache.
 * Ex: 125000 → "125 000 Ar"
 */
export const fmt = (v) => {
  const n = Number(v ?? 0)
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n)} Ar`
}

/**
 * Formate un montant en version compacte pour les KPI cards.
 * Ex: 1250000 → "1 250k Ar" | 800 → "800 Ar"
 */
export const fmtShort = (v) => {
  const n = Number(v ?? 0)
  if (!n) return '0 Ar'
  if (n >= 1_000_000) return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(n / 1_000_000)}M Ar`
  if (n >= 1_000)     return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(n / 1_000))}k Ar`
  return `${Math.round(n)} Ar`
}
