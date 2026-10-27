<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border tracking-wide uppercase"
    :class="statusClass"
  >
    <span class="w-1.5 h-1.5 rounded-full" :class="dotClass"></span>
    {{ formattedStatus }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  status: string;
}>();

const formattedStatus = computed(() => {
  return (props.status || 'new').replace('_', ' ');
});

const statusClass = computed(() => {
  switch (props.status) {
    case 'new':
      return 'bg-cyan-50 text-cyan-800 border-cyan-200';
    case 'assigned':
      return 'bg-purple-50 text-purple-800 border-purple-200';
    case 'contacted':
      return 'bg-amber-50 text-amber-800 border-amber-200';
    case 'follow_up':
      return 'bg-indigo-50 text-indigo-800 border-indigo-200';
    case 'interested':
      return 'bg-teal-50 text-teal-800 border-teal-200';
    case 'converted':
      return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    case 'lost':
      return 'bg-rose-50 text-rose-800 border-rose-200';
    case 'not_reachable':
      return 'bg-gray-100 text-gray-700 border-gray-300';
    default:
      return 'bg-gray-50 text-gray-700 border-gray-200';
  }
});

const dotClass = computed(() => {
  switch (props.status) {
    case 'new': return 'bg-cyan-500';
    case 'assigned': return 'bg-purple-500';
    case 'contacted': return 'bg-amber-500';
    case 'follow_up': return 'bg-indigo-500';
    case 'interested': return 'bg-teal-500';
    case 'converted': return 'bg-emerald-500';
    case 'lost': return 'bg-rose-500';
    case 'not_reachable': return 'bg-gray-400';
    default: return 'bg-gray-400';
  }
});
</script>
