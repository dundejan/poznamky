import { createRouter, createWebHistory } from 'vue-router'
import NoteListView from '@/views/NoteListView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'notes',
      component: NoteListView,
    },
    {
      path: '/notes/new',
      name: 'note-create',
      component: () => import('@/views/NoteCreateView.vue'),
    },
    {
      path: '/notes/:id(\\d+)/edit',
      name: 'note-edit',
      component: () => import('@/views/NoteEditView.vue'),
      props: (route) => ({ id: Number(route.params.id) }),
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

export default router
