<script setup>
defineProps({
  mobile: { type: Boolean, default: false },
  title: { type: String, default: '' },
  maxWidth: { type: String, default: '480px' },
});
defineEmits(['close']);
</script>

<template>
  <div v-if="mobile" class="fixed inset-0 bg-slate-900/50 flex items-end z-50" @click="$emit('close')">
    <section
      class="bg-white rounded-t-[20px] px-5 pt-5 pb-7 w-full max-h-[82%] overflow-y-auto shadow-[0_-12px_30px_rgba(0,0,0,0.2)]"
      @click.stop
    >
      <div class="w-9 h-1 rounded-full bg-slate-200 mx-auto mb-4"></div>
      <h2 v-if="title" class="text-base font-bold mb-4">{{ title }}</h2>
      <slot />
    </section>
  </div>
  <div v-else class="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-6 z-50" @click="$emit('close')">
    <section
      class="bg-white border border-slate-200 rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.2)] p-7 w-full max-h-[90vh] overflow-y-auto"
      :style="{ maxWidth }"
      @click.stop
    >
      <h2 v-if="title" class="text-base font-bold mb-5">{{ title }}</h2>
      <slot />
    </section>
  </div>
</template>
