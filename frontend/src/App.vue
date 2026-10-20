<template>
  <header class="app-header">
    <div class="container app-header__inner">
      <RouterLink
        :to="{ name: 'notes' }"
        class="app-header__brand"
      >
        Poznámky
      </RouterLink>
      <RouterLink
        :to="{ name: 'note-create' }"
        class="button button--primary"
      >
        Nová poznámka
      </RouterLink>
    </div>
  </header>

  <main class="container">
    <RouterView />
  </main>
</template>
