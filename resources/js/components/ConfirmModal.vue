<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 animate-in fade-in duration-150"
    @click.self="handleCancel"
  >
    <div
      class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-vb-plum/10 animate-in zoom-in-95 duration-150"
      role="dialog"
      aria-modal="true"
    >
      <!-- Header -->
      <div class="p-5 border-b border-vb-plum/10 flex items-start gap-3.5 bg-vb-cream/40">
        <div
          class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
          :class="[
            variant === 'danger'
              ? 'bg-rose-100 text-rose-600'
              : variant === 'warning'
              ? 'bg-amber-100 text-amber-700'
              : 'bg-vb-violet/10 text-vb-violet'
          ]"
        >
          <component :is="iconComponent" class="w-5 h-5" />
        </div>
        <div class="flex-1 pr-6">
          <h3 class="font-display font-bold text-base text-vb-plum leading-snug">
            {{ title }}
          </h3>
          <p v-if="subtitle" class="text-xs text-vb-muted mt-0.5 font-medium">
            {{ subtitle }}
          </p>
        </div>
        <button
          type="button"
          @click="handleCancel"
          class="p-1 rounded-lg text-vb-muted hover:text-vb-plum hover:bg-vb-plum/5 transition-colors"
          aria-label="Close dialog"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Content Body -->
      <div class="p-6 space-y-4">
        <p class="text-xs text-vb-ink leading-relaxed">
          {{ message }}
        </p>

        <!-- Detailed consequences callout -->
        <div
          v-if="consequences"
          class="p-3.5 rounded-xl border text-xs space-y-1"
          :class="[
            variant === 'danger'
              ? 'bg-rose-50/70 border-rose-200/80 text-rose-900'
              : variant === 'warning'
              ? 'bg-amber-50/70 border-amber-200/80 text-amber-900'
              : 'bg-vb-cream border-vb-plum/10 text-vb-plum'
          ]"
        >
          <span class="font-bold block text-[11px] uppercase tracking-wider">
            Important Note:
          </span>
          <p class="text-[11px] leading-relaxed">
            {{ consequences }}
          </p>
        </div>

        <!-- Strict Confirmation Input (e.g. typing reference_id or name for permanent delete) -->
        <div v-if="requiredConfirmationText" class="space-y-1.5 pt-1">
          <label class="block text-xs font-semibold text-vb-plum">
            Please type <span class="font-mono font-bold text-rose-600 select-all">{{ requiredConfirmationText }}</span> to confirm:
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
        <p v-if="error" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-200">
          {{ error }}
        </p>
      </div>

      <!-- Actions Footer -->
      <div class="p-4 bg-vb-cream/30 border-t border-vb-plum/10 flex items-center justify-end gap-2.5">
        <button
          type="button"
          @click="handleCancel"
          :disabled="isSubmitting"
          class="px-4 py-2 text-xs font-semibold text-vb-muted hover:text-vb-plum rounded-full hover:bg-vb-plum/5 transition-colors disabled:opacity-50"
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
              ? 'bg-rose-600 hover:bg-rose-700 text-white'
              : variant === 'warning'
              ? 'bg-amber-600 hover:bg-amber-700 text-white'
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
