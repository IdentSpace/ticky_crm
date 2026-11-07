// Keep decimal strings exact; formatting must never pass prices through Number.
export const decimalDisplay = value => String(value ?? '').replaceAll('.', ',')

// Compare decimal prices without losing precision through floating point conversion.
export const decimalCompare = (left, right) => {
  const [leftInteger, leftFraction = ''] = String(left).split('.')
  const [rightInteger, rightFraction = ''] = String(right).split('.')
  const scale = Math.max(leftFraction.length, rightFraction.length)
  const scaled = (integer, fraction) => BigInt(integer + fraction.padEnd(scale, '0'))
  const a = scaled(leftInteger, leftFraction)
  const b = scaled(rightInteger, rightFraction)
  return a < b ? -1 : a > b ? 1 : 0
}
