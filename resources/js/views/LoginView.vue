<template>
  <div class="min-h-screen bg-[#15021C] flex flex-col justify-center relative overflow-hidden font-sans text-white selection:bg-vb-magenta selection:text-white">
    <!-- Top Brand Spectrum Bar -->
    <div class="spectrum h-1.5 w-full fixed top-0 left-0 z-50"></div>

    <!-- Ambient Gradient Lighting Mesh & Radiant Waves -->
    <div class="ambient-canvas pointer-events-none" aria-hidden="true">
      <!-- Top-Left Soft Plum Ambient Aura -->
      <div class="absolute -top-[10%] -left-[10%] w-[55vw] h-[55vw] max-w-[650px] max-h-[650px] rounded-full bg-gradient-to-br from-[#8E1E78]/30 to-[#5A0E57]/15 blur-[95px]"></div>

      <!-- Top-Right Intense Cyan/Teal Radiant Glow -->
      <div class="absolute -top-[12%] right-[-8%] w-[60vw] h-[55vw] max-w-[720px] max-h-[650px] rounded-full bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-[#00F0FF]/40 via-[#00A9A5]/30 to-transparent blur-[75px]"></div>

      <!-- Center-Right Violet Diffusion -->
      <div class="absolute top-[25%] right-[10%] w-[45vw] h-[45vw] max-w-[550px] max-h-[550px] rounded-full bg-[radial-gradient(circle,_var(--tw-gradient-stops))] from-[#8E1E78]/30 via-[#5A0E57]/20 to-transparent blur-[85px]"></div>

      <!-- Bottom-Right Radiant Amber/Orange Ambient Glow -->
      <div class="absolute bottom-[-15%] right-[-5%] w-[70vw] h-[60vw] max-w-[850px] max-h-[700px] rounded-full bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-[#FF8C00]/50 via-[#E11D48]/35 via-[#8E1E78]/25 to-transparent blur-[80px]"></div>

      <!-- Top-Right Cyan/Teal Arc Ribbon -->
      <svg class="absolute top-0 right-0 w-[50%] h-[38%] min-h-[250px] pointer-events-none overflow-visible" viewBox="0 0 720 350" fill="none" preserveAspectRatio="none">
        <defs>
          <linearGradient id="cyan-arc-grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#00F0FF" stop-opacity="0.85" />
            <stop offset="60%" stop-color="#00A9A5" stop-opacity="0.6" />
            <stop offset="100%" stop-color="#21A3C4" stop-opacity="0.1" />
          </linearGradient>
          <filter id="soft-glow" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="14" />
          </filter>
        </defs>
        <path d="M 220 0 C 340 140, 520 160, 720 90 L 720 0 Z" fill="url(#cyan-arc-grad)" filter="url(#soft-glow)" opacity="0.6" />
      </svg>

      <!-- Bottom Silky Vector Ribbon Waves (Orange, Magenta, Plum) -->
      <svg class="absolute bottom-0 left-0 w-full h-[58%] min-h-[420px] pointer-events-none overflow-visible" viewBox="0 0 1440 500" fill="none" preserveAspectRatio="none">
        <defs>
          <linearGradient id="ribbon-deep-plum" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#420640" stop-opacity="0.9" />
            <stop offset="30%" stop-color="#6B1066" stop-opacity="0.95" />
            <stop offset="65%" stop-color="#8E1E78" stop-opacity="0.9" />
            <stop offset="100%" stop-color="#A21CAF" stop-opacity="0.8" />
          </linearGradient>
          <linearGradient id="ribbon-vibrant-orange" x1="0%" y1="100%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#7B2D8E" stop-opacity="0.8" />
            <stop offset="35%" stop-color="#C026D3" stop-opacity="0.9" />
            <stop offset="68%" stop-color="#FF5500" stop-opacity="0.95" />
            <stop offset="85%" stop-color="#FF8C00" stop-opacity="0.98" />
            <stop offset="100%" stop-color="#FFAE00" stop-opacity="0.9" />
          </linearGradient>
          <linearGradient id="ribbon-crest-highlight" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="20%" stop-color="#C026D3" stop-opacity="0" />
            <stop offset="55%" stop-color="#FF7A00" stop-opacity="0.8" />
            <stop offset="80%" stop-color="#FFAE00" stop-opacity="0.95" />
            <stop offset="100%" stop-color="#FFD600" stop-opacity="0.7" />
          </linearGradient>
          <filter id="ribbon-ambient-blur" x="-10%" y="-10%" width="120%" height="120%">
            <feGaussianBlur stdDeviation="8" />
          </filter>
        </defs>

        <!-- Base Plum Flow -->
        <path d="M -50 360 C 280 440, 560 300, 920 330 C 1180 350, 1340 260, 1500 220 L 1500 500 L -50 500 Z" fill="url(#ribbon-deep-plum)" opacity="0.85" />

        <!-- Main Vibrant S-Curve Ribbon (Sweeps under and behind the login card) -->
        <path d="M -50 410 C 320 480, 620 320, 960 270 C 1200 230, 1380 180, 1500 150 L 1500 500 L -50 500 Z" fill="url(#ribbon-vibrant-orange)" opacity="0.92" filter="url(#ribbon-ambient-blur)" />

        <!-- Radiant Ribbon Crest Edge -->
        <path d="M 320 480 C 620 320, 960 270, 1500 150" stroke="url(#ribbon-crest-highlight)" stroke-width="5" opacity="0.85" filter="url(#ribbon-ambient-blur)" />
      </svg>
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

        <!-- Floating True Glass Login Card -->
        <div class="glass-card-true p-8 sm:p-10 relative overflow-hidden">
          <!-- Top Spectrum Accent Edge -->
          <div class="h-[2.5px] w-full absolute top-0 left-0 bg-gradient-to-r from-[#8E1E78] via-[#00A9A5] via-[#7BBF3B] to-[#FF8C00] rounded-t-[28px]"></div>

          <div>
            <h3 class="font-display font-bold text-2xl text-white tracking-tight">Sign in to your desk</h3>
            <p class="text-xs text-purple-200/80 mt-1">Enter your CRM credentials to access your pipeline.</p>
          </div>

          <form @submit.prevent="handleLogin" class="mt-6 space-y-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200/90 mb-1.5 flex items-center gap-1.5">
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
                  class="glass-input text-sm pl-3.5 pr-3.5 py-3"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200/90 mb-1.5 flex items-center gap-1.5">
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
                  class="glass-input text-sm pl-3.5 pr-11 py-3"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3.5 top-1/2 -translate-y-1/2 text-purple-200/70 hover:text-white transition-colors"
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
              class="w-full btn-primary text-sm font-semibold py-3 px-6 rounded-full flex items-center justify-center gap-2 shadow-lg shadow-[#8E1E78]/50 disabled:opacity-60 transition-all cursor-pointer mt-2"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ loading ? 'Authenticating...' : 'Sign In to CRM' }}</span>
              <ArrowRight v-if="!loading" class="w-4 h-4 ml-1" />
            </button>
          </form>

          <div class="mt-5 text-center">
            <span class="text-xs text-purple-200/70 hover:text-white transition-colors cursor-pointer">
              Forgot your password?
            </span>
          </div>

          <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-[11px] text-purple-200/60">
            <div class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></span>
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
    if (err.response?.data?.message) {
      errorMessage.value = err.response.data.message;
    } else if (err.response?.data?.errors?.email?.[0]) {
      errorMessage.value = err.response.data.errors.email[0];
    } else if (err.response?.status === 422) {
      errorMessage.value = 'The provided credentials do not match our records.';
    } else if (err.code === 'ERR_NETWORK' || !err.response) {
      errorMessage.value = 'Unable to connect to server. Please verify your connection.';
    } else {
      errorMessage.value = err?.message || 'Login failed. Please verify credentials.';
    }
  } finally {
    loading.value = false;
  }
};
</script>
