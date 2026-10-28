<template>
  <div class="relative inline-block text-left" ref="menuRef">
    <button
      type="button"
      @click.stop="toggleMenu"
      :class="triggerClass || 'p-1.5 rounded-lg text-vb-muted hover:text-vb-plum hover:bg-vb-cream/80 border border-transparent hover:border-vb-plum/10 transition-all focus:outline-none focus:ring-2 focus:ring-vb-violet/30'"
      :aria-expanded="isOpen"
      :aria-label="ariaLabel || 'Actions menu'"
      :title="title || 'Actions'"
    >
      <slot name="trigger">
        <MoreVertical class="w-4 h-4" />
      </slot>
    </button>

    <transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div
        v-if="isOpen"
        class="absolute z-40 mt-1 min-w-[180px] w-max max-w-xs rounded-xl bg-white shadow-xl border border-vb-plum/10 py-1.5 focus:outline-none overflow-hidden"
        :class="align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right'"
        role="menu"
      >
        <template v-for="(item, idx) in visibleItems" :key="idx">
          <!-- Divider -->
          <div v-if="item.divider" class="my-1 border-t border-vb-plum/10" role="separator"></div>

          <!-- Router Link Action -->
          <router-link
            v-else-if="item.to"
            :to="item.to"
            @click="closeMenu"
            class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-vb-ink hover:bg-vb-cream/60 hover:text-vb-plum transition-colors"
            role="menuitem"
          >
            <component :is="item.icon" class="w-4 h-4 text-vb-muted shrink-0" />
            <span class="truncate">{{ item.label }}</span>
          </router-link>

          <!-- Button Action -->
          <button
            v-else
            type="button"
            :disabled="item.disabled"
            @click.stop="handleAction(item)"
            class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-left font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            :class="[
              item.danger
                ? 'text-rose-600 hover:bg-rose-50 hover:text-rose-700'
                : item.warning
                ? 'text-amber-700 hover:bg-amber-50'
                : 'text-vb-ink hover:bg-vb-cream/60 hover:text-vb-plum'
            ]"
            role="menuitem"
          >
            <component
              :is="item.icon"
              class="w-4 h-4 shrink-0"
              :class="item.danger ? 'text-rose-500' : item.warning ? 'text-amber-600' : 'text-vb-muted'"
            />
            <span class="truncate">{{ item.label }}</span>
          </button>
        </template>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { MoreVertical } from 'lucide-vue-next';

export interface ActionMenuItem {
  label?: string;
  icon?: any;
  action?: () => void | Promise<void>;
  to?: string;
  danger?: boolean;
  warning?: boolean;
  disabled?: boolean;
  show?: boolean;
  divider?: boolean;
}

const props = withDefaults(
  defineProps<{
    items: ActionMenuItem[];
    align?: 'left' | 'right';
    title?: string;
    ariaLabel?: string;
    triggerClass?: string;
  }>(),
  {
    align: 'right',
  }
);

const isOpen = ref(false);
const menuRef = ref<HTMLElement | null>(null);

const visibleItems = computed(() => {
  return props.items.filter((item) => item.show !== false);
});

const toggleMenu = () => {
  isOpen.value = !isOpen.value;
};

const closeMenu = () => {
  isOpen.value = false;
};

const handleAction = async (item: ActionMenuItem) => {
  if (item.disabled) return;
  closeMenu();
  if (item.action) {
    await item.action();
  }
};

const handleClickOutside = (e: MouseEvent) => {
  if (menuRef.value && !menuRef.value.contains(e.target as Node)) {
    closeMenu();
  }
};

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isOpen.value) {
    closeMenu();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeydown);
});
</script>
