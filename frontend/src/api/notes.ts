export const PRIORITIES = ['high', 'medium', 'low'] as const

export type Priority = (typeof PRIORITIES)[number]

export interface Note {
  id: number
  title: string
  content: string
  priority: Priority
  createdAt: string
  updatedAt: string
}

export interface NoteInput {
  title: string
  content: string
  priority: Priority
}

export type FieldErrors = Partial<Record<string, string[]>>

/** API error response, or a network failure (status 0). */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly fields: FieldErrors = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export function isPriority(value: unknown): value is Priority {
  return typeof value === 'string' && (PRIORITIES as readonly string[]).includes(value)
}

/** User-facing message; unexpected errors are logged, not shown. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message
  }
  console.error(error)
  return 'Něco se nepovedlo. Zkuste to prosím znovu.'
}

export async function listNotes(priority: Priority | null, signal?: AbortSignal): Promise<Note[]> {
  const query = priority ? `?priority=${priority}` : ''
  const body = await request<{ data: Note[] }>(`/notes${query}`, { signal })
  return body.data
}

export function getNote(id: number, signal?: AbortSignal): Promise<Note> {
  return request<Note>(`/notes/${id}`, { signal })
}

export function createNote(input: NoteInput): Promise<Note> {
  return request<Note>('/notes', { method: 'POST', body: JSON.stringify(input) })
}

export function updateNote(id: number, input: NoteInput): Promise<Note> {
  return request<Note>(`/notes/${id}`, { method: 'PUT', body: JSON.stringify(input) })
}

export async function deleteNote(id: number): Promise<void> {
  await request<null>(`/notes/${id}`, { method: 'DELETE' })
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (init.body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  let response: Response
  try {
    response = await fetch(`/api${path}`, { ...init, headers })
  } catch (error) {
    if (init.signal?.aborted) {
      throw error
    }
    throw new ApiError(0, 'network_error', 'Server je nedostupný. Zkontrolujte připojení a zkuste to znovu.')
  }

  if (response.status === 204) {
    return null as T
  }

  const body: unknown = await response.json().catch((error: unknown) => {
    if (init.signal?.aborted) {
      throw error
    }
    return null
  })

  if (!response.ok) {
    throw toApiError(response.status, body)
  }
  if (body === null) {
    throw unexpectedResponse(response.status)
  }

  return body as T
}

function toApiError(status: number, body: unknown): ApiError {
  const error = (body as { error?: { code?: string; message?: string; fields?: FieldErrors } } | null)?.error

  // Not our error shape, e.g. a proxy 502 while PHP starts.
  if (!error?.code || !error.message) {
    return unexpectedResponse(status)
  }

  return new ApiError(status, error.code, error.message, error.fields ?? {})
}

function unexpectedResponse(status: number): ApiError {
  return new ApiError(status, 'unexpected_response', 'Server odpověděl neočekávaně. Zkuste to prosím znovu.')
}
