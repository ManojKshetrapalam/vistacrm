<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-150"
    @click.self="handleCancel"
  >
    <div
      class="glass-elevated max-w-md w-full overflow-hidden border border-white/15 shadow-2xl animate-in zoom-in-95 duration-150"
      role="dialog"
      aria-modal="true"
    >
      <!-- Header -->
      <div class="p-5 border-b border-white/10 flex items-start gap-3.5 bg-white/[0.04] backdrop-blur-md">
        <div
          class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-xs border"
          :class="[
            variant === 'danger'
              ? 'bg-rose-500/20 text-rose-300 border-rose-500/30'
              : variant === 'warning'
              ? 'bg-amber-500/20 text-amber-300 border-amber-500/30'
              : 'bg-vb-magenta/20 text-purple-200 border-vb-magenta/30'
          ]"
        >
          <component :is="iconComponent" class="w-5 h-5" />
        </div>
        <div class="flex-1 pr-6">
          <h3 class="font-display font-bold text-base text-white leading-snug">
            {{ title }}
          </h3>
          <p v-if="subtitle" class="text-xs text-purple-200/70 mt-0.5 font-medium">
            {{ subtitle }}
          </p>
        </div>
        <button
          type="button"
          @click="handleCancel"
          class="p-1.5 rounded-lg text-purple-300 hover:text-white hover:bg-white/10 transition-colors"
          aria-label="Close dialog"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Content Body -->
      <div class="p-6 space-y-4">
        <p class="text-xs text-purple-200/90 leading-relaxed">
          {{ message }}
        </p>

        <!-- Detailed consequences callout -->
        <div
          v-if="consequences"
          class="p-3.5 rounded-xl border text-xs space-y-1"
          :class="[
            variant === 'danger'
              ? 'bg-rose-500/10 border-rose-500/30 text-rose-200'
              : variant === 'warning'
              ? 'bg-amber-500/10 border-amber-500/30 text-amber-200'
              : 'bg-white/[0.06] border-white/10 text-purple-200'
          ]"
        >
          <span class="font-bold block text-[11px] uppercase tracking-wider">
            Important Note:
          </span>
          <p class="text-[11px] leading-relaxed">
            {{ consequences }}
          </p>
        </div>

        <!-- Strict Confirmation Input -->
        <div v-if="requiredConfirmationText" class="space-y-1.5 pt-1">
          <label class="block text-xs font-semibold text-purple-200">
            Please type <span class="font-mono font-bold text-rose-400 select-all">{{ requiredConfirmationText }}</span> to confirm:
          </label>
          <input
            v-model="inputConfirmationText"
            type="text"
            class="field text-xs font-mono"
            :placeholder="requiredConfirmationText"
            autocomplete="off"
            @keyup.enter="canConfirm && handleConfirm()"
          />
        </div>

        <!-- Error display -->
        <p v-if="error" class="text-xs text-rose-300 bg-rose-500/20 p-2.5 rounded-lg border border-rose-500/40">
          {{ error }}
        </p>
      </div>

      <!-- Actions Footer -->
      <div class="p-4 bg-white/[0.03] backdrop-blur-sm border-t border-white/10 flex items-center justify-end gap-2.5">
        <button
          type="button"
          @click="handleCancel"
          :disabled="isSubmitting"
          class="px-4 py-2 text-xs font-semibold text-purple-200/70 hover:text-white rounded-full hover:bg-white/10 transition-colors disabled:opacity-50"
        >
          {{ cancelText || 'Cancel' }}
        </button>

        <button
          type="button"
          @click="handleConfirm"
          :disabled="!canConfirm || isSubmitting"
          class="px-5 py-2 rounded-full text-xs font-semibold shadow-sm transition-all flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
          :class="[
            variant === 'danger'
              ? 'bg-rose-600 hover:bg-rose-500 text-white shadow-rose-600/30'
              : variant === 'warning'
              ? 'bg-amber-600 hover:bg-amber-500 text-white shadow-amber-600/30'
              : 'btn-primary'
          ]"
        >
          <span v-if="isSubmitting" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span>{{ isSubmitting ? (submittingText || 'Processing...') : (confirmText || 'Confirm') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { AlertTriangle, AlertCircle, Info, X } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    title: string;
    subtitle?: string;
    message: string;
    consequences?: string;
    confirmText?: string;
    cancelText?: string;
    submittingText?: string;
    variant?: 'danger' | 'warning' | 'primary';
    requiredConfirmationText?: string;
    isSubmitting?: boolean;
    error?: string;
  }>(),
  {
    variant: 'danger',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    submittingText: 'Processing...',
  }
);

const emit = defineEmits(['confirm', 'cancel', 'update:isOpen']);

const inputConfirmationText = ref('');

watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      inputConfirmationText.value = '';
    }
  }
);

const iconComponent = computed(() => {
  if (props.variant === 'danger') return AlertTriangle;
  if (props.variant === 'warning') return AlertCircle;
  return Info;
});

const canConfirm = computed(() => {
  if (props.requiredConfirmationText) {
    return inputConfirmationText.value.trim() === props.requiredConfirmationText.trim();
  }
  return true;
});

const handleCancel = () => {
  if (props.isSubmitting) return;
  emit('cancel');
  emit('update:isOpen', false);
};

const handleConfirm = () => {
  if (!canConfirm.value || props.isSubmitting) return;
  emit('confirm');
};
</script>
