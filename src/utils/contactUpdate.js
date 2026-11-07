export function localToday() {
  const date = new Date()
  return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
}

export const newContactUpdate = () => ({ setDate: false, date: localToday(), setStatus: false, status: 'green' })

export function contactUpdatePayload(options) {
  const update = {}
  if (options.setDate) update.last_contact_date = options.date
  if (options.setStatus) {
    update.follow_up_mode = options.status === 'automatic' ? 'automatic' : 'manual'
    if (update.follow_up_mode === 'manual') update.follow_up_manual = options.status
  }
  return Object.keys(update).length ? update : null
}
