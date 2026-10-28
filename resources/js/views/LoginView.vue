<template>
  <div class="min-h-screen bg-vb-cream flex flex-col justify-center relative overflow-hidden">
    <!-- Top Brand Spectrum Bar -->
    <div class="spectrum h-1.5 w-full fixed top-0 left-0 z-50"></div>

    <!-- Background Decorative Elements -->
    <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-vb-violet/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-vb-teal/10 blur-3xl pointer-events-none"></div>

    <div class="max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8 z-10">
      <div class="card-glass overflow-hidden shadow-2xl grid md:grid-cols-2">
        <!-- Brand Presentation Column -->
        <div class="p-8 lg:p-12 bg-gradient-to-br from-vb-plum via-vb-plum-2 to-vb-plum text-white flex flex-col justify-between relative overflow-hidden">
          <div class="absolute inset-0 bg-white/[0.03] backdrop-blur-[1px]"></div>
          <div class="relative z-10 space-y-6">
            <div class="flex items-center gap-3.5">
              <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur-md p-1.5 shadow-md flex items-center justify-center shrink-0 border border-white/20">
                <img
                  :src="logoUrl"
                  alt="Vistabound Logo"
                  class="w-full h-full object-contain"
                  @error="onLogoError"
                />
              </div>
              <div>
                <span class="block font-display font-bold text-xl tracking-tight text-white">VISTABOUND</span>
                <span class="block text-[11px] tracking-[0.2em] uppercase text-white/70">Integrated Solutions LLP</span>
              </div>
            </div>

            <div class="pt-6">
              <span class="text-xs uppercase tracking-widest text-vb-amber font-semibold font-display">
                Temporary CRM Desk
              </span>
              <h2 class="mt-2 text-2xl lg:text-3xl font-display font-bold leading-snug">
                Lead Management &amp; Follow-up Operations
              </h2>
              <p class="mt-3 text-sm text-white/80 leading-relaxed font-sans">
                Internal sales execution portal for lead intake, WhatsApp outreach, scheduled client consultations, and pipeline conversion.
              </p>
            </div>
          </div>

          <div class="relative z-10 pt-8 border-t border-white/10 mt-8">
            <div class="flex items-center gap-2 text-xs text-white/70">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Central Node · Bengaluru, India</span>
            </div>
            <p class="text-[11px] text-white/50 mt-1">vistaboundintegrated.com · Internal Use Only</p>
          </div>
        </div>

        <!-- Login Form Column -->
        <div class="p-8 lg:p-12 bg-white flex flex-col justify-center">
          <div>
            <!-- Mobile Brand Header (visible on mobile screens when left column is stacked) -->
            <div class="md:hidden flex items-center gap-3.5 mb-6 pb-4 border-b border-vb-plum/10">
              <div class="w-12 h-12 rounded-xl bg-vb-plum p-1.5 shadow-md flex items-center justify-center shrink-0">
                <img
                  :src="logoUrl"
                  alt="Vistabound Logo"
                  class="w-full h-full object-contain"
                  @error="onLogoError"
                />
              </div>
              <div>
                <span class="block font-display font-bold text-lg tracking-tight text-vb-plum leading-tight">VISTABOUND</span>
                <span class="block text-[10px] tracking-[0.18em] uppercase text-vb-muted font-semibold">Integrated Solutions LLP</span>
              </div>
            </div>

            <h3 class="font-display font-bold text-2xl text-vb-plum">Sign in to your desk</h3>
            <p class="text-sm text-vb-muted mt-1">Enter your CRM credentials to access your pipeline.</p>
          </div>

          <form @submit.prevent="handleLogin" class="mt-6 space-y-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Username or Email Address
              </label>
              <input
                v-model="email"
                type="text"
                required
                autocomplete="username"
                placeholder="admin or name@vistaboundintegrated.com"
                class="field text-sm"
              />
            </div>

            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum">
                  Password
                </label>
              </div>
              <input
                v-model="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••••••"
                class="field text-sm"
              />
            </div>

            <div v-if="errorMsg" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
              {{ errorMsg }}
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full btn-primary py-3 rounded-full font-display font-semibold text-sm flex items-center justify-center gap-2 mt-2"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ loading ? 'Authenticating...' : 'Sign In to CRM' }}</span>
            </button>

            <!-- Production Connection Status Footer -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-vb-muted">
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Production Endpoint</span>
              </span>
              <span class="font-mono text-[10px] text-vb-plum/70 font-medium">vistaboundintegrated.com</span>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import vistaboundLogo from '@/assets/vistabound-logo.png';
import { ref, computed } from 'vue';

const logoUrl = computed(() => {
  if (typeof window !== 'undefined' && window.location.pathname.startsWith('/CRM')) {
    return '/CRM/images/vistabound-logo.png';
  }
  return vistaboundLogo;
});

const onLogoError = (e: Event) => {
  const el = e.target as HTMLImageElement;
  if (!el.dataset.fallback) {
    el.dataset.fallback = '1';
    el.src = vistaboundLogo;
  } else if (el.dataset.fallback === '1') {
    el.dataset.fallback = '2';
    el.src = '/images/vistabound-logo.png';
  }
};
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const loading = ref(false);
const errorMsg = ref('');

const handleLogin = async () => {
  loading.value = true;
  errorMsg.value = '';

  try {
    await authStore.login({
      login: email.value.trim(),
      email: email.value.trim(),
      password: password.value,
    });
    router.push('/dashboard');
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'The provided credentials do not match our records.';
  } finally {
    loading.value = false;
  }
};
</script>
