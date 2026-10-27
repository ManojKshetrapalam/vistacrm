<template>
  <span
    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold rounded-md border"
    :class="priorityClass"
  >
    <span v-if="priority === 'urgent'" class="relative flex h-2 w-2">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
      <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
    </span>
    <span v-else class="w-1.5 h-1.5 rounded-full" :class="dotClass"></span>
    {{ formattedPriority }}
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
      return 'bg-red-50 text-red-700 border-red-200';
    case 'high':
      return 'bg-orange-50 text-orange-700 border-orange-200';
    case 'medium':
      return 'bg-blue-50 text-blue-700 border-blue-200';
    case 'low':
      return 'bg-slate-50 text-slate-600 border-slate-200';
    default:
      return 'bg-gray-50 text-gray-700 border-gray-200';
  }
});

const dotClass = computed(() => {
  switch (props.priority) {
    case 'high': return 'bg-orange-500';
    case 'medium': return 'bg-blue-500';
    case 'low': return 'bg-slate-400';
    default: return 'bg-gray-400';
  }
});
</script>
