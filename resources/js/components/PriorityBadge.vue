<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-[11px] font-bold rounded-md border uppercase tracking-wider backdrop-blur-md shadow-2xs"
    :class="priorityClass"
  >
    <span v-if="priority === 'urgent'" class="relative flex h-2 w-2">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
      <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
    </span>
    <span v-else class="w-1.5 h-1.5 rounded-full shrink-0" :class="dotClass"></span>
    <span>{{ formattedPriority }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  priority: string;
}>();

const formattedPriority = computed(() => {
  return (props.priority || 'medium').toUpperCase();
});

const priorityClass = computed(() => {
  switch (props.priority) {
    case 'urgent':
      return 'bg-rose-500/20 text-rose-300 border-rose-400/40 shadow-xs shadow-rose-500/20';
    case 'high':
      return 'bg-amber-500/20 text-amber-300 border-amber-400/40 shadow-xs shadow-amber-500/20';
    case 'medium':
      return 'bg-sky-500/20 text-sky-300 border-sky-400/40';
    case 'low':
      return 'bg-white/10 text-slate-300 border-white/20';
    default:
      return 'bg-white/10 text-slate-300 border-white/20';
  }
});

const dotClass = computed(() => {
  switch (props.priority) {
    case 'high': return 'bg-orange-500 shadow-xs shadow-orange-500';
    case 'medium': return 'bg-blue-500';
    case 'low': return 'bg-slate-400';
    default: return 'bg-slate-400';
  }
});
</script>
