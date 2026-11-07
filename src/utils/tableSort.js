const collator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true })

export function sortTableRows(rows, field, direction = 'asc', compare = null) {
  const sign = direction === 'desc' ? -1 : 1
  return [...rows].sort((a, b) => {
    if (compare) return sign * compare(a, b)
    const left = a[field]
    const right = b[field]
    // Keep empty values at the end in either direction.
    if (left == null || left === '') return right == null || right === '' ? 0 : 1
    if (right == null || right === '') return -1
    return sign * (typeof left === 'number' && typeof right === 'number'
      ? left - right
      : collator.compare(String(left), String(right)))
  })
}
