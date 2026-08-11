<script setup>
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
  modelValue: { type: Number, required: true },
  display: { type: String, required: true },
  suffix: { type: String, default: '' },
  step: { type: [Number, String], default: 1 },
  min: { type: [Number, String], default: undefined },
  compact: { type: Boolean, default: false },
  valueClass: { type: String, default: '' },
  // 'display': prominent standalone number (Projection page). 'inline': muted label-style text (Budgets rows/income).
  variant: { type: String, default: 'display' },
});

const VALUE_CLASSES = {
  display: { full: 'text-[28px] font-extrabold tracking-tight leading-none', compact: 'text-sm font-extrabold' },
  inline: { full: 'text-sm font-semibold text-slate-600', compact: 'text-xs font-semibold text-slate-600' },
};
const emit = defineEmits(['update:modelValue']);

const editing = ref(false);
const draft = ref(props.modelValue);

function startEditing() {
  draft.value = props.modelValue;
  editing.value = true;
}
function commit() {
  emit('update:modelValue', Number(draft.value) || 0);
  editing.value = false;
}
</script>

<template>
  <div class="flex items-center gap-2">
    <template v-if="editing">
      <input
        type="number"
        :step="step"
        :min="min"
        v-model.number="draft"
        autofocus
        @keyup.enter="commit"
        class="text-right font-semibold border border-indigo-600 rounded-lg"
        :class="compact ? 'w-[70px] px-2 py-1 text-[13px] rounded-[7px]' : 'w-[110px] px-2.5 py-1.5 text-sm'"
      />
      <span v-if="suffix" class="text-slate-500" :class="compact ? 'text-xs' : 'text-sm'">{{ suffix }}</span>
      <button
        type="button"
        class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold cursor-pointer"
        :class="compact ? 'px-2.5 py-1 text-xs rounded-[7px]' : 'px-3.5 py-1.5 text-[13px]'"
        @click="commit"
      >
        <Icon name="check" :size="compact ? 12 : 14" :stroke-width="2.2" />OK
      </button>
    </template>
    <template v-else>
      <span :class="[compact ? VALUE_CLASSES[variant].compact : VALUE_CLASSES[variant].full, valueClass]">{{ display }}</span>
      <button
        type="button"
        class="inline-flex items-center gap-1 font-semibold text-indigo-600 cursor-pointer"
        :class="compact ? 'text-[11px]' : 'border border-slate-200 rounded-lg px-3 py-1.5 text-[13px] hover:bg-slate-50'"
        @click="startEditing"
      >
        <Icon name="edit" :size="compact ? 11 : 13" />Modifier
      </button>
    </template>
  </div>
</template>
