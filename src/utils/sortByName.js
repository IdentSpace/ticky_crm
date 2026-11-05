const collator = new Intl.Collator('de', { sensitivity: 'base', numeric: true })

export const sortByName = items => [...items].sort((a, b) => collator.compare(a.name ?? '', b.name ?? '') || (a.id ?? 0) - (b.id ?? 0))
