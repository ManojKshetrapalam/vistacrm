<template>
  <AppLayout>
    <div class="space-y-6 max-w-5xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-violet/20 text-purple-300 border border-vb-violet/30">
              Data Ingestion Engine
            </span>
          </div>
          <h2 class="text-2xl font-display font-bold text-white mt-1">Batch Lead Import Wizard</h2>
          <p class="text-xs text-purple-200/70 mt-0.5">
            Upload CSV or Excel (.xlsx, .xls) files with duplicate protection and bulk employee assignment.
          </p>
        </div>

        <button
          v-if="activeMainTab === 'wizard'"
          @click="downloadSampleCsv"
          class="glass-surface border border-white/10 px-4 py-2 rounded-full text-xs font-semibold flex items-center gap-1.5 self-start text-purple-200 hover:text-white hover:bg-white/10 transition-all"
        >
          <Download class="w-3.5 h-3.5" />
          <span>Download Sample CSV Template</span>
        </button>
      </div>

      <!-- Navigation Tabs: New Import vs Import Batches & History -->
      <div class="flex items-center gap-2 border-b border-white/10 pb-3">
        <button
          @click="activeMainTab = 'wizard'"
          class="px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-2"
          :class="activeMainTab === 'wizard' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-plum-btn' : 'glass-surface border border-white/10 text-purple-200/70 hover:text-white hover:bg-white/10'"
        >
          <UploadCloud class="w-4 h-4" />
          <span>New Lead Ingestion</span>
        </button>

        <button
          @click="activeMainTab = 'history'; fetchHistory()"
          class="px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-2"
          :class="activeMainTab === 'history' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-plum-btn' : 'glass-surface border border-white/10 text-purple-200/70 hover:text-white hover:bg-white/10'"
        >
          <History class="w-4 h-4" />
          <span>Import Batches &amp; History</span>
        </button>
      </div>

      <!-- WIZARD TAB -->
      <template v-if="activeMainTab === 'wizard'">
      <!-- Step Indicator Bar -->
      <div class="glass-surface p-4 border border-white/10">
        <div class="grid grid-cols-4 gap-2 text-center text-xs">
          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 1 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : step > 1 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 1</span>
            <span>Upload File</span>
          </div>

          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 2 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : step > 2 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 2</span>
            <span>Map Columns</span>
          </div>

          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 3 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : step > 3 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 3</span>
            <span>Preview &amp; Assign</span>
          </div>

          <div
            class="p-2.5 rounded-xl transition-all"
            :class="step === 4 ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white font-bold shadow-plum-btn' : 'text-purple-200/50 bg-white/[0.03] border border-white/5'"
          >
            <span class="block text-[10px] uppercase tracking-wider">Step 4</span>
            <span>Import Report</span>
          </div>
        </div>
      </div>

      <!-- STEP 1: Upload File -->
      <div v-if="step === 1" class="glass-surface p-8 space-y-6 border border-white/10">
        <div
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

          <h3 class="font-display font-bold text-lg text-white">Select or Drag &amp; Drop Spreadsheet</h3>
          <p class="text-xs text-purple-200/70 mt-1 max-w-sm mx-auto">
            Supported formats: <strong class="text-white">.csv</strong>, <strong class="text-white">.xlsx</strong>, or <strong class="text-white">.xls</strong> up to 10 MB.
          </p>

          <div class="mt-5 inline-flex items-center gap-2 btn-primary px-6 py-2.5 rounded-full text-xs font-semibold shadow-plum-btn">
            <FileSpreadsheet class="w-4 h-4" />
            <span>Choose File From Device</span>
          </div>
        </div>

        <div v-if="uploadError" class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs rounded-xl backdrop-blur-sm">
          {{ uploadError }}
        </div>
      </div>

      <!-- STEP 2: Column Mapping -->
      <div v-if="step === 2" class="glass-surface p-8 space-y-6 border border-white/10">
        <div class="border-b border-white/10 pb-4">
          <h3 class="font-display font-bold text-lg text-white">Map Spreadsheet Headers to CRM Fields</h3>
          <p class="text-xs text-purple-200/70 mt-0.5">
            We automatically detected matching columns from file: <strong class="text-white">{{ uploadedFileName }}</strong> ({{ totalRowsFound }} rows found).
          </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 text-xs">
          <!-- Lead Name (Mandatory) -->
          <div class="p-4 rounded-xl border space-y-1.5 shadow-sm" :class="mapping.name ? 'border-white/15 bg-white/[0.04]' : 'border-rose-500/40 bg-rose-500/10'">
            <div class="flex items-center justify-between">
              <label class="font-bold text-purple-200 uppercase tracking-wider text-[11px]">
                Lead Name <span class="text-rose-400">*</span>
              </label>
              <span class="text-[10px] text-rose-400 font-semibold">Mandatory</span>
            </div>
            <select v-model="mapping.name" class="field text-xs">
              <option value="">-- Choose Column --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>

          <!-- Phone Number (Mandatory) -->
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

          <!-- Source -->
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
            <label class="font-semibold text-purple-200 uppercase tracking-wider text-[11px]">Additional Scope / Notes</label>
            <select v-model="mapping.additional_details" class="field text-xs">
              <option value="">-- Optional / Skip --</option>
              <option v-for="h in fileHeaders" :key="h" :value="h">{{ h }}</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-white/10">
          <button @click="step = 1" class="text-xs font-semibold text-purple-300 hover:text-white transition-colors">
            ← Choose Different File
          </button>
          <button
            @click="proceedToPreview"
            :disabled="!mapping.name || !mapping.phone"
            class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-plum-btn disabled:opacity-50"
          >
            <span>Preview &amp; Assignment Options</span>
            <ArrowRight class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- STEP 3: Preview & Assignment Settings -->
      <div v-if="step === 3" class="glass-surface p-8 space-y-6 border border-white/10">
        <div class="border-b border-white/10 pb-4">
          <h3 class="font-display font-bold text-lg text-white">Data Preview &amp; Lead Assignment</h3>
          <p class="text-xs text-purple-200/70 mt-0.5">
            Inspect sample rows and configure how imported leads should be assigned to solutions desk representatives.
          </p>
        </div>

        <!-- Sample Preview Table -->
        <div class="overflow-x-auto border border-white/10 rounded-2xl bg-white/[0.03] shadow-sm">
          <table class="w-full text-left text-xs">
            <thead class="bg-white/[0.05] border-b border-white/10 text-purple-200 font-semibold">
              <tr>
                <th class="py-2.5 px-3">Lead Name</th>
                <th class="py-2.5 px-3">Phone</th>
                <th class="py-2.5 px-3">Email</th>
                <th class="py-2.5 px-3">Company</th>
                <th class="py-2.5 px-3">Service</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="(row, idx) in samplePreview" :key="idx" class="hover:bg-white/[0.04]">
                <td class="py-2.5 px-3 font-semibold text-white">{{ row[mapping.name] || '—' }}</td>
                <td class="py-2.5 px-3 font-mono text-purple-200">{{ row[mapping.phone] || '—' }}</td>
                <td class="py-2.5 px-3 text-purple-200/70">{{ mapping.email ? row[mapping.email] : '—' }}</td>
                <td class="py-2.5 px-3 text-white">{{ mapping.company_name ? row[mapping.company_name] : '—' }}</td>
                <td class="py-2.5 px-3 text-purple-200/70">{{ mapping.service_interest ? row[mapping.service_interest] : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Assignment & Deduplication Configuration -->
        <div class="p-6 glass-surface rounded-2xl border border-white/10 space-y-4">
          <h4 class="font-display font-bold text-sm text-white">Ingestion &amp; Assignment Rules</h4>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Assignment Mode -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Bulk Employee Allocation
              </label>
              <select v-model="assignmentMode" class="field text-xs">
                <option value="unassigned">Leave Unassigned</option>
                <option value="round_robin">Round-Robin (Evenly distribute across active team)</option>
                <option value="specific">Assign All to Specific Representative</option>
              </select>
            </div>

            <!-- Specific Employee Dropdown -->
            <div v-if="assignmentMode === 'specific'">
              <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
                Assignee Representative
              </label>
              <select v-model="specificAssignedTo" class="field text-xs">
                <option value="">Select Employee...</option>
                <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                  {{ emp.name }} ({{ emp.email }})
                </option>
              </select>
            </div>
          </div>

          <!-- Duplicate Strategy Checkbox -->
          <div class="pt-2">
            <label class="flex items-center gap-2.5 text-xs text-white font-semibold cursor-pointer">
              <input
                type="checkbox"
                v-model="skipDuplicates"
                class="rounded text-vb-magenta focus:ring-vb-magenta bg-white/5 border-white/20"
              />
              <span>Automatically skip duplicates (phones matching existing CRM leads or within the file)</span>
            </label>
            <p class="text-[11px] text-purple-200/70 ml-6 mt-0.5">
              Skipped duplicate records will be reported in the post-import summary and available for download.
            </p>
          </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-white/10">
          <button @click="step = 2" class="text-xs font-semibold text-purple-300 hover:text-white transition-colors">
            ← Back to Mapping
          </button>
          <button
            @click="processBatchImport"
            :disabled="processingImport"
            class="btn-primary px-7 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-plum-btn"
          >
            <span v-if="processingImport" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ processingImport ? 'Executing Batch Import...' : 'Confirm & Execute Import' }}</span>
          </button>
        </div>
      </div>

      <!-- STEP 4: Import Summary Report -->
      <div v-if="step === 4 && importReport" class="glass-surface p-8 space-y-6 border border-white/10">
        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center justify-center shadow-md">
            <CheckCircle2 class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-display font-bold text-xl text-white">Import Processing Completed</h3>
            <p class="text-xs text-purple-200/70">Summary audit metrics for file: {{ uploadedFileName }}</p>
          </div>
        </div>

        <!-- Metric Counters -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div class="p-4 rounded-xl glass-surface border border-white/10 text-center shadow-sm">
            <span class="text-xs text-purple-200/70 uppercase tracking-wider block font-semibold">Total Rows</span>
            <span class="text-2xl font-bold text-white font-display mt-1 block">{{ importReport.total_rows }}</span>
          </div>

          <div class="p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-center shadow-sm">
            <span class="text-xs text-emerald-300 uppercase tracking-wider block font-semibold">Imported</span>
            <span class="text-2xl font-bold text-emerald-200 font-display mt-1 block">{{ importReport.imported_count }}</span>
          </div>

          <div class="p-4 rounded-xl bg-amber-500/15 border border-amber-500/30 text-center shadow-sm">
            <span class="text-xs text-amber-300 uppercase tracking-wider block font-semibold">Duplicates</span>
            <span class="text-2xl font-bold text-amber-200 font-display mt-1 block">{{ importReport.duplicate_count }}</span>
          </div>

          <div class="p-4 rounded-xl bg-rose-500/15 border border-rose-500/30 text-center shadow-sm">
            <span class="text-xs text-rose-300 uppercase tracking-wider block font-semibold">Invalid</span>
            <span class="text-2xl font-bold text-rose-200 font-display mt-1 block">{{ importReport.invalid_count }}</span>
          </div>

          <div class="p-4 rounded-xl bg-white/[0.04] border border-white/10 text-center col-span-2 sm:col-span-1 shadow-sm">
            <span class="text-xs text-purple-200/70 uppercase tracking-wider block font-semibold">Skipped</span>
            <span class="text-2xl font-bold text-white font-display mt-1 block">{{ importReport.skipped_count }}</span>
          </div>
        </div>

        <!-- Failed Rows Table / Download -->
        <div v-if="importReport.failed_rows && importReport.failed_rows.length > 0" class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="font-display font-bold text-sm text-white">Failed &amp; Skipped Records Audit</h4>
            <button
              @click="downloadReportCsv(importReport.failed_rows)"
              class="text-xs font-semibold text-purple-300 hover:text-white flex items-center gap-1 transition-colors"
            >
              <Download class="w-3.5 h-3.5" />
              <span>Download Failed Rows CSV</span>
            </button>
          </div>

          <div class="overflow-x-auto border border-white/10 rounded-2xl bg-white/[0.03] max-h-60 overflow-y-auto shadow-sm">
            <table class="w-full text-left text-xs">
              <thead class="bg-white/[0.05] border-b border-white/10 text-purple-200 font-semibold sticky top-0">
                <tr>
                  <th class="py-2.5 px-3">Row #</th>
                  <th class="py-2.5 px-3">Name</th>
                  <th class="py-2.5 px-3">Phone</th>
                  <th class="py-2.5 px-3">Failure Reason</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr v-for="(fRow, fIdx) in importReport.failed_rows" :key="fIdx" class="hover:bg-rose-500/10">
                  <td class="py-2 px-3 font-mono font-bold text-purple-300">Row {{ fRow.row }}</td>
                  <td class="py-2 px-3 text-white">{{ fRow.name || '—' }}</td>
                  <td class="py-2 px-3 font-mono text-purple-200">{{ fRow.phone || '—' }}</td>
                  <td class="py-2 px-3 text-rose-300 font-medium">{{ fRow.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-4 border-t border-white/10 flex items-center justify-end gap-3">
          <button
            @click="resetWizard"
            class="glass-surface border border-white/10 px-5 py-2.5 rounded-full text-xs font-semibold text-purple-200 hover:text-white hover:bg-white/10 transition-all"
          >
            Import Another File
          </button>
          <router-link
            to="/leads"
            class="btn-primary px-6 py-2.5 rounded-full text-xs font-semibold flex items-center gap-1.5 shadow-plum-btn"
          >
            <span>View Leads Pipeline</span>
            <ArrowRight class="w-4 h-4" />
          </router-link>
        </div>
      </div>
      </template>

      <!-- IMPORT BATCHES & HISTORY TAB -->
      <div v-else class="space-y-6">
        <div class="glass-surface border border-white/10 p-6 shadow-sm">
          <div class="flex items-center justify-between pb-4 border-b border-white/10">
            <div>
              <h3 class="font-display font-bold text-lg text-white">Historical Import Batches</h3>
              <p class="text-xs text-purple-200/70 mt-0.5">Audit log of all past lead ingestion batches, duplicate detection counts, and error reports.</p>
            </div>
            <button
              @click="fetchHistory(historyPagination.current_page)"
              :disabled="loadingHistory"
              class="glass-surface border border-white/10 p-2 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-purple-200 hover:text-white hover:bg-white/10 transition-all"
            >
              <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingHistory }" />
              <span>Refresh</span>
            </button>
          </div>

          <!-- Loading State -->
          <div v-if="loadingHistory" class="py-12 text-center text-purple-200/70 text-xs">
            <RefreshCw class="w-6 h-6 animate-spin mx-auto text-vb-magenta mb-2" />
            Loading import history...
          </div>

          <!-- Empty State -->
          <div v-else-if="importHistories.length === 0" class="py-12 text-center text-purple-200/60 space-y-2">
            <FileSpreadsheet class="w-10 h-10 mx-auto text-white/30" />
            <p class="font-medium text-white">No import batches recorded yet.</p>
            <p class="text-xs">Uploaded spreadsheets will be logged here with complete row-by-row audit trails.</p>
          </div>

          <!-- History Table -->
          <div v-else class="overflow-x-auto mt-4">
            <table class="w-full text-left text-xs">
              <thead class="bg-white/[0.05] text-purple-200 border-b border-white/10 uppercase font-display font-bold text-[10px] tracking-wider">
                <tr>
                  <th class="py-3 px-3">Batch &amp; Date</th>
                  <th class="py-3 px-3">Uploaded By</th>
                  <th class="py-3 px-3">Assignment Target</th>
                  <th class="py-3 px-3 text-center">Total Rows</th>
                  <th class="py-3 px-3 text-center">Ingested</th>
                  <th class="py-3 px-3 text-center">Duplicates</th>
                  <th class="py-3 px-3 text-center">Failed</th>
                  <th class="py-3 px-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr v-for="item in importHistories" :key="item.id" class="hover:bg-white/[0.04] transition-colors">
                  <td class="py-3 px-3">
                    <div class="font-semibold text-white flex items-center gap-1.5">
                      <FileSpreadsheet class="w-3.5 h-3.5 text-vb-magenta flex-shrink-0" />
                      <span class="truncate max-w-[180px]" :title="item.filename">{{ item.filename }}</span>
                    </div>
                    <div class="text-[10px] text-purple-200/60 mt-0.5">
                      {{ formatDateTime(item.created_at) }}
                    </div>
                  </td>
                  <td class="py-3 px-3 text-white font-medium">
                    {{ item.user?.name || 'Administrator' }}
                  </td>
                  <td class="py-3 px-3">
                    <span v-if="item.assigned_employee" class="px-2 py-0.5 rounded-full bg-white/10 border border-white/15 text-white text-[11px] font-medium">
                      {{ item.assigned_employee.name }}
                    </span>
                    <span v-else class="text-purple-200/50 text-[11px] italic">
                      Unassigned / Pool
                    </span>
                  </td>
                  <td class="py-3 px-3 text-center font-mono font-bold text-white">
                    {{ item.total_rows }}
                  </td>
                  <td class="py-3 px-3 text-center font-mono">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold">
                      {{ item.imported_count }}
                    </span>
                  </td>
                  <td class="py-3 px-3 text-center font-mono">
                    <span v-if="item.duplicate_count > 0" class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold">
                      {{ item.duplicate_count }}
                    </span>
                    <span v-else class="text-purple-200/50">0</span>
                  </td>
                  <td class="py-3 px-3 text-center font-mono">
                    <span v-if="(item.invalid_count + item.skipped_count) > 0" class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 font-bold">
                      {{ item.invalid_count + item.skipped_count }}
                    </span>
                    <span v-else class="text-purple-200/50">0</span>
                  </td>
                  <td class="py-3 px-3 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <button
                        v-if="hasFailedRows(item)"
                        @click="downloadFailedReport(item)"
                        class="p-1.5 rounded-lg border border-rose-500/30 text-rose-300 hover:bg-rose-500/20 transition-colors"
                        title="Download Failed Rows CSV Report"
                      >
                        <Download class="w-3.5 h-3.5" />
                      </button>
                      <button
                        @click="selectedHistory = item"
                        class="p-1.5 rounded-lg border border-white/15 text-purple-200 hover:text-white hover:bg-white/10 transition-colors"
                        title="View Batch Audit Details"
                      >
                        <Eye class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="historyPagination.last_page > 1" class="flex items-center justify-between pt-4 border-t border-white/10 mt-4 text-xs">
              <span class="text-purple-200/70">
                Showing page {{ historyPagination.current_page }} of {{ historyPagination.last_page }} ({{ historyPagination.total }} total batches)
              </span>
              <div class="flex items-center gap-2">
                <button
                  :disabled="historyPagination.current_page <= 1"
                  @click="fetchHistory(historyPagination.current_page - 1)"
                  class="glass-surface px-3 py-1.5 rounded-lg border border-white/15 text-purple-200 hover:text-white hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                <button
                  :disabled="historyPagination.current_page >= historyPagination.last_page"
                  @click="fetchHistory(historyPagination.current_page + 1)"
                  class="glass-surface px-3 py-1.5 rounded-lg border border-white/15 text-purple-200 hover:text-white hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Batch Audit Detail Modal -->
        <div v-if="selectedHistory" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md">
          <div class="glass-elevated border border-white/15 max-w-2xl w-full p-6 sm:p-7 shadow-2xl space-y-5 max-h-[90vh] flex flex-col relative overflow-hidden">
            <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>
            <div class="flex items-center justify-between border-b border-white/10 pb-3 pt-1">
              <div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-violet/20 text-purple-300 border border-vb-violet/30">
                  Batch Audit Inspection
                </span>
                <h3 class="font-display font-bold text-lg text-white mt-1 truncate max-w-md">
                  {{ selectedHistory.filename }}
                </h3>
              </div>
              <button @click="selectedHistory = null" class="p-1.5 rounded-full hover:bg-white/10 text-purple-200 hover:text-white transition-colors">
                <X class="w-5 h-5" />
              </button>
            </div>

            <div class="grid grid-cols-4 gap-3 text-center text-xs">
              <div class="p-3 bg-white/[0.04] rounded-2xl border border-white/10 shadow-sm">
                <span class="text-purple-200/70 text-[10px] uppercase font-bold block">Total Rows</span>
                <span class="text-lg font-bold font-mono text-white">{{ selectedHistory.total_rows }}</span>
              </div>
              <div class="p-3 bg-emerald-500/15 rounded-2xl text-emerald-300 border border-emerald-500/30">
                <span class="text-[10px] uppercase font-bold block">Ingested</span>
                <span class="text-lg font-bold font-mono">{{ selectedHistory.imported_count }}</span>
              </div>
              <div class="p-3 bg-amber-500/15 rounded-2xl text-amber-300 border border-amber-500/30">
                <span class="text-[10px] uppercase font-bold block">Duplicates</span>
                <span class="text-lg font-bold font-mono">{{ selectedHistory.duplicate_count }}</span>
              </div>
              <div class="p-3 bg-rose-500/15 rounded-2xl text-rose-300 border border-rose-500/30">
                <span class="text-[10px] uppercase font-bold block">Failed</span>
                <span class="text-lg font-bold font-mono">{{ (selectedHistory.invalid_count || 0) + (selectedHistory.skipped_count || 0) }}</span>
              </div>
            </div>

            <!-- Failed Rows List inside Modal -->
            <div class="flex-1 overflow-y-auto space-y-3 pr-1">
              <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold uppercase tracking-wider text-purple-200 font-display">Row-Level Failures / Skip Log</h4>
                <button
                  v-if="hasFailedRows(selectedHistory)"
                  @click="downloadFailedReport(selectedHistory)"
                  class="text-xs font-semibold text-purple-300 hover:text-white flex items-center gap-1 transition-colors"
                >
                  <Download class="w-3.5 h-3.5" />
                  <span>Download Report (.csv)</span>
                </button>
              </div>

              <div v-if="selectedHistory.summary?.failed_rows?.length" class="border border-white/10 rounded-2xl overflow-hidden bg-white/[0.03] shadow-sm">
                <table class="w-full text-left text-xs">
                  <thead class="bg-white/[0.05] text-purple-200 font-bold border-b border-white/10">
                    <tr>
                      <th class="py-2.5 px-3">Row</th>
                      <th class="py-2.5 px-3">Name</th>
                      <th class="py-2.5 px-3">Phone</th>
                      <th class="py-2.5 px-3">Failure Reason</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-white/5">
                    <tr v-for="(f, idx) in selectedHistory.summary.failed_rows" :key="idx" class="hover:bg-rose-500/10">
                      <td class="py-2 px-3 font-mono font-bold text-purple-300">Row {{ f.row }}</td>
                      <td class="py-2 px-3 text-white">{{ f.name || '—' }}</td>
                      <td class="py-2 px-3 font-mono text-purple-200">{{ f.phone || '—' }}</td>
                      <td class="py-2 px-3 text-rose-300 font-medium">{{ f.reason }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="p-4 bg-emerald-500/15 text-emerald-300 text-xs rounded-2xl flex items-center gap-2 border border-emerald-500/30">
                <CheckCircle2 class="w-4 h-4 text-emerald-300 flex-shrink-0" />
                <span>All rows in this spreadsheet were ingested cleanly with zero validation errors!</span>
              </div>
            </div>

            <div class="pt-3 border-t border-white/10 flex justify-end">
              <button @click="selectedHistory = null" class="btn-primary px-5 py-2 rounded-full text-xs font-semibold shadow-plum-btn">
                Close Audit View
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import {
  UploadCloud,
  FileSpreadsheet,
  Download,
  ArrowRight,
  CheckCircle2,
  History,
  RefreshCw,
  Eye,
  X,
} from 'lucide-vue-next';

const activeMainTab = ref<'wizard' | 'history'>('wizard');
const importHistories = ref<any[]>([]);
const loadingHistory = ref(false);
const historyPagination = ref<any>({ current_page: 1, last_page: 1, total: 0 });
const selectedHistory = ref<any>(null);

const step = ref(1);
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);
const uploadError = ref('');

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

const assignmentMode = ref('unassigned');
const specificAssignedTo = ref('');
const skipDuplicates = ref(true);

const processingImport = ref(false);
const importReport = ref<any>(null);

const employeesList = ref<any[]>([]);

const fetchEmployees = async () => {
  try {
    const res = await api.get('/settings');
    employeesList.value = res.data.employees || [];
  } catch (err) {
    // quiet
  }
};

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
  uploadError.value = '';
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

    // Apply auto-detected mapping
    Object.assign(mapping, res.data.detected_mapping || {});

    step.value = 2;
  } catch (err: any) {
    uploadError.value = err.response?.data?.message || 'Failed to process file. Ensure valid CSV or Excel.';
  }
};

