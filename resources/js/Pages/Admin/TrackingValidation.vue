<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const loading = ref(false);
const lastRun = ref(null);
const checks = ref([]);
const expandedCheck = ref(null);

// Fix confirmation dialog
const showFixDialog = ref(false);
const fixTarget = ref(null);
const fixing = ref(false);

const severityColor = {
    critical: 'bg-red-500/20 text-red-400',
    warning: 'bg-amber-500/20 text-amber-400',
    info: 'bg-blue-500/20 text-blue-400',
};

const fixableChecks = ['double_counted_stock', 'missing_ledger_fields', 'ghost_items'];

function isFixable(checkName) {
    return fixableChecks.includes(checkName);
}

async function runAllChecks() {
    loading.value = true;
    try {
        const { data } = await axios.post(route('admin.run-validation'));
        checks.value = Array.isArray(data) ? data : Object.values(data);
        lastRun.value = new Date();
        toast.success('Validation complete');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Validation failed');
    } finally {
        loading.value = false;
    }
}

function toggleExpand(checkName) {
    expandedCheck.value = expandedCheck.value === checkName ? null : checkName;
}

function openFix(check) {
    fixTarget.value = check;
    showFixDialog.value = true;
}

async function confirmFix() {
    if (!fixTarget.value) return;
    fixing.value = true;
    try {
        await axios.post(route('admin.run-fix', fixTarget.value.name));
        toast.success('Fix applied');
        showFixDialog.value = false;
        // Re-run all checks to refresh
        await runAllChecks();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Fix failed');
    } finally {
        fixing.value = false;
    }
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-100">Equipment Tracking Validation</h1>
            <p class="text-sm text-slate-500 mt-1">Developer audit tool — checks ledger integrity</p>
        </div>

        <!-- Run Button + Last Run -->
        <div class="flex items-center gap-4 mb-6">
            <button
                @click="runAllChecks"
                :disabled="loading"
                class="flex items-center gap-2 rounded-lg bg-cyan-500 px-6 py-3 text-sm font-bold text-white hover:bg-cyan-400 transition-colors disabled:opacity-50"
            >
                <i v-if="loading" class="pi pi-spin pi-spinner"></i>
                <i v-else class="pi pi-play"></i>
                {{ loading ? 'Running...' : 'Run All Checks' }}
            </button>
            <span v-if="lastRun" class="text-xs text-slate-500">
                Last run: {{ lastRun.toLocaleString() }}
            </span>
        </div>

        <!-- Empty state -->
        <div v-if="!checks.length && !loading" class="text-center py-16">
            <i class="pi pi-verified text-4xl text-slate-600 mb-3"></i>
            <p class="text-slate-500">Click "Run All Checks" to validate ledger integrity.</p>
        </div>

        <!-- Results Grid -->
        <div v-if="checks.length" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div
                v-for="check in checks"
                :key="check.name"
                class="relative rounded-xl border bg-slate-800 p-5 transition-all"
                :class="check.count === 0 ? 'border-emerald-500/30' : 'border-slate-700'"
            >
                <!-- Pass/Fail overlay icon -->
                <div class="absolute top-4 right-4">
                    <div v-if="check.count === 0" class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20">
                        <i class="pi pi-check text-emerald-400"></i>
                    </div>
                    <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-red-500/20">
                        <i class="pi pi-exclamation-triangle text-red-400"></i>
                    </div>
                </div>

                <!-- Header -->
                <div class="flex items-center gap-2 mb-3 pr-10">
                    <h3 class="text-sm font-semibold text-slate-100">{{ check.label || check.name }}</h3>
                    <span :class="['inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase', severityColor[check.severity] || severityColor.info]">
                        {{ check.severity }}
                    </span>
                </div>

                <!-- Count -->
                <p :class="['text-3xl font-bold mb-1', check.count === 0 ? 'text-emerald-400' : check.severity === 'critical' ? 'text-red-400' : 'text-amber-400']">
                    {{ check.count }}
                </p>
                <p class="text-xs text-slate-500 mb-3">{{ check.description || 'issues found' }}</p>

                <!-- Expand + Fix buttons -->
                <div v-if="check.count > 0" class="flex items-center gap-2">
                    <button
                        @click="toggleExpand(check.name)"
                        class="text-xs text-cyan-400 hover:text-cyan-300"
                    >
                        {{ expandedCheck === check.name ? 'Collapse' : 'View Items' }}
                        <i :class="expandedCheck === check.name ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="ml-1 text-[10px]"></i>
                    </button>
                    <button
                        v-if="isFixable(check.name)"
                        @click="openFix(check)"
                        class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400 transition-colors"
                    >
                        <i class="pi pi-wrench text-[10px] mr-1"></i> Auto Fix
                    </button>
                </div>

                <!-- Expanded: affected items -->
                <div v-if="expandedCheck === check.name && check.items?.length" class="mt-4 rounded-lg border border-slate-700 overflow-hidden">
                    <DataTable
                        :value="check.items.slice(0, 50)"
                        :paginator="check.items.length > 10"
                        :rows="10"
                        class="[&_.p-datatable-thead>tr>th]:!bg-slate-900 [&_.p-datatable-thead>tr>th]:!text-slate-400 [&_.p-datatable-thead>tr>th]:!border-slate-700 [&_.p-datatable-tbody>tr>td]:!bg-slate-800 [&_.p-datatable-tbody>tr>td]:!text-slate-300 [&_.p-datatable-tbody>tr>td]:!border-slate-700 [&_.p-paginator]:!bg-slate-900 [&_.p-paginator]:!border-slate-700 [&_.p-paginator]:!text-slate-400 text-xs"
                    >
                        <Column field="id" header="ID" style="width: 60px" />
                        <Column field="equipment_name" header="Equipment" />
                        <Column field="serial_number" header="Serial #" />
                        <Column field="details" header="Details">
                            <template #body="{ data }">
                                <span class="text-xs text-slate-400">{{ data.details || data.reason || '—' }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Fix Confirmation Dialog -->
        <Dialog
            v-model:visible="showFixDialog"
            header="Confirm Auto Fix"
            modal
            :style="{ width: '420px' }"
            :pt="{ root: { class: '!bg-slate-800 !border-slate-700' }, header: { class: '!bg-slate-800 !text-slate-100 !border-slate-700' }, content: { class: '!bg-slate-800 !text-slate-300' } }"
        >
            <div class="space-y-3">
                <div class="flex items-center gap-3 rounded-lg bg-amber-500/10 border border-amber-500/20 p-3">
                    <i class="pi pi-exclamation-triangle text-amber-400"></i>
                    <p class="text-sm text-amber-300">This action will modify database records.</p>
                </div>
                <p class="text-sm text-slate-300">
                    Fix <strong class="text-slate-100">{{ fixTarget?.label || fixTarget?.name }}</strong>
                    — {{ fixTarget?.count }} item(s) will be affected.
                </p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <button @click="showFixDialog = false" class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors">Cancel</button>
                    <button @click="confirmFix" :disabled="fixing" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400 transition-colors disabled:opacity-50">
                        {{ fixing ? 'Fixing...' : 'Continue' }}
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
