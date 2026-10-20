import { createRouter, createWebHistory } from 'vue-router'
import { isPriority } from '@/api/notes'
import NoteListView from '@/views/NoteListView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (_to, _from, savedPosition) => savedPosition ?? { top: 0 },
  routes: [
    {
      path: '/',
      name: 'notes',
      component: NoteListView,
      // Drop an invalid ?priority from the URL.
      beforeEnter: (to) =>
        to.query.priority === undefined || isPriority(to.query.priority) ? true : { name: 'notes' },
    },
    {
      path: '/notes/new',
      name: 'note-create',
      component: () => import('@/views/NoteCreateView.vue'),
      meta: { title: 'Nová poznámka' },
    },
    {
      path: '/notes/:id(\\d+)/edit',
      name: 'note-edit',
      component: () => import('@/views/NoteEditView.vue'),
      props: (route) => ({ id: Number(route.params.id) }),
      meta: { title: 'Upravit poznámku' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { title: 'Stránka nenalezena' },
    },
  ],
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | Poznámky` : 'Poznámky'
})

export default router
