<template>
  <div class="card state">
    <h1>Stránka nenalezena</h1>
    <p>Tato stránka neexistuje.</p>
    <RouterLink
      :to="{ name: 'notes' }"
      class="button"
    >
      Zpět na seznam
    </RouterLink>
  </div>
</template>
