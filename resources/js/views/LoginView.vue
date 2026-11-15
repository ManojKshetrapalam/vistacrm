<template>
  <div class="min-h-screen bg-[#18031E] flex flex-col justify-center relative overflow-hidden font-sans text-white selection:bg-vb-magenta selection:text-white">
    <!-- Top Brand Spectrum Bar -->
    <div class="spectrum h-1.5 w-full fixed top-0 left-0 z-50"></div>

    <!-- Ambient Gradient Lighting Mesh -->
    <div class="ambient-canvas" aria-hidden="true">
      <div class="ambient-blob-1"></div>
      <div class="ambient-blob-2"></div>
      <div class="ambient-blob-3"></div>
      <div class="ambient-blob-4"></div>
    </div>

    <div class="max-w-5xl w-full mx-auto p-4 sm:p-6 lg:p-8 relative z-10">
      <div class="grid md:grid-cols-2 gap-8 items-center">
        <!-- Brand Presentation Column -->
        <div class="p-6 lg:p-8 flex flex-col justify-between relative overflow-hidden">
          <div class="relative z-10 space-y-6">
            <div class="flex items-center gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md p-2 shadow-lg flex items-center justify-center shrink-0 border border-white/20">
                <img
                  :src="logoUrl"
                  alt="Vistabound Logo"
                  class="w-full h-full object-contain"
                  @error="onLogoError"
                />
              </div>
              <div>
                <span class="block font-display font-bold text-xl tracking-tight text-white">VISTABOUND</span>
                <span class="block text-[11px] tracking-[0.2em] uppercase text-vb-cyan font-semibold">INTEGRATED SOLUTIONS LLP</span>
              </div>
            </div>

            <div class="pt-4">
              <span class="text-[11px] uppercase tracking-widest text-vb-orange font-bold font-display inline-block bg-vb-orange/15 px-3 py-1 rounded-full border border-vb-orange/30">
                TEMPORARY CRM DESK
              </span>
              <h2 class="mt-4 text-3xl lg:text-4xl font-display font-bold leading-tight text-white">
                Lead Management &amp; Follow-up Operations
              </h2>
              <p class="mt-3 text-sm text-purple-200/80 leading-relaxed font-sans max-w-md">
                Internal sales execution portal for lead intake, WhatsApp outreach, scheduled client consultations, and pipeline conversion.
              </p>
            </div>

            <!-- Features Highlights -->
            <div class="pt-2 space-y-2.5 text-xs text-purple-200/90">
              <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-vb-magenta shadow-sm shadow-vb-magenta"></span>
                <span>Role-based access &amp; supervised team delegation</span>
              </div>
              <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-vb-teal shadow-sm shadow-vb-teal"></span>
                <span>Automated 5-minute follow-up reminder alerts</span>
              </div>
              <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-vb-green shadow-sm shadow-vb-green"></span>
                <span>1-Click templated WhatsApp client engagement</span>
              </div>
            </div>
          </div>

          <div class="relative z-10 pt-8 border-t border-white/10 mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-purple-200/60">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Central Node · Bengaluru, India</span>
            </div>
            <p class="text-[11px]">vistaboundintegrated.com · Internal Use Only</p>
          </div>
        </div>

        <!-- Floating Dark Glass Login Card -->
        <div class="glass-elevated p-8 sm:p-10 relative overflow-hidden shadow-2xl">
          <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>

          <div>
            <h3 class="font-display font-bold text-2xl text-white">Sign in to your desk</h3>
            <p class="text-xs text-purple-200/70 mt-1">Enter your CRM credentials to access your pipeline.</p>
          </div>

          <form @submit.prevent="handleLogin" class="mt-6 space-y-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5 flex items-center gap-1.5">
                <User class="w-3.5 h-3.5 text-purple-300" />
                <span>Username or Email Address</span>
              </label>
              <div class="relative">
                <input
                  v-model="loginIdentifier"
                  type="text"
                  required
                  autocomplete="username"
                  placeholder="prasanna@vista.com"
                  class="field text-sm pl-3.5 pr-3.5 py-3 rounded-xl bg-white/[0.08] border-white/15 text-white placeholder:text-white/35 focus:border-vb-magenta focus:ring-2 focus:ring-vb-magenta/30"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5 flex items-center gap-1.5">
                <Lock class="w-3.5 h-3.5 text-purple-300" />
                <span>Password</span>
              </label>
              <div class="relative">
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                  class="field text-sm pl-3.5 pr-11 py-3 rounded-xl bg-white/[0.08] border-white/15 text-white placeholder:text-white/35 focus:border-vb-magenta focus:ring-2 focus:ring-vb-magenta/30"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3.5 top-1/2 -translate-y-1/2 text-purple-300 hover:text-white transition-colors"
                  :title="showPassword ? 'Hide password' : 'Show password'"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Error Notice -->
            <div
              v-if="errorMessage"
              class="p-3.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-200 text-xs flex items-start gap-2.5 backdrop-blur-sm animate-in fade-in"
            >
              <AlertCircle class="w-4 h-4 text-rose-400 shrink-0 mt-0.5" />
              <span>{{ errorMessage }}</span>
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full btn-primary text-sm font-semibold py-3 px-6 rounded-full flex items-center justify-center gap-2 shadow-lg shadow-vb-plum/60 disabled:opacity-60 transition-all cursor-pointer mt-2"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ loading ? 'Authenticating...' : 'Sign In to CRM' }}</span>
              <ArrowRight v-if="!loading" class="w-4 h-4 ml-1" />
            </button>
          </form>

          <div class="mt-5 text-center">
            <span class="text-xs text-purple-200/60 hover:text-white transition-colors cursor-pointer">
              Forgot your password?
            </span>
          </div>

          <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-[11px] text-purple-200/50">
            <div class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              <span>Production Endpoint</span>
            </div>
            <span>vistaboundintegrated.com</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { AlertCircle, User, Lock, Eye, EyeOff, ArrowRight } from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();

const loginIdentifier = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');

// In Laravel/Vite, /favicon.jpg is available at web root
const logoUrl = ref('/favicon.jpg');

const onLogoError = () => {
  if (logoUrl.value === '/favicon.jpg') {
    logoUrl.value = '/favicon.png';
  } else if (logoUrl.value === '/favicon.png') {
    logoUrl.value = '/images/vistabound-logo.png';
  }
};

const handleLogin = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const success = await authStore.login({
      login: loginIdentifier.value.trim(),
      password: password.value,
    });

    if (success) {
      router.push('/dashboard');
    } else {
      errorMessage.value = authStore.error || 'The provided credentials do not match our records.';
    }
  } catch (err: any) {
    errorMessage.value = err?.message || 'Login failed. Please verify credentials.';
  } finally {
    loading.value = false;
  }
};
</script>
