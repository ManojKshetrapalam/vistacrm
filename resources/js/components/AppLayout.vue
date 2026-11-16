<template>
  <div class="min-h-screen bg-[#18031E] flex flex-col font-sans text-white relative overflow-x-hidden selection:bg-vb-magenta selection:text-white">
    <!-- Top Brand Spectrum Bar -->
    <div class="spectrum h-1 w-full fixed top-0 left-0 z-50"></div>

    <!-- Ambient Mesh Background Canvas -->
    <div class="ambient-canvas" aria-hidden="true">
      <div class="ambient-blob-1"></div>
      <div class="ambient-blob-2"></div>
      <div class="ambient-blob-3"></div>
      <div class="ambient-blob-4"></div>
    </div>

    <div class="flex-1 flex pt-1 relative z-10">
      <!-- Mobile Sidebar Overlay -->
      <div
        v-if="mobileSidebarOpen"
        class="fixed inset-0 z-50 bg-black/75 backdrop-blur-md lg:hidden transition-opacity"
        @click="mobileSidebarOpen = false"
      ></div>

      <!-- Glass Sidebar (Deep Dark Plum Frosted Glass) -->
      <aside
        class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] glass-sidebar flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0"
        :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <!-- Logo Area -->
        <div class="p-5 border-b border-white/10 flex items-center justify-between">
          <router-link to="/dashboard" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-2xl bg-white/10 backdrop-blur-md p-1.5 shadow-md border border-white/20 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <img
                :src="logoUrl"
                alt="Vistabound Logo"
                class="w-full h-full object-contain"
                @error="onLogoError"
              />
            </div>
            <div class="leading-tight">
              <span class="block font-display font-bold text-base tracking-tight text-white">VISTABOUND</span>
              <span class="block text-[10px] tracking-[0.18em] uppercase text-vb-cyan font-semibold">Solutions LLP</span>
            </div>
          </router-link>
          <button
            class="lg:hidden p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
            @click="mobileSidebarOpen = false"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Desk Scope Badge -->
        <div class="px-5 py-2.5 bg-black/30 border-b border-white/5 flex items-center justify-between">
          <div class="text-[11px] text-purple-200/60 uppercase tracking-wider font-semibold">Desk Scope:</div>
          <span
            class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border"
            :class="[
              authStore.isAdmin
                ? 'bg-gradient-to-r from-vb-magenta to-vb-violet text-white border-white/20 shadow-sm'
                : (authStore.isTeamLead
                    ? 'bg-vb-teal/20 text-teal-200 border-vb-teal/40'
                    : 'bg-white/10 text-white/90 border-white/15')
            ]"
          >
            {{ authStore.isAdmin ? 'Administrator' : (authStore.isTeamLead ? 'Team Lead' : 'Solutions Desk') }}
          </span>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
          <router-link
            to="/dashboard"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/dashboard') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <LayoutDashboard class="w-4 h-4" />
            <span>Dashboard</span>
          </router-link>

          <router-link
            to="/leads"
            class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/leads') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <div class="flex items-center gap-3">
              <Users class="w-4 h-4" />
              <span>Leads</span>
            </div>
          </router-link>

          <router-link
            to="/follow-ups"
            class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/follow-ups') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <div class="flex items-center gap-3">
              <CalendarClock class="w-4 h-4" />
              <span>Follow-ups</span>
            </div>
            <span
              v-if="dueFollowupsCount > 0"
              class="px-2 py-0.5 text-[10px] font-bold rounded-full"
              :class="isActive('/follow-ups') ? 'bg-white/25 text-white' : 'bg-vb-orange text-white shadow-sm'"
            >
              {{ dueFollowupsCount }}
            </span>
          </router-link>

          <!-- Admin Section -->
          <div v-if="authStore.isAdmin" class="pt-4 pb-1.5 px-3.5">
            <span class="text-[10px] font-bold uppercase tracking-widest text-purple-200/40">Administration</span>
          </div>

          <router-link
            v-if="authStore.isAdmin"
            to="/import"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/import') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <FileSpreadsheet class="w-4 h-4" />
            <span>Excel / CSV Ingestion</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/employees"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/employees') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <UserCheck class="w-4 h-4" />
            <span>Staff &amp; Access Controls</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/settings"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/settings') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <Settings class="w-4 h-4" />
            <span>CRM &amp; Templates</span>
          </router-link>

          <!-- System Section -->
          <div class="pt-4 pb-1.5 px-3.5">
            <span class="text-[10px] font-bold uppercase tracking-widest text-purple-200/40">System</span>
          </div>

          <router-link
            to="/notifications"
            class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/notifications') ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-lg shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/[0.06]'"
            @click="mobileSidebarOpen = false"
          >
            <div class="flex items-center gap-3">
              <BellRing class="w-4 h-4" />
              <span>Notifications</span>
            </div>
            <span
              v-if="unreadNotificationsCount > 0"
              class="w-2 h-2 rounded-full bg-vb-cyan shadow-sm shadow-vb-cyan"
            ></span>
          </router-link>

          <!-- Direct Sign Out Option in Scrollable Nav -->
          <div class="pt-4 pb-1.5 px-3.5 border-t border-white/10 mt-2">
            <span class="text-[10px] font-bold uppercase tracking-widest text-rose-300/60">Account Session</span>
          </div>
          <button
            type="button"
            @click="handleLogout"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-rose-300 hover:text-rose-200 hover:bg-rose-500/20 w-full text-left border border-rose-500/20"
          >
            <LogOut class="w-4 h-4 text-rose-400" />
            <span>Sign Out</span>
          </button>
        </nav>

        <!-- User Profile Pill in Sidebar Footer -->
        <div class="p-3.5 pb-8 lg:pb-3.5 border-t border-white/10 bg-black/45 space-y-2 shrink-0">
          <div class="p-2.5 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-md flex items-center justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white font-bold text-xs flex items-center justify-center shrink-0 border border-white/20 shadow-sm">
                {{ userInitials }}
              </div>
              <div class="min-w-0">
                <span class="block text-xs font-semibold text-white truncate">{{ authStore.user?.name }}</span>
                <span class="block text-[10px] text-purple-200/60 truncate">{{ authStore.user?.email }}</span>
              </div>
            </div>
            <button
              @click="handleLogout"
              class="p-1.5 text-purple-200/60 hover:text-rose-300 hover:bg-white/10 rounded-lg transition-colors shrink-0"
              title="Sign Out"
            >
              <LogOut class="w-4 h-4" />
            </button>
          </div>

          <!-- Prominent Full-Width Mobile Logout Button -->
          <button
            @click="handleLogout"
            class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white text-xs font-bold flex items-center justify-center gap-2 transition-all shadow-md shadow-rose-900/40 border border-rose-400/30 active:scale-98"
          >
            <LogOut class="w-4 h-4" />
            <span>Sign Out</span>
          </button>
        </div>
      </aside>

      <!-- Main Shell Area -->
      <div class="flex-1 flex flex-col min-w-0">
        <!-- Elevated Glass Header (Dark Luxury Frosted Header) -->
        <header class="glass-surface rounded-none border-t-0 border-x-0 border-b border-white/10 bg-[#1A0420]/80 backdrop-blur-xl px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between sticky top-1 z-30 shadow-md">
          <div class="flex items-center gap-3">
            <button
              class="lg:hidden p-2 text-purple-200 hover:bg-white/10 rounded-xl transition-colors"
              @click="mobileSidebarOpen = true"
            >
              <Menu class="w-5 h-5" />
            </button>
            <div class="hidden sm:block">
              <div class="text-[11px] font-semibold text-purple-200/60 uppercase tracking-wider flex items-center gap-1.5">
                <span>Vistabound CRM</span>
                <span>/</span>
                <span class="text-vb-magenta font-bold">{{ currentTitle }}</span>
              </div>
              <h1 class="text-lg sm:text-xl font-display font-bold text-white leading-tight">
                {{ currentTitle }}
              </h1>
            </div>
          </div>

          <!-- Center Search Pill (From Mockup) -->
          <div class="hidden md:flex items-center gap-2 px-4 py-2 rounded-full bg-white/[0.07] border border-white/10 text-xs text-purple-200/60 w-64 lg:w-80 focus-within:border-vb-magenta/50 focus-within:bg-white/[0.1] transition-all">
            <Search class="w-3.5 h-3.5 text-purple-300" />
            <input
              type="text"
              placeholder="Search leads, clients, tickets..."
              class="bg-transparent border-none outline-none text-xs text-white placeholder:text-purple-200/40 flex-1 font-sans"
            />
            <kbd class="px-1.5 py-0.5 rounded text-[10px] bg-white/10 text-purple-200/50 font-mono">/</kbd>
          </div>

          <div class="flex items-center gap-3">
            <!-- Sync Indicator Pill -->
            <button
              v-if="isOnline"
              @click="handleManualSync"
              :disabled="isSyncing"
              class="hidden sm:flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full border border-emerald-500/30 bg-emerald-500/10 backdrop-blur-sm text-emerald-300 hover:bg-emerald-500/20 transition-colors"
              title="Network Online — Click to force sync"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Online</span>
              <RefreshCw class="w-3 h-3 text-emerald-300 ml-0.5" :class="{'animate-spin': isSyncing}" />
            </button>
            <div
              v-else
              title="Offline Mode — Changes are queued and will synchronize automatically when online"
              class="flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-300"
            >
              <CloudOff class="w-3.5 h-3.5 text-amber-300" />
              <span>Offline{{ pendingCount > 0 ? ` (${pendingCount} queued)` : '' }}</span>
            </div>

            <!-- Quick Action "Add Lead" -->
            <router-link
              to="/leads/new"
              class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-1.5 shadow-md shadow-vb-magenta/20"
            >
              <Plus class="w-3.5 h-3.5" />
              <span class="hidden sm:inline">Add Lead</span>
            </router-link>

            <!-- Notifications Bell -->
            <router-link
              to="/notifications"
              class="relative p-2 text-purple-200 hover:text-white hover:bg-white/10 rounded-xl transition-all border border-transparent hover:border-white/10"
              title="Notifications"
            >
              <Bell class="w-5 h-5" />
              <span
                v-if="unreadNotificationsCount > 0"
                class="absolute top-1 right-1 w-2.5 h-2.5 bg-vb-magenta rounded-full ring-2 ring-[#18031E] animate-pulse"
              ></span>
            </router-link>

            <!-- Interactive User Dropdown in Topbar -->
            <div class="relative" ref="userDropdownRef">
              <button
                type="button"
                @click="onUserAvatarClick"
                class="flex items-center gap-2.5 pl-2 border-l border-white/10 focus:outline-none group cursor-pointer"
                aria-label="User Account Menu"
              >
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-vb-magenta to-vb-plum text-white font-bold text-xs flex items-center justify-center shrink-0 border border-white/20 shadow-sm group-hover:scale-105 transition-transform">
                  {{ userInitials }}
                </div>
                <div class="hidden xl:block text-left text-xs leading-tight">
                  <span class="font-bold text-white block">{{ authStore.user?.name }}</span>
                  <span class="text-[10px] text-purple-200/60 block">{{ authStore.isAdmin ? 'Administrator' : (authStore.isTeamLead ? 'Team Lead' : 'Client Advisor') }}</span>
                </div>
              </button>

              <!-- Dropdown Menu -->
              <div
                v-if="userMenuOpen"
                class="absolute right-0 mt-2 w-64 rounded-2xl bg-[#1D0324]/95 border border-white/15 backdrop-blur-2xl shadow-2xl p-3 z-50 space-y-3"
              >
                <div class="flex items-center gap-3 pb-2.5 border-b border-white/10">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white font-bold text-sm flex items-center justify-center shrink-0 border border-white/20 shadow-sm">
                    {{ userInitials }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-white truncate">{{ authStore.user?.name }}</p>
                    <p class="text-[11px] text-purple-200/60 truncate">{{ authStore.user?.email }}</p>
                    <span class="inline-block mt-1 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-vb-magenta/20 border border-vb-magenta/40 text-pink-200">
                      {{ authStore.isAdmin ? 'Administrator' : (authStore.isTeamLead ? 'Team Lead' : 'Solutions Desk') }}
                    </span>
                  </div>
                </div>

                <div class="space-y-1">
                  <router-link
                    v-if="authStore.isAdmin"
                    to="/settings"
                    @click="userMenuOpen = false"
                    class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
                  >
                    <Settings class="w-4 h-4 text-purple-300" />
                    <span>CRM Settings</span>
                  </router-link>
                  <button
                    type="button"
                    @click="handleLogout"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-rose-300 hover:text-rose-200 hover:bg-rose-500/15 border border-transparent hover:border-rose-500/30 transition-all text-left cursor-pointer"
                  >
                    <LogOut class="w-4 h-4 text-rose-400" />
                    <span>Sign Out</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </header>

        <!-- Dynamic Page View -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 pb-32 lg:pb-8">
          <slot />
        </main>
      </div>
    </div>

    <!-- Mobile Bottom Navigation Bar (Figma Design) -->
    <nav
      v-if="!mobileSidebarOpen && !mobileMoreSheetOpen"
      class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#15021B]/90 backdrop-blur-2xl border-t border-white/12 px-3 pt-2 pb-[calc(0.6rem+env(safe-area-inset-bottom,0px))] flex items-center justify-around shadow-2xl"
    >
      <router-link
        to="/dashboard"
        class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-2xl transition-all"
        :class="isActive('/dashboard') ? 'text-white bg-gradient-to-r from-vb-magenta/40 to-vb-plum/40 border border-white/15 shadow-sm' : 'text-purple-200/60 hover:text-white'"
      >
        <LayoutDashboard class="w-4 h-4" />
        <span class="text-[10px] font-semibold tracking-tight">Home</span>
      </router-link>

      <router-link
        to="/leads"
        class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-2xl transition-all"
        :class="isActive('/leads') ? 'text-white bg-gradient-to-r from-vb-magenta/40 to-vb-plum/40 border border-white/15 shadow-sm' : 'text-purple-200/60 hover:text-white'"
      >
        <Users class="w-4 h-4" />
        <span class="text-[10px] font-semibold tracking-tight">Leads</span>
      </router-link>

      <router-link
        to="/follow-ups"
        class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-2xl transition-all relative"
        :class="isActive('/follow-ups') ? 'text-white bg-gradient-to-r from-vb-magenta/40 to-vb-plum/40 border border-white/15 shadow-sm' : 'text-purple-200/60 hover:text-white'"
      >
        <div class="relative">
          <CalendarClock class="w-4 h-4" />
          <span
            v-if="dueFollowupsCount > 0"
            class="absolute -top-1 -right-2 px-1 py-0.2 text-[8px] font-bold rounded-full bg-vb-orange text-white"
          >
            {{ dueFollowupsCount }}
          </span>
        </div>
        <span class="text-[10px] font-semibold tracking-tight">Pipeline</span>
      </router-link>

      <router-link
        to="/notifications"
        class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-2xl transition-all relative"
        :class="isActive('/notifications') ? 'text-white bg-gradient-to-r from-vb-magenta/40 to-vb-plum/40 border border-white/15 shadow-sm' : 'text-purple-200/60 hover:text-white'"
      >
        <div class="relative">
          <Bell class="w-4 h-4" />
          <span
            v-if="unreadNotificationsCount > 0"
            class="absolute -top-1 -right-2 w-2 h-2 rounded-full bg-vb-magenta ring-1 ring-[#18031E]"
          ></span>
        </div>
        <span class="text-[10px] font-semibold tracking-tight">Alerts</span>
      </router-link>

      <button
        @click="mobileMoreSheetOpen = true"
        class="flex flex-col items-center gap-1 px-3 py-1.5 rounded-2xl transition-all text-purple-200/60 hover:text-white cursor-pointer"
      >
        <Menu class="w-4 h-4" />
        <span class="text-[10px] font-semibold tracking-tight">More</span>
      </button>
    </nav>

    <!-- Mobile More / Account Slide-Up Sheet -->
    <div
      v-if="mobileMoreSheetOpen"
      class="fixed inset-0 z-50 bg-black/75 backdrop-blur-md flex flex-col justify-end lg:hidden animate-in fade-in duration-200"
      @click.self="mobileMoreSheetOpen = false"
    >
      <div
        class="bg-[#1A0322] border-t border-white/20 rounded-t-3xl p-5 space-y-4 shadow-2xl animate-in slide-in-from-bottom duration-200 max-h-[85vh] overflow-y-auto"
      >
        <!-- Drag Handle / Dismiss bar -->
        <div class="flex justify-center -mt-2 mb-1">
          <div class="w-12 h-1.5 rounded-full bg-white/20"></div>
        </div>

        <!-- User Profile Header -->
        <div class="flex items-center justify-between pb-3 border-b border-white/10">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white font-bold text-base flex items-center justify-center shrink-0 border border-white/20 shadow-md">
              {{ userInitials }}
            </div>
            <div class="min-w-0">
              <span class="block text-sm font-bold text-white truncate">{{ authStore.user?.name }}</span>
              <span class="block text-xs text-purple-200/60 truncate">{{ authStore.user?.email }}</span>
              <span
                class="inline-block mt-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border"
                :class="authStore.isAdmin ? 'bg-vb-magenta/20 text-pink-200 border-vb-magenta/40' : (authStore.isTeamLead ? 'bg-teal-500/20 text-teal-300 border-teal-500/40' : 'bg-white/10 text-white/80 border-white/15')"
              >
                {{ authStore.isAdmin ? 'Administrator' : (authStore.isTeamLead ? 'Team Lead' : 'Solutions Desk') }}
              </span>
            </div>
          </div>
          <button
            @click="mobileMoreSheetOpen = false"
            class="p-2 text-purple-300 hover:text-white rounded-xl hover:bg-white/10 transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Navigation Links in More Sheet -->
        <div class="space-y-1">
          <router-link
            to="/dashboard"
            @click="mobileMoreSheetOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
          >
            <LayoutDashboard class="w-4 h-4 text-purple-300" />
            <span>Dashboard</span>
          </router-link>

          <router-link
            to="/leads"
            @click="mobileMoreSheetOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
          >
            <Users class="w-4 h-4 text-purple-300" />
            <span>Leads Pipeline</span>
          </router-link>

          <router-link
            to="/follow-ups"
            @click="mobileMoreSheetOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
          >
            <CalendarClock class="w-4 h-4 text-purple-300" />
            <span>Follow-ups</span>
          </router-link>

          <router-link
            to="/notifications"
            @click="mobileMoreSheetOpen = false"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
          >
            <BellRing class="w-4 h-4 text-purple-300" />
            <span>Notifications</span>
          </router-link>

          <!-- Admin-only links -->
          <template v-if="authStore.isAdmin">
            <div class="pt-2 pb-1 px-3.5">
              <span class="text-[10px] font-bold uppercase tracking-widest text-purple-200/40">Administration</span>
            </div>

            <router-link
              to="/employees"
              @click="mobileMoreSheetOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
            >
              <UserCheck class="w-4 h-4 text-purple-300" />
              <span>Staff &amp; Access Controls</span>
            </router-link>

            <router-link
              to="/import"
              @click="mobileMoreSheetOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
            >
              <FileSpreadsheet class="w-4 h-4 text-purple-300" />
              <span>Excel / CSV Ingestion</span>
            </router-link>

            <router-link
              to="/settings"
              @click="mobileMoreSheetOpen = false"
              class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-purple-200/80 hover:text-white hover:bg-white/10 transition-colors"
            >
              <Settings class="w-4 h-4 text-purple-300" />
              <span>CRM Settings &amp; Templates</span>
            </router-link>
          </template>
        </div>

        <!-- Prominent Logout Button -->
        <div class="pt-2 pb-3">
          <button
            type="button"
            @click="handleLogout"
            class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white font-bold text-sm flex items-center justify-center gap-2.5 shadow-xl shadow-rose-950/50 border border-rose-400/30 active:scale-98 transition-all cursor-pointer"
          >
            <LogOut class="w-4 h-4" />
            <span>Sign Out of CRM Desk</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Active 5-minute Reminder Alert Modal -->
    <ActiveReminderModal
      :is-open="activeReminderModalOpen"
      :follow-up="dueFollowUp"
      @close="activeReminderModalOpen = false"
      @complete="onReminderComplete"
      @reschedule="onReminderReschedule"
    />

    <!-- Login Follow-up Briefing Modal -->
    <LoginFollowUpModal
      :is-open="loginBriefingModalOpen"
      :follow-ups="pendingBriefingItems"
      @close="loginBriefingModalOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { isOnline, syncEngine } from '../services/syncEngine';
import api from '../api/axios';
import ActiveReminderModal from './ActiveReminderModal.vue';
import LoginFollowUpModal from './LoginFollowUpModal.vue';
import {
  LayoutDashboard,
  Users,
  CalendarClock,
  FileSpreadsheet,
  UserCheck,
  Settings,
  BellRing,
  LogOut,
  X,
  Menu,
  Plus,
  Bell,
  Search,
  RefreshCw,
  CloudOff,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const mobileSidebarOpen = ref(false);
const mobileMoreSheetOpen = ref(false);
const userMenuOpen = ref(false);
const userDropdownRef = ref<HTMLElement | null>(null);
const logoUrl = ref('/favicon.jpg');
const isSyncing = ref(false);
const pendingCount = ref(0);

const dueFollowupsCount = ref(0);
const unreadNotificationsCount = ref(0);

const activeReminderModalOpen = ref(false);
const dueFollowUp = ref<any>(null);

const loginBriefingModalOpen = ref(false);
const pendingBriefingItems = ref<any[]>([]);

let pollingInterval: any = null;

const currentTitle = computed(() => {
  const titles: Record<string, string> = {
    '/dashboard': 'Dashboard Overview',
    '/leads': 'Leads Pipeline',
    '/leads/new': 'Register New Lead',
    '/follow-ups': 'Daily Follow-ups',
    '/import': 'Batch Data Ingestion',
    '/employees': 'Team Administration',
    '/settings': 'System Settings',
    '/notifications': 'Notification Center',
  };
  return titles[route.path] || 'CRM Desk';
});

const userInitials = computed(() => {
  const name = authStore.user?.name || 'CRM';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
});

const isActive = (path: string) => {
  if (path === '/leads' && route.path.startsWith('/leads') && route.path !== '/leads/new') {
    return true;
  }
  return route.path === path;
};

const onLogoError = () => {
  if (logoUrl.value === '/favicon.jpg') {
    logoUrl.value = '/favicon.png';
  } else if (logoUrl.value === '/favicon.png') {
    logoUrl.value = '/images/vistabound-logo.png';
  }
};

const handleManualSync = async () => {
  if (isSyncing.value) return;
  isSyncing.value = true;
  try {
    await syncEngine.processOutbox();
    await fetchLivePollData();
  } finally {
    isSyncing.value = false;
  }
};

const onUserAvatarClick = () => {
  if (window.innerWidth < 1024) {
    mobileMoreSheetOpen.value = true;
  } else {
    userMenuOpen.value = !userMenuOpen.value;
  }
};

const handleLogout = async () => {
  mobileSidebarOpen.value = false;
  mobileMoreSheetOpen.value = false;
  userMenuOpen.value = false;
  await authStore.logout();
  router.push('/login');
};

const fetchLivePollData = async () => {
  if (!authStore.isAuthenticated) return;
  try {
    const res = await api.get('/notifications/poll');
    dueFollowupsCount.value = res.data.due_followups_count || 0;
    unreadNotificationsCount.value = res.data.unread_notifications_count || 0;
    pendingCount.value = await syncEngine.getQueueCount();

    if (res.data.active_reminder && !activeReminderModalOpen.value) {
      dueFollowUp.value = res.data.active_reminder;
      activeReminderModalOpen.value = true;
    }
  } catch (err) {
    // quiet polling fallback
  }
};

const onReminderComplete = () => {
  activeReminderModalOpen.value = false;
  fetchLivePollData();
};

const onReminderReschedule = () => {
  activeReminderModalOpen.value = false;
  fetchLivePollData();
};

const onWindowClick = (e: MouseEvent) => {
  if (userDropdownRef.value && !userDropdownRef.value.contains(e.target as Node)) {
    userMenuOpen.value = false;
  }
};

onMounted(async () => {
  window.addEventListener('click', onWindowClick);
  await fetchLivePollData();
  pollingInterval = setInterval(fetchLivePollData, 30000);
});

onUnmounted(() => {
  window.removeEventListener('click', onWindowClick);
  if (pollingInterval) clearInterval(pollingInterval);
});
</script>