const proceedToPreview = () => {
  if (!mapping.name || !mapping.phone) return;
  step.value = 3;
};

const processBatchImport = async () => {
  processingImport.value = true;
  try {
    const res = await api.post('/import/process', {
      file_token: fileToken.value,
      mapping: mapping,
      skip_duplicates: skipDuplicates.value,
      assignment_mode: assignmentMode.value,
      assigned_to: assignmentMode.value === 'specific' ? specificAssignedTo.value : null,
    });

    importReport.value = res.data.report;
    step.value = 4;
  } catch (err: any) {
    alert(err.response?.data?.message || 'Import failed to complete.');
  } finally {
    processingImport.value = false;
  }
};

const resetWizard = () => {
  step.value = 1;
  fileToken.value = '';
  uploadedFileName.value = '';
  fileHeaders.value = [];
  samplePreview.value = [];
  importReport.value = null;
  if (fileInput.value) fileInput.value.value = '';
};

const downloadSampleCsv = () => {
  const csvContent =
    "Name,Phone,Email,Company,Lead Source,Service Interest,Details\n" +
    "Suresh Gowda,+91 98800 12345,suresh@gowdatech.in,Gowda Tech,Website,IT Infrastructure & Hardware AMC,Needs comprehensive 24/7 workstation AMC\n" +
    "Meera Pillai,+91 97420 54321,meera@pillairesorts.com,Pillai Luxury Resorts,Referral,Hospitality Management,Looking for central hotel PMS & management consultation\n" +
    "Arun Verma,9916012345,arun@vermacommerce.com,Verma Commerce,WhatsApp Inbound,Retail & Commerce Solutions,Expanding omnichannel retail billing across 4 stores";

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

const fetchHistory = async (page = 1) => {
  loadingHistory.value = true;
  try {
    const res = await api.get(`/import/history?page=${page}`);
    importHistories.value = res.data.data || [];
    historyPagination.value = {
      current_page: res.data.current_page,
      last_page: res.data.last_page,
      total: res.data.total,
    };
  } catch (err: any) {
    console.error('Failed to load import history:', err);
  } finally {
    loadingHistory.value = false;
  }
};

const hasFailedRows = (item: any) => {
  return (item.summary?.failed_rows && item.summary.failed_rows.length > 0) || (item.invalid_count > 0);
};

const downloadFailedReport = async (item: any) => {
  try {
    const res = await api.get(`/import/history/${item.id}/failed-report`, {
      responseType: 'blob',
    });
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `failed_report_${item.id}_${item.filename || 'import'}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  } catch (err: any) {
    alert('Failed to download failure report: ' + (err.response?.data?.message || err.message));
  }
};

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchEmployees();
});
</script>
