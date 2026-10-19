const dateTime = new Intl.DateTimeFormat('cs-CZ', { dateStyle: 'medium', timeStyle: 'short' })

export function formatDateTime(iso: string): string {
  return dateTime.format(new Date(iso))
}

/** 1 poznámka, 2 až 4 poznámky, 5 a více poznámek. */
export function pluralizeNotes(count: number): string {
  if (count === 1) return '1 poznámka'
  if (count >= 2 && count <= 4) return `${count} poznámky`
  return `${count} poznámek`
}

export function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : 'Něco se nepovedlo.'
}
