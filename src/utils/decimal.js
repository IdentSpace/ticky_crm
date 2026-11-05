// Keep decimal strings exact; formatting must never pass prices through Number.
export const decimalDisplay = value => String(value ?? '').replaceAll('.', ',')
