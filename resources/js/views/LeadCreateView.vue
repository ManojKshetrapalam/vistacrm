<template>
  <AppLayout>
    <div :class="intakeMode === 'bulk' ? 'max-w-5xl mx-auto space-y-6' : 'max-w-3xl mx-auto space-y-6'">
      <!-- Breadcrumb & Mode Switcher -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-purple-200/60">
          <router-link to="/leads" class="hover:text-white">Leads</router-link>
          <span>/</span>
          <span class="text-white font-semibold">{{ intakeMode === 'bulk' ? 'Bulk Import' : 'New Lead' }}</span>
        </div>

        <!-- Mode Switcher Pill -->
        <div class="flex items-center gap-2 p-1 rounded-full glass-surface border border-white/10 self-start sm:self-auto shadow-sm">
          <button
            type="button"
            @click="switchMode('single')"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-2"
            :class="intakeMode === 'single' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-plum-btn' : 'text-purple-200/70 hover:text-white hover:bg-white/10'"
          >
            <UserPlus class="w-3.5 h-3.5" />
            <span>Single Lead Intake</span>
          </button>
          <button
            type="button"
            @click="switchMode('bulk')"
            class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-2"
            :class="intakeMode === 'bulk' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-plum-btn' : 'text-purple-200/70 hover:text-white hover:bg-white/10'"
          >
            <FileSpreadsheet class="w-3.5 h-3.5" />
            <span>Bulk Excel / CSV Import</span>
          </button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- 1. SINGLE LEAD INTAKE MODE                 -->
      <!-- ========================================== -->
      <div v-if="intakeMode === 'single'" class="glass-surface p-6 sm:p-8 relative overflow-hidden shadow-2xl border border-white/10">
        <!-- Top Brand Accent -->
        <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>

        <div class="border-b border-white/10 pb-5 mb-6 pt-2">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30">
              Intake Desk
            </span>
          </div>
          <h2 class="text-2xl font-display font-bold text-white mt-2">Register New Lead Account</h2>
          <p class="text-xs text-purple-200/70 mt-1">
            Fill in client contact information. Phone number will be normalized for WhatsApp compatibility and duplicate checks.
          </p>
        </div>

        <!-- Duplicate Warning Alert -->
        <div
          v-if="duplicateLead"
          class="mb-6 p-4 rounded-xl bg-amber-500/15 border border-amber-500/40 text-amber-200 text-xs space-y-2 animate-in fade-in backdrop-blur-sm"
        >
          <div class="flex items-center gap-2 font-bold text-sm text-amber-300">
            <AlertTriangle class="w-4 h-4 text-amber-400" />
            <span>Duplicate Phone Detected in CRM</span>
          </div>
          <p>
            An existing lead <strong>{{ duplicateLead.name }}</strong> (Ref: <router-link :to="`/leads/${duplicateLead.id}`" class="underline font-bold text-vb-cyan hover:text-white">{{ duplicateLead.reference_id }}</router-link>) with company <em>{{ duplicateLead.company_name || 'N/A' }}</em> is already registered to representative <strong>{{ duplicateLead.assigned_to_name }}</strong>.
          </p>
          <label class="flex items-center gap-2 pt-1 font-semibold text-amber-200 cursor-pointer">
            <input
              type="checkbox"
              v-model="confirmDuplicate"
              class="rounded text-vb-magenta focus:ring-vb-magenta"
            />
            <span>I confirm this is a separate inquiry. Create record anyway.</span>
          </label>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Lead Name -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Lead / Contact Name <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                placeholder="e.g. Ramesh Hegde"
                class="field text-sm"
              />
            </div>

            <!-- Phone Number -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Mobile / WhatsApp Phone <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.phone"
                type="tel"
                required
                placeholder="e.g. +91 98450 12345"
                class="field text-sm"
              />
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Email -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Email Address
              </label>
              <input
                v-model="form.email"
                type="email"
                placeholder="client@organization.com"
                class="field text-sm"
              />
            </div>

            <!-- Company Name -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Company / Organization Name
              </label>
              <input
                v-model="form.company_name"
                type="text"
                placeholder="e.g. Acme Tech Solutions"
                class="field text-sm"
              />
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Lead Source -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Lead Source
              </label>
              <select v-model="form.source" class="field text-sm">
                <option v-for="src in leadSources" :key="src" :value="src">{{ src }}</option>
              </select>
            </div>

            <!-- Service Interest -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Service or Product Interest
              </label>
              <select v-model="form.service_interest" class="field text-sm">
                <option value="">Select Primary Interest...</option>
                <option v-for="cat in serviceCategories" :key="cat" :value="cat">{{ cat }}</option>
              </select>
            </div>
          </div>

          <div class="grid sm:grid-cols-3 gap-4">
            <!-- Status -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Initial Status
              </label>
              <select v-model="form.status" class="field text-sm">
                <option value="new">New</option>
                <option value="assigned">Assigned</option>
                <option value="contacted">Contacted</option>
                <option value="follow_up">Follow-up</option>
                <option value="interested">Interested</option>
              </select>
            </div>

            <!-- Priority -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Priority Level
              </label>
              <select v-model="form.priority" class="field text-sm">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>

            <!-- Assigned Representative (Admin & Team Lead) -->
            <div v-if="authStore.isAdmin || authStore.isTeamLead">
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                {{ authStore.isAdmin ? 'Assign Representative' : 'Assign Representative (Self or Team Member)' }}
              </label>
              <select v-model="form.assigned_to" class="field text-sm">
                <option value="">{{ authStore.isAdmin ? 'Leave Unassigned' : 'Assign to Myself' }}</option>
                <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                  {{ emp.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Next Follow-up Date (Optional) -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
              Schedule Initial Follow-up (Optional)
            </label>
            <input
              v-model="form.next_follow_up_date"
              type="datetime-local"
              class="field text-sm max-w-sm"
            />
          </div>

          <!-- Additional Details -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
              Inquiry Scope &amp; Background Details
            </label>
            <textarea
              v-model="form.additional_details"
              rows="4"
              class="field text-sm font-sans resize-none"
              placeholder="Client requirements, budget range, specific pain points, or notes from initial consultation..."
            ></textarea>
          </div>

          <div v-if="errorMsg" class="p-3.5 bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs rounded-xl backdrop-blur-sm">
            {{ errorMsg }}
          </div>

          <!-- Form Buttons -->
          <div class="pt-5 border-t border-white/10 flex items-center justify-end gap-3">
            <router-link
              to="/leads"
              class="btn-ghost px-5 py-2.5 rounded-full text-xs font-semibold"
            >
              Cancel
            </router-link>
            <button
              type="submit"
              :disabled="submitting"
              class="btn-primary text-xs font-semibold px-6 py-2.5 rounded-full flex items-center gap-2 shadow-md shadow-vb-magenta/30"
            >
              <Check class="w-4 h-4" />
              <span>{{ submitting ? 'Saving...' : 'Create Lead Record' }}</span>
            </button>
          </div>
        </form>
      </div>

      <!-- ========================================== -->
      <!-- 2. BULK EXCEL / CSV IMPORT WIZARD          -->
      <!-- ========================================== -->
      <div v-else class="space-y-6">
        <!-- Top Step Indicator Bar -->
        <div class="glass-surface p-4 border border-white/10 shadow-lg">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
            <div
              class="p-2.5 rounded-xl transition-all"
              :class="bulkStep === 1 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : bulkStep > 1 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
            >
              <span class="block text-[10px] uppercase tracking-wider">Step 1</span>
              <span>Upload &amp; Map</span>
            </div>

            <div
              class="p-2.5 rounded-xl transition-all"
              :class="bulkStep === 2 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : bulkStep > 2 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
            >
              <span class="block text-[10px] uppercase tracking-wider">Step 2</span>
              <span>Duplicates &amp; Clean Leads</span>
            </div>

            <div
              class="p-2.5 rounded-xl transition-all"
              :class="bulkStep === 3 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : bulkStep > 3 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
            >
              <span class="block text-[10px] uppercase tracking-wider">Step 3</span>
              <span>Round-Robin Assign</span>
            </div>

            <div
              class="p-2.5 rounded-xl transition-all"
              :class="bulkStep === 4 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
            >
              <span class="block text-[10px] uppercase tracking-wider">Step 4</span>
              <span>Import Summary</span>
            </div>
          </div>
        </div>

        <!-- ---------------------------------------- -->
        <!-- STEP 1: Upload File & Column Mapping      -->
        <!-- ---------------------------------------- -->
        <div v-if="bulkStep === 1" class="glass-surface p-6 sm:p-8 space-y-6 relative overflow-hidden border border-white/10 shadow-2xl">
          <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/10 pb-5 pt-2">
            <div>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30">
                  Bulk Ingestion
                </span>
              </div>
              <h3 class="text-2xl font-display font-bold text-white mt-1">Upload Spreadsheet &amp; Confirm Mapping</h3>
              <p class="text-xs text-purple-200/70 mt-1">
                Upload an Excel or CSV file. We will automatically detect columns and scan for duplicate phone numbers.
              </p>
            </div>
            <button
              type="button"
              @click="downloadSampleCsv"
              class="glass-surface border border-white/10 px-4 py-2 rounded-full text-xs font-semibold text-purple-200 hover:text-white hover:bg-white/10 flex items-center gap-2 self-start transition-all shadow-sm"
            >
              <Download class="w-3.5 h-3.5 text-vb-magenta" />
              <span>Download Sample Template</span>
            </button>
          </div>

          <!-- Drag and Drop Dropzone -->
          <div
            v-if="!fileToken"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="handleDrop"
            class="border-2 border-dashed rounded-3xl p-10 text-center transition-all cursor-pointer"
            :class="dragOver ? 'border-vb-magenta bg-vb-magenta/15' : 'border-white/20 hover:border-vb-magenta/60 bg-white/[0.02]'"
            @click="fileInput?.click()"
          >
            <input
              ref="fileInput"
              type="file"
              accept=".csv, .xlsx, .xls"
              class="hidden"
              @change="handleFileSelect"
            />
            <div class="w-16 h-16 rounded-2xl bg-white/10 text-white mx-auto flex items-center justify-center mb-4 shadow-sm border border-white/15">
              <UploadCloud class="w-8 h-8 text-vb-magenta" />
            </div>
            <h4 class="font-display font-bold text-lg text-white">Select or Drag &amp; Drop Spreadsheet</h4>
            <p class="text-xs text-purple-200/70 mt-1 max-w-sm mx-auto">
              Supported formats: <strong class="text-white">.xlsx</strong>, <strong class="text-white">.xls</strong>, or <strong class="text-white">.csv</strong> up to 10 MB.
            </p>
            <div class="mt-5 inline-flex items-center gap-2 btn-primary px-6 py-2.5 rounded-full text-xs font-semibold shadow-plum-btn">
              <FileSpreadsheet class="w-4 h-4" />
              <span>Choose File From Device</span>
            </div>
          </div>

          <!-- Upload In Progress -->
          <div v-if="uploadingFile" class="py-8 text-center space-y-2">
            <RefreshCw class="w-8 h-8 text-vb-magenta animate-spin mx-auto" />
            <p class="text-sm font-semibold text-white">Parsing and analyzing spreadsheet structure...</p>
          </div>

          <!-- Column Mapping & Preview (Shown after file uploaded) -->
          <div v-if="fileToken && !uploadingFile" class="space-y-6">
            <div class="flex items-center justify-between p-4 rounded-2xl bg-white/[0.04] border border-white/10">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-vb-magenta/20 border border-vb-magenta/30 flex items-center justify-center text-vb-magenta font-bold">
                  <FileSpreadsheet class="w-5 h-5" />
                </div>
                <div>
                  <h4 class="font-semibold text-white text-sm">{{ uploadedFileName }}</h4>
                  <p class="text-xs text-purple-200/70">{{ totalRowsFound }} total rows identified in sheet</p>
                </div>
              </div>
              <button
                type="button"
                @click="resetFileInput"
                class="text-xs text-purple-300 hover:text-white underline cursor-pointer"
              >
                Change File
              </button>
            </div>

            <!-- Mapping Grid -->
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-purple-200 mb-3">Confirm Field Columns</h4>
              <div class="grid sm:grid-cols-2 gap-4 text-xs">
                <!-- Name (Required) -->
                <div class="p-4 rounded-xl border space-y-1.5 shadow-sm" :class="mapping.name ? 'border-white/15 bg-white/[0.04]' : 'border-rose-500/40 bg-rose-500/10'">
                  <div class="flex items-center justify-between">
                    <label class="font-bold text-purple-200 uppercase tracking-wider text-[11px]">
                      Lead / Contact Name <span class="text-rose-400">*</span>
                    </label>
                    <span class="text-[10px] text-rose-400 font-semibold">Mandatory</span>
                  </div>
                  <select v-model="mapping.name" class="field text-xs">
                    <option value="">-- Choose Column --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>

                <!-- Phone (Required) -->
                <div class="p-4 rounded-xl border space-y-1.5 shadow-sm" :class="mapping.phone ? 'border-white/15 bg-white/[0.04]' : 'border-rose-500/40 bg-rose-500/10'">
                  <div class="flex items-center justify-between">
                    <label class="font-bold text-purple-200 uppercase tracking-wider text-[11px]">
                      Phone Number <span class="text-rose-400">*</span>
                    </label>
                    <span class="text-[10px] text-rose-400 font-semibold">Mandatory</span>
                  </div>
                  <select v-model="mapping.phone" class="field text-xs">
                    <option value="">-- Choose Column --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>

                <!-- Email -->
                <div class="p-4 rounded-xl border border-white/10 bg-white/[0.04] space-y-1.5 shadow-sm">
                  <label class="font-semibold text-purple-200 uppercase tracking-wider text-[11px]">Email Address</label>
                  <select v-model="mapping.email" class="field text-xs">
                    <option value="">-- Optional / Skip --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>

                <!-- Company Name -->
                <div class="p-4 rounded-xl border border-white/10 bg-white/[0.04] space-y-1.5 shadow-sm">
                  <label class="font-semibold text-purple-200 uppercase tracking-wider text-[11px]">Company / Organization</label>
                  <select v-model="mapping.company_name" class="field text-xs">
                    <option value="">-- Optional / Skip --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>

                <!-- Lead Source -->
                <div class="p-4 rounded-xl border border-white/10 bg-white/[0.04] space-y-1.5 shadow-sm">
                  <label class="font-semibold text-purple-200 uppercase tracking-wider text-[11px]">Lead Source</label>
                  <select v-model="mapping.source" class="field text-xs">
                    <option value="">-- Optional / Default to "Import" --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>

                <!-- Service Interest -->
                <div class="p-4 rounded-xl border border-white/10 bg-white/[0.04] space-y-1.5 shadow-sm">
                  <label class="font-semibold text-purple-200 uppercase tracking-wider text-[11px]">Service Interest</label>
                  <select v-model="mapping.service_interest" class="field text-xs">
                    <option value="">-- Optional / Skip --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>

                <!-- Additional Details -->
                <div class="p-4 rounded-xl border border-white/10 bg-white/[0.04] space-y-1.5 sm:col-span-2 shadow-sm">
                  <label class="font-semibold text-purple-200 uppercase tracking-wider text-[11px]">Additional Notes / Scope</label>
                  <select v-model="mapping.additional_details" class="field text-xs">
                    <option value="">-- Optional / Skip --</option>
                    <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Preview 3-5 Rows Table -->
            <div v-if="samplePreview && samplePreview.length > 0" class="space-y-2">
              <h4 class="text-xs font-bold uppercase tracking-wider text-purple-200">First {{ samplePreview.length }} Rows Sample</h4>
              <div class="overflow-x-auto border border-white/10 rounded-2xl bg-white/[0.02]">
                <table class="w-full text-left text-xs">
                  <thead class="bg-white/[0.05] border-b border-white/10 text-purple-200 font-semibold">
                    <tr>
                      <th class="py-2 px-3">Name</th>
                      <th class="py-2 px-3">Phone</th>
                      <th class="py-2 px-3">Email</th>
                      <th class="py-2 px-3">Company</th>
                      <th class="py-2 px-3">Service</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-white/5">
                    <tr v-for="(row, idx) in samplePreview" :key="idx" class="hover:bg-white/[0.04]">
                      <td class="py-2 px-3 font-semibold text-white">{{ row[mapping.name] || '—' }}</td>
                      <td class="py-2 px-3 font-mono text-purple-200">{{ row[mapping.phone] || '—' }}</td>
                      <td class="py-2 px-3 text-purple-200/70">{{ mapping.email ? row[mapping.email] : '—' }}</td>
                      <td class="py-2 px-3 text-white">{{ mapping.company_name ? row[mapping.company_name] : '—' }}</td>
                      <td class="py-2 px-3 text-purple-200/70">{{ mapping.service_interest ? row[mapping.service_interest] : '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Upload / Validation Error -->
            <div v-if="bulkError" class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs rounded-xl backdrop-blur-sm">
              {{ bulkError }}
            </div>

            <!-- Step 1 Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-white/10">
              <button
                type="button"
                @click="resetFileInput"
                class="text-xs font-semibold text-purple-300 hover:text-white transition-colors"
              >
                ← Clear File
              </button>
              <button
                type="button"
                @click="analyzeDuplicates"
                :disabled="!mapping.name || !mapping.phone || analyzingDuplicates"
                class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-plum-btn disabled:opacity-50"
              >
                <RefreshCw v-if="analyzingDuplicates" class="w-4 h-4 animate-spin" />
                <span>{{ analyzingDuplicates ? 'Scanning Duplicates...' : 'Scan File & Check Duplicates →' }}</span>
                <ArrowRight v-if="!analyzingDuplicates" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- ---------------------------------------- -->
        <!-- STEP 2: Duplicate Check & Ignored Leads   -->
        <!-- ---------------------------------------- -->
        <div v-if="bulkStep === 2" class="glass-surface p-6 sm:p-8 space-y-6 relative overflow-hidden border border-white/10 shadow-2xl">
          <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>

          <div class="border-b border-white/10 pb-5 pt-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30">
                Integrity Pre-Scan
              </span>
            </div>
            <h3 class="text-2xl font-display font-bold text-white mt-1">Duplicate Leads Analysis &amp; Skip Review</h3>
            <p class="text-xs text-purple-200/70 mt-1">
              We cross-referenced all normalized phone numbers against your active CRM records and rows within the file.
            </p>
          </div>

          <!-- KPI Summary Banner -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-4 rounded-2xl glass-surface border border-white/10 text-center shadow-sm">
              <span class="text-xs text-purple-200/70 uppercase tracking-wider block font-semibold">Total Records</span>
              <span class="text-2xl font-bold text-white font-display mt-1 block">{{ duplicateAnalysis?.total_rows || 0 }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-center shadow-sm">
              <span class="text-xs text-amber-300 uppercase tracking-wider block font-semibold">Duplicates (Ignored)</span>
              <span class="text-2xl font-bold text-amber-200 font-display mt-1 block">{{ duplicateAnalysis?.duplicate_count || 0 }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-center shadow-sm">
              <span class="text-xs text-emerald-300 uppercase tracking-wider block font-semibold">Clean Unique Leads</span>
              <span class="text-2xl font-bold text-emerald-200 font-display mt-1 block">{{ duplicateAnalysis?.clean_count || 0 }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 text-center shadow-sm">
              <span class="text-xs text-purple-200/70 uppercase tracking-wider block font-semibold">Invalid / Incomplete</span>
              <span class="text-2xl font-bold text-white font-display mt-1 block">{{ (duplicateAnalysis?.invalid_count || 0) }}</span>
            </div>
          </div>

          <!-- Ignored Duplicates Table -->
          <div v-if="duplicateAnalysis?.duplicates && duplicateAnalysis.duplicates.length > 0" class="space-y-3">
            <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
              <AlertTriangle class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" />
              <div class="text-xs text-amber-200/90 leading-relaxed">
                <strong class="text-amber-300">Duplicate Protection Active:</strong>
                {{ duplicateAnalysis.duplicates.length }} duplicate lead(s) detected. These records <strong>will be completely ignored and skipped</strong> during import to prevent overwriting existing clients or duplicate assignments.
              </div>
            </div>

            <div class="overflow-x-auto border border-white/10 rounded-2xl bg-white/[0.02] max-h-64 overflow-y-auto shadow-sm">
              <table class="w-full text-left text-xs">
                <thead class="bg-white/[0.05] border-b border-white/10 text-purple-200 font-semibold sticky top-0 backdrop-blur-md">
                  <tr>
                    <th class="py-2.5 px-3">Row #</th>
                    <th class="py-2.5 px-3">Contact Name</th>
                    <th class="py-2.5 px-3">Phone</th>
                    <th class="py-2.5 px-3">Conflict Reason</th>
                    <th class="py-2.5 px-3">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                  <tr v-for="(dupe, dIdx) in duplicateAnalysis.duplicates" :key="dIdx" class="hover:bg-amber-500/10 transition-colors">
                    <td class="py-2 px-3 font-mono font-bold text-purple-300">Row {{ dupe.row }}</td>
                    <td class="py-2 px-3 font-semibold text-white">{{ dupe.name || '—' }}</td>
                    <td class="py-2 px-3 font-mono text-purple-200">{{ dupe.phone }}</td>
                    <td class="py-2 px-3 text-amber-200/90">
                      {{ dupe.reason }}
                    </td>
                    <td class="py-2 px-3">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        Ignored / Skipped
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Zero Duplicates Banner -->
          <div v-else class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 flex items-center gap-3">
            <CheckCircle2 class="w-5 h-5 text-emerald-300 flex-shrink-0" />
            <div class="text-xs text-emerald-200">
              <strong class="text-white">Zero Duplicates Detected:</strong> All {{ duplicateAnalysis?.clean_count || 0 }} rows are unique and verified ready for round-robin assignment.
            </div>
          </div>

          <!-- Sample Clean Leads -->
          <div v-if="duplicateAnalysis?.sample_clean_leads && duplicateAnalysis.sample_clean_leads.length > 0" class="space-y-2">
            <h4 class="text-xs font-bold uppercase tracking-wider text-purple-200">Clean Leads Preview (First {{ duplicateAnalysis.sample_clean_leads.length }} to be imported)</h4>
            <div class="overflow-x-auto border border-white/10 rounded-2xl bg-white/[0.02]">
              <table class="w-full text-left text-xs">
                <thead class="bg-white/[0.05] border-b border-white/10 text-purple-200 font-semibold">
                  <tr>
                    <th class="py-2 px-3">Row</th>
                    <th class="py-2 px-3">Name</th>
                    <th class="py-2 px-3">Phone</th>
                    <th class="py-2 px-3">Company</th>
                    <th class="py-2 px-3">Service Interest</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                  <tr v-for="(clean, cIdx) in duplicateAnalysis.sample_clean_leads" :key="cIdx" class="hover:bg-white/[0.04]">
                    <td class="py-2 px-3 font-mono text-purple-300">Row {{ clean.row }}</td>
                    <td class="py-2 px-3 font-semibold text-white">{{ clean.name }}</td>
                    <td class="py-2 px-3 font-mono text-emerald-300">{{ clean.phone }}</td>
                    <td class="py-2 px-3 text-purple-200/80">{{ clean.company_name || '—' }}</td>
                    <td class="py-2 px-3 text-purple-200/70">{{ clean.service_interest || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Step 2 Actions -->
          <div class="flex items-center justify-between pt-4 border-t border-white/10">
            <button
              type="button"
              @click="bulkStep = 1"
              class="text-xs font-semibold text-purple-300 hover:text-white transition-colors"
            >
              ← Back to Mapping
            </button>
            <button
              type="button"
              @click="proceedToAssignment"
              :disabled="!duplicateAnalysis || duplicateAnalysis.clean_count === 0"
              class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-plum-btn disabled:opacity-50"
            >
              <span>Continue to User Assignment ({{ duplicateAnalysis?.clean_count || 0 }} Clean Leads)</span>
              <ArrowRight class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- ---------------------------------------- -->
        <!-- STEP 3: User Selection & Round-Robin Live -->
        <!-- ---------------------------------------- -->
        <div v-if="bulkStep === 3" class="glass-surface p-6 sm:p-8 space-y-6 relative overflow-hidden border border-white/10 shadow-2xl">
          <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>

          <div class="border-b border-white/10 pb-5 pt-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30">
                Workload Distribution
              </span>
            </div>
            <h3 class="text-2xl font-display font-bold text-white mt-1">Select Users for Round-Robin Assignment</h3>
            <p class="text-xs text-purple-200/70 mt-1">
              Choose the representatives who should receive the <strong>{{ duplicateAnalysis?.clean_count || 0 }} clean leads</strong>. Leads rotate sequentially 1-by-1 in an even round-robin distribution.
            </p>
          </div>

          <!-- Quick Actions & Unassigned Checkbox -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-white/[0.03] border border-white/10">
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="selectAllAssignees"
                :disabled="leaveUnassigned"
                class="px-3 py-1.5 rounded-full text-xs font-semibold glass-surface border border-white/10 text-purple-200 hover:text-white hover:bg-white/10 transition-all disabled:opacity-40"
              >
                Select All
              </button>
              <button
                type="button"
                @click="deselectAllAssignees"
                :disabled="leaveUnassigned"
                class="px-3 py-1.5 rounded-full text-xs font-semibold glass-surface border border-white/10 text-purple-200 hover:text-white hover:bg-white/10 transition-all disabled:opacity-40"
              >
                Deselect All
              </button>
              <span class="text-xs text-purple-200/60 ml-2">
                {{ selectedAssigneeIds.length }} user(s) selected
              </span>
            </div>

            <label class="flex items-center gap-2 text-xs font-semibold text-purple-200 cursor-pointer">
              <input
                type="checkbox"
                v-model="leaveUnassigned"
                @change="handleLeaveUnassignedToggle"
                class="rounded text-vb-magenta focus:ring-vb-magenta bg-white/5 border-white/20"
              />
              <span>Leave leads unassigned (Inbound Pool)</span>
            </label>
          </div>

          <!-- Assignable Users Grid -->
          <div v-if="!leaveUnassigned" class="space-y-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-purple-200 font-display">
              Available Representatives
            </h4>

            <div v-if="loadingUsers" class="py-8 text-center text-xs text-purple-200/70">
              <RefreshCw class="w-6 h-6 animate-spin text-vb-magenta mx-auto mb-2" />
              Loading eligible team members...
            </div>

            <div v-else-if="assignableUsers.length === 0" class="p-6 rounded-2xl bg-white/[0.03] border border-white/10 text-center text-xs text-purple-200/60">
              No assignable team members found within your supervisory scope.
            </div>

            <div v-else class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
              <div
                v-for="user in assignableUsers"
                :key="user.id"
                @click="toggleUserSelection(user.id)"
                class="p-3.5 rounded-2xl border transition-all cursor-pointer flex items-start gap-3 select-none"
                :class="selectedAssigneeIds.includes(user.id) ? 'border-vb-magenta/80 bg-vb-magenta/15 shadow-md shadow-vb-magenta/20' : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'"
              >
                <input
                  type="checkbox"
                  :checked="selectedAssigneeIds.includes(user.id)"
                  class="rounded text-vb-magenta focus:ring-vb-magenta bg-white/5 border-white/30 mt-1 pointer-events-none"
                />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span class="font-bold text-white text-xs truncate">{{ user.name }}</span>
                    <span
                      class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full"
                      :class="user.role === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : (user.role === 'team_lead' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-vb-violet/20 text-purple-200 border border-vb-violet/30')"
                    >
                      {{ user.role === 'admin' ? 'Admin' : (user.role === 'team_lead' ? 'Lead' : 'Employee') }}
                    </span>
                  </div>
                  <p class="text-[11px] text-purple-200/60 truncate mt-0.5">{{ user.email }}</p>
                  <div class="flex items-center gap-1 mt-2 text-[10px] text-purple-200/70">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>{{ user.active_leads_count ?? 0 }} active leads</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Dynamic Live Round-Robin Calculator Box -->
          <div
            v-if="!leaveUnassigned && selectedAssigneeIds.length > 0"
            class="p-5 rounded-2xl bg-gradient-to-br from-vb-magenta/15 via-white/[0.04] to-vb-plum/20 border border-vb-magenta/30 shadow-lg space-y-3"
          >
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-vb-magenta font-display">
              <Sparkles class="w-4 h-4 text-vb-magenta" />
              <span>Live Round-Robin Distribution Preview</span>
            </div>

            <p class="text-xs text-purple-200/90 leading-relaxed">
              <strong>{{ duplicateAnalysis?.clean_count || 0 }} clean leads</strong> will rotate sequentially across the <strong>{{ selectedAssigneeIds.length }} selected representative(s)</strong>:
            </p>

            <!-- User quota chips -->
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2 pt-1">
              <div
                v-for="calc in roundRobinDistribution"
                :key="calc.id"
                class="p-2.5 rounded-xl bg-black/30 border border-white/10 flex items-center justify-between text-xs"
              >
                <div class="truncate mr-2">
                  <span class="font-semibold text-white block truncate">{{ calc.name }}</span>
                  <span class="text-[10px] text-purple-200/60 uppercase">{{ calc.role }}</span>
                </div>
                <div class="text-right">
                  <span class="font-mono font-bold text-sm text-emerald-300">~{{ calc.assignedCount }}</span>
                  <span class="text-[10px] text-purple-200/60 block">leads</span>
                </div>
              </div>
            </div>

            <p class="text-[11px] text-purple-200/70 italic pt-1">
              * Sequence: Lead 1 &rarr; {{ roundRobinDistribution[0]?.name }}, Lead 2 &rarr; {{ roundRobinDistribution[1]?.name || roundRobinDistribution[0]?.name }}... repeating sequentially.
            </p>
          </div>

          <!-- Unassigned Notice Box -->
          <div
            v-else-if="leaveUnassigned"
            class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center gap-3 text-xs text-purple-200"
          >
            <Info class="w-5 h-5 text-purple-300 flex-shrink-0" />
            <span>All <strong>{{ duplicateAnalysis?.clean_count || 0 }} clean leads</strong> will be ingested with status <code>unassigned</code> into the inbound pool. Any representative can claim them later.</span>
          </div>

          <div v-else class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
            <AlertTriangle class="w-4 h-4 flex-shrink-0" />
            <span>Please select at least one representative, or check "Leave leads unassigned" to proceed.</span>
          </div>

          <!-- Process Error -->
          <div v-if="bulkError" class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs rounded-xl backdrop-blur-sm">
            {{ bulkError }}
          </div>

          <!-- Step 3 Actions -->
          <div class="flex items-center justify-between pt-4 border-t border-white/10">
            <button
              type="button"
              @click="bulkStep = 2"
              class="text-xs font-semibold text-purple-300 hover:text-white transition-colors"
            >
              ← Back to Duplicate Review
            </button>
            <button
              type="button"
              @click="executeBatchImport"
              :disabled="executingImport || (!leaveUnassigned && selectedAssigneeIds.length === 0)"
              class="btn-primary px-7 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-plum-btn disabled:opacity-50"
            >
              <RefreshCw v-if="executingImport" class="w-4 h-4 animate-spin" />
              <span>{{ executingImport ? 'Importing & Assigning...' : `Execute Round-Robin Import (${duplicateAnalysis?.clean_count || 0} Leads) 🚀` }}</span>
            </button>
          </div>
        </div>

        <!-- ---------------------------------------- -->
        <!-- STEP 4: Import Summary & Final Report     -->
        <!-- ---------------------------------------- -->
        <div v-if="bulkStep === 4 && importReport" class="glass-surface p-6 sm:p-8 space-y-6 relative overflow-hidden border border-white/10 shadow-2xl">
          <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>

          <div class="flex items-center gap-3 border-b border-white/10 pb-5 pt-2">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center justify-center shadow-lg">
              <CheckCircle2 class="w-7 h-7" />
            </div>
            <div>
              <h3 class="font-display font-bold text-2xl text-white">Bulk Lead Import Complete</h3>
              <p class="text-xs text-purple-200/70 mt-0.5">Summary execution audit for file: <strong class="text-white">{{ uploadedFileName }}</strong></p>
            </div>
          </div>

          <!-- Metric Counters -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-4 rounded-2xl glass-surface border border-white/10 text-center shadow-sm">
              <span class="text-xs text-purple-200/70 uppercase tracking-wider block font-semibold">Total Rows</span>
              <span class="text-2xl font-bold text-white font-display mt-1 block">{{ importReport.total_rows }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-center shadow-sm">
              <span class="text-xs text-emerald-300 uppercase tracking-wider block font-semibold">Clean Imported</span>
              <span class="text-2xl font-bold text-emerald-200 font-display mt-1 block">{{ importReport.imported_count }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-center shadow-sm">
              <span class="text-xs text-amber-300 uppercase tracking-wider block font-semibold">Duplicates Ignored</span>
              <span class="text-2xl font-bold text-amber-200 font-display mt-1 block">{{ importReport.duplicate_count }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 text-center shadow-sm">
              <span class="text-xs text-purple-200/70 uppercase tracking-wider block font-semibold">Invalid / Skipped</span>
              <span class="text-2xl font-bold text-white font-display mt-1 block">{{ (importReport.invalid_count || 0) + (importReport.skipped_count || 0) }}</span>
            </div>
          </div>

          <!-- Assignment Breakdown Card -->
          <div v-if="importReport.assignment_breakdown && importReport.assignment_breakdown.length > 0" class="p-5 rounded-2xl glass-surface border border-white/10 space-y-3">
            <h4 class="font-display font-bold text-sm text-white flex items-center gap-2">
              <Users class="w-4 h-4 text-vb-magenta" />
              <span>Round-Robin Assignment Breakdown</span>
            </h4>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2.5">
              <div
                v-for="item in importReport.assignment_breakdown"
                :key="item.user_id"
                class="p-3 rounded-xl bg-white/[0.03] border border-white/10 flex items-center justify-between"
              >
                <div>
                  <span class="font-semibold text-white text-xs block">{{ item.name }}</span>
                  <span class="text-[10px] text-purple-200/60 uppercase">{{ item.role }}</span>
                </div>
                <div class="text-right">
                  <span class="font-mono font-bold text-emerald-300 text-sm">{{ item.count }}</span>
                  <span class="text-[10px] text-purple-200/60 block">leads assigned</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Failed Rows Table / Download -->
          <div v-if="importReport.failed_rows && importReport.failed_rows.length > 0" class="space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="font-display font-bold text-xs uppercase tracking-wider text-rose-300">Validation Failures</h4>
              <button
                type="button"
                @click="downloadReportCsv(importReport.failed_rows)"
                class="text-xs font-semibold text-purple-300 hover:text-white flex items-center gap-1 transition-colors"
              >
                <Download class="w-3.5 h-3.5" />
                <span>Download Failures (.csv)</span>
              </button>
            </div>

            <div class="overflow-x-auto border border-white/10 rounded-2xl bg-white/[0.02] max-h-48 overflow-y-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-white/[0.05] border-b border-white/10 text-purple-200 font-semibold sticky top-0">
                  <tr>
                    <th class="py-2 px-3">Row #</th>
                    <th class="py-2 px-3">Name</th>
                    <th class="py-2 px-3">Phone</th>
                    <th class="py-2 px-3">Reason</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                  <tr v-for="(fRow, fIdx) in importReport.failed_rows" :key="fIdx" class="hover:bg-rose-500/10">
                    <td class="py-1.5 px-3 font-mono font-bold text-purple-300">Row {{ fRow.row }}</td>
                    <td class="py-1.5 px-3 text-white">{{ fRow.name || '—' }}</td>
                    <td class="py-1.5 px-3 font-mono text-purple-200">{{ fRow.phone || '—' }}</td>
                    <td class="py-1.5 px-3 text-rose-300">{{ fRow.reason }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Step 4 Actions -->
          <div class="pt-5 border-t border-white/10 flex flex-wrap items-center justify-between gap-3">
            <button
              type="button"
              @click="resetBulkWizard"
              class="glass-surface border border-white/10 px-5 py-2.5 rounded-full text-xs font-semibold text-purple-200 hover:text-white hover:bg-white/10 transition-all"
            >
              Import Another File
            </button>

            <div class="flex items-center gap-3">
              <button
                type="button"
                @click="switchMode('single')"
                class="glass-surface border border-white/10 px-5 py-2.5 rounded-full text-xs font-semibold text-purple-200 hover:text-white hover:bg-white/10 transition-all"
              >
                Register Single Lead
              </button>
              <router-link
                to="/leads"
                class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-plum-btn"
              >
                <span>View Leads Pipeline</span>
                <ArrowRight class="w-4 h-4" />
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import { syncEngine, isOnline } from '../services/syncEngine';
import AppLayout from '../components/AppLayout.vue';
import {
  AlertTriangle,
  Check,
  UserPlus,
  FileSpreadsheet,
  UploadCloud,
  Download,
  ArrowRight,
  CheckCircle2,
  RefreshCw,
  Users,
  Sparkles,
  Info,
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

// -------------------------------------------------------------
// INTAKE MODE
// -------------------------------------------------------------
const intakeMode = ref<'single' | 'bulk'>('single');

const switchMode = (mode: 'single' | 'bulk') => {
  intakeMode.value = mode;
  router.replace({ query: { ...route.query, mode } });
};

// -------------------------------------------------------------
// SINGLE LEAD INTAKE STATE
// -------------------------------------------------------------
const leadSources = ref<string[]>(['Website', 'WhatsApp Inbound', 'LinkedIn', 'Referral', 'Cold Outreach']);
const serviceCategories = ref<string[]>([]);
const employeesList = ref<any[]>([]);

const submitting = ref(false);
const errorMsg = ref('');
const duplicateLead = ref<any>(null);
const confirmDuplicate = ref(false);

const form = reactive({
  name: '',
  phone: '',
  email: '',
  company_name: '',
  source: 'Website',
  service_interest: '',
  status: 'new',
  priority: 'medium',
  assigned_to: '',
  next_follow_up_date: '',
  additional_details: '',
});

const fetchSettings = async () => {
  try {
    const res = await api.get('/settings');
    leadSources.value = res.data.lead_sources || [];
    serviceCategories.value = res.data.service_categories || [];
    employeesList.value = res.data.employees || [];
  } catch (err) {
    // quiet
  }
};

const handleSubmit = async () => {
  submitting.value = true;
  errorMsg.value = '';
  duplicateLead.value = null;

  const payload: any = {
    name: form.name,
    phone: form.phone,
    email: form.email || null,
    company_name: form.company_name || null,
    source: form.source,
    service_interest: form.service_interest || null,
    status: form.status,
    priority: form.priority,
    assigned_to: form.assigned_to || null,
    next_follow_up_date: form.next_follow_up_date || null,
    additional_details: form.additional_details || null,
    confirm_duplicate: confirmDuplicate.value,
  };

  try {
    if (isOnline.value) {
      const res = await api.post('/leads', payload);
      router.push(`/leads/${res.data.lead.id}`);
    } else {
      await syncEngine.enqueueMutation('create_lead', undefined, payload);
      router.push('/leads');
    }
  } catch (err: any) {
    if (err.response?.status === 409 && err.response?.data?.duplicate) {
      duplicateLead.value = err.response.data.existing_lead;
      errorMsg.value = err.response.data.message;
    } else if (!err.response) {
      // Network failure, queue offline
      await syncEngine.enqueueMutation('create_lead', undefined, payload);
      router.push('/leads');
    } else {
      errorMsg.value = err.response?.data?.message || 'Failed to create lead.';
    }
  } finally {
    submitting.value = false;
  }
};

// -------------------------------------------------------------
// BULK IMPORT WIZARD STATE
// -------------------------------------------------------------
const bulkStep = ref(1);
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);
const uploadingFile = ref(false);
const bulkError = ref('');

const fileToken = ref('');
const uploadedFileName = ref('');
const fileHeaders = ref<string[]>([]);
const samplePreview = ref<any[]>([]);
const totalRowsFound = ref(0);

const mapping = reactive<Record<string, string>>({
  name: '',
  phone: '',
  email: '',
  company_name: '',
  source: '',
  service_interest: '',
  additional_details: '',
});

// Step 2 Analysis
const analyzingDuplicates = ref(false);
const duplicateAnalysis = ref<any>(null);

// Step 3 Assignment
const loadingUsers = ref(false);
const assignableUsers = ref<any[]>([]);
const selectedAssigneeIds = ref<number[]>([]);
const leaveUnassigned = ref(false);
const executingImport = ref(false);
const importReport = ref<any>(null);

const handleDrop = (e: DragEvent) => {
  dragOver.value = false;
  if (e.dataTransfer?.files.length) {
    uploadFile(e.dataTransfer.files[0]);
  }
};

const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files?.length) {
    uploadFile(target.files[0]);
  }
};

const uploadFile = async (file: File) => {
  bulkError.value = '';
  uploadingFile.value = true;
  const formData = new FormData();
  formData.append('file', file);

  try {
    const res = await api.post('/import/upload-preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    fileToken.value = res.data.file_token;
    uploadedFileName.value = res.data.filename;
    fileHeaders.value = res.data.headers;
    samplePreview.value = res.data.preview;
    totalRowsFound.value = res.data.total_rows;

    // Apply auto-detected column mapping
    Object.assign(mapping, res.data.detected_mapping || {});
  } catch (err: any) {
    bulkError.value = err.response?.data?.message || 'Failed to upload spreadsheet. Ensure valid CSV or Excel file.';
  } finally {
    uploadingFile.value = false;
  }
};

const resetFileInput = () => {
  fileToken.value = '';
  uploadedFileName.value = '';
  fileHeaders.value = [];
  samplePreview.value = [];
  totalRowsFound.value = 0;
  bulkError.value = '';
  if (fileInput.value) fileInput.value.value = '';
};

// Scan for duplicates (Step 1 -> Step 2)
const analyzeDuplicates = async () => {
  if (!mapping.name || !mapping.phone) return;
  analyzingDuplicates.value = true;
  bulkError.value = '';

  try {
    const res = await api.post('/import/preview-duplicates', {
      file_token: fileToken.value,
      mapping: mapping,
    });

    duplicateAnalysis.value = res.data;
    bulkStep.value = 2;
  } catch (err: any) {
    bulkError.value = err.response?.data?.message || 'Duplicate pre-scan failed.';
  } finally {
    analyzingDuplicates.value = false;
  }
};

// Step 2 -> Step 3
const proceedToAssignment = async () => {
  bulkStep.value = 3;
  await fetchAssignableUsers();
};

const fetchAssignableUsers = async () => {
  loadingUsers.value = true;
  try {
    const res = await api.get('/import/assignable-users');
    assignableUsers.value = res.data.users || [];

    // Pre-select all active team members by default
    if (selectedAssigneeIds.value.length === 0) {
      selectedAssigneeIds.value = assignableUsers.value.map((u: any) => u.id);
    }
  } catch (err) {
    // quiet
  } finally {
    loadingUsers.value = false;
  }
};

const toggleUserSelection = (userId: number) => {
  if (leaveUnassigned.value) return;
  const idx = selectedAssigneeIds.value.indexOf(userId);
  if (idx > -1) {
    selectedAssigneeIds.value.splice(idx, 1);
  } else {
    selectedAssigneeIds.value.push(userId);
  }
};

const selectAllAssignees = () => {
  selectedAssigneeIds.value = assignableUsers.value.map((u: any) => u.id);
};

const deselectAllAssignees = () => {
  selectedAssigneeIds.value = [];
};

const handleLeaveUnassignedToggle = () => {
  if (leaveUnassigned.value) {
    selectedAssigneeIds.value = [];
  } else {
    selectAllAssignees();
  }
};

// Real-time round-robin calculation
const roundRobinDistribution = computed(() => {
  const cleanCount = duplicateAnalysis.value?.clean_count || 0;
  const selectedCount = selectedAssigneeIds.value.length;
  if (selectedCount === 0 || cleanCount === 0) return [];

  const basePerUser = Math.floor(cleanCount / selectedCount);
  const remainder = cleanCount % selectedCount;

  return selectedAssigneeIds.value.map((id, index) => {
    const user = assignableUsers.value.find((u) => u.id === id);
    const count = basePerUser + (index < remainder ? 1 : 0);
    return {
      id,
      name: user?.name || `User #${id}`,
      role: user?.role_display || user?.role || 'Employee',
      assignedCount: count,
    };
  });
});

// Step 3 -> Step 4 Execution
const executeBatchImport = async () => {
  executingImport.value = true;
  bulkError.value = '';

  const mode = leaveUnassigned.value || selectedAssigneeIds.value.length === 0 ? 'unassigned' : 'round_robin';

  try {
    const res = await api.post('/import/process', {
      file_token: fileToken.value,
      mapping: mapping,
      skip_duplicates: true,
      assignment_mode: mode,
      selected_assignee_ids: mode === 'round_robin' ? selectedAssigneeIds.value : [],
    });

    importReport.value = res.data.report;
    bulkStep.value = 4;
  } catch (err: any) {
    bulkError.value = err.response?.data?.message || 'Batch import failed to complete.';
  } finally {
    executingImport.value = false;
  }
};

const resetBulkWizard = () => {
  bulkStep.value = 1;
  resetFileInput();
  duplicateAnalysis.value = null;
  importReport.value = null;
};

const downloadSampleCsv = () => {
  const csvContent =
    "Name,Phone,Email,Company,Lead Source,Service Interest,Details\n" +
    "Suresh Gowda,+91 98800 12345,suresh@gowdatech.in,Gowda Tech,Website,IT Infrastructure & Hardware AMC,Needs comprehensive 24/7 workstation AMC\n" +
    "Meera Pillai,+91 97420 54321,meera@pillairesorts.com,Pillai Luxury Resorts,Referral,Hospitality Management,Looking for central hotel PMS & management consultation\n" +
    "Arun Verma,+91 99160 12345,arun@vermacommerce.com,Verma Commerce,WhatsApp Inbound,Retail & Commerce Solutions,Expanding omnichannel retail billing across 4 stores\n" +
    "Duplicate Test,+91 98800 12345,dupe@test.com,Duplicate Corp,LinkedIn,Enterprise Software,Test intra-file duplicate\n";

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = "vistabound_leads_sample.csv";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
};

const downloadReportCsv = (failedRows: any[]) => {
  let csv = "Row Number,Lead Name,Phone,Failure Reason\n";
  failedRows.forEach((r) => {
    csv += `"${r.row}","${(r.name || '').replace(/"/g, '""')}","${r.phone || ''}","${(r.reason || '').replace(/"/g, '""')}"\n`;
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `vistabound_failed_import_${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
};

onMounted(() => {
  fetchSettings();
  if (route.query.mode === 'bulk') {
    intakeMode.value = 'bulk';
  }
});
</script>
