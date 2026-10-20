import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, errorMessage, type FieldErrors, type Note, type NoteInput } from '@/api/notes'

/** Submit flow shared by the create and edit views. */
export function useNoteSave(save: (input: NoteInput) => Promise<Note>, successMessage: string) {
  const router = useRouter()
  const submitting = ref(false)
  const fieldErrors = ref<FieldErrors>({})
  const formError = ref<string | null>(null)

  async function submit(input: NoteInput): Promise<void> {
    submitting.value = true
    fieldErrors.value = {}
    formError.value = null

    try {
      await save(input)
    } catch (error) {
      formError.value = errorMessage(error)
      if (error instanceof ApiError && error.code === 'validation_failed') {
        fieldErrors.value = error.fields
      }
      return
    } finally {
      submitting.value = false
    }

    await router.push({ name: 'notes', state: { flash: successMessage } })
  }

  return { submitting, fieldErrors, formError, submit }
}
