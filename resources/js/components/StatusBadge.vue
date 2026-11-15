<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-[11px] font-bold rounded-full border tracking-wide uppercase shadow-2xs backdrop-blur-md"
    :class="statusClass"
  >
    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="dotClass"></span>
    <span>{{ formattedStatus }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  status: string;
}>();

const formattedStatus = computed(() => {
  return (props.status || 'new').replace(/_/g, ' ');
});

const statusClass = computed(() => {
  switch (props.status) {
    case 'new':
      return 'bg-cyan-500/20 text-cyan-300 border-cyan-400/40 shadow-xs shadow-cyan-500/20';
    case 'assigned':
      return 'bg-purple-500/20 text-purple-200 border-purple-400/40 shadow-xs shadow-purple-500/20';
    case 'contacted':
      return 'bg-teal-500/20 text-teal-300 border-teal-400/40 shadow-xs shadow-teal-500/20';
    case 'follow_up':
      return 'bg-fuchsia-500/20 text-fuchsia-200 border-fuchsia-400/40 shadow-xs shadow-fuchsia-500/20';
    case 'interested':
      return 'bg-amber-500/20 text-amber-300 border-amber-400/40 shadow-xs shadow-amber-500/20';
    case 'converted':
      return 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40 shadow-xs shadow-emerald-500/20';
    case 'lost':
      return 'bg-rose-500/20 text-rose-300 border-rose-400/40 shadow-xs shadow-rose-500/20';
    case 'not_reachable':
      return 'bg-white/10 text-slate-300 border-white/20';
    default:
      return 'bg-white/10 text-purple-200 border-white/20';
  }
});

const dotClass = computed(() => {
  switch (props.status) {
    case 'new': return 'bg-cyan-500 shadow-xs shadow-cyan-500';
    case 'assigned': return 'bg-purple-500 shadow-xs shadow-purple-500';
    case 'contacted': return 'bg-amber-500 shadow-xs shadow-amber-500';
    case 'follow_up': return 'bg-indigo-500 shadow-xs shadow-indigo-500';
    case 'interested': return 'bg-teal-500 shadow-xs shadow-teal-500';
    case 'converted': return 'bg-emerald-500 shadow-xs shadow-emerald-500';
    case 'lost': return 'bg-rose-500 shadow-xs shadow-rose-500';
    case 'not_reachable': return 'bg-slate-400';
    default: return 'bg-slate-400';
  }
});
</script>
