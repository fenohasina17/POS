export const validatePermissionName = (name) => {
  if (!name) return 'Le nom est requis'
  if (name.length < 3) return 'Le nom doit contenir au moins 3 caractères'
  if (name.length > 50) return 'Le nom ne peut dépasser 50 caractères'
  if (!/^[a-z_.]+$/.test(name))
    return 'Utilisez uniquement des minuscules, des underscores et des points'
  if (!/^[a-z]/.test(name)) return 'Doit commencer par une lettre'
  if (!/[a-z]$/.test(name)) return 'Doit se terminer par une lettre'
  if (/_{2,}/.test(name)) return 'Ne peut contenir deux underscores consécutifs'
  if (/\.{2,}/.test(name)) return 'Ne peut contenir deux points consécutifs'
  if (/^\.|\.$/.test(name)) return 'Ne peut commencer ou se terminer par un point'
  if (!/^[a-z]+(?:\.[a-z]+)+$/.test(name))
    return 'Le format doit être "mot.mot" (au moins un point)'
  return null
}
