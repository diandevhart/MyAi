<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    suppliers: Object,
});

const search = ref('');
const showDialog = ref(false);
const editing = ref(null);

const form = ref({
    name: '', contact_person: '', email: '', phone: '',
    address: '', city: '', country: '', tax_number: '',
    payment_terms: '', notes: '',
});

const supplierList = computed(() => props.suppliers?.data || []);

const filtered = computed(() => {
    if (!search.value) return supplierList.value;
    const q = search.value.toLowerCase();
    return supplierList.value.filter(s =>
        s.name.toLowerCase().includes(q) ||
        (s.contact_person || '').toLowerCase().includes(q) ||
        (s.email || '').toLowerCase().includes(q) ||
        (s.city || '').toLowerCase().includes(q)
    );
});

function ratingColor(rating) {
    if (rating >= 4.0) return 'bg-emerald-500/20 text-emerald-400';
    if (rating >= 3.0) return 'bg-amber-500/20 text-amber-400';
    return 'bg-red-500/20 text-red-400';
}

function openAdd() {
    editing.value = null;
    form.value = { name: '', contact_person: '', email: '', phone: '', address: '', city: '', country: '', tax_number: '', payment_terms: '', notes: '' };
    showDialog.value = true;
}

function openEdit(supplier) {
    editing.value = supplier;
    form.value = {
        name: supplier.name || '',
        contact_person: supplier.contact_person || '',
        email: supplier.email || '',
        phone: supplier.phone || '',
        address: supplier.address || '',
        city: supplier.city || '',
        country: supplier.country || '',
        tax_number: supplier.tax_number || '',
        payment_terms: supplier.payment_terms || '',
        notes: supplier.notes || '',
    };
    showDialog.value = true;
}

async function submit() {
    try {
        if (editing.value) {
            await axios.put(route('suppliers.update', editing.value.id), form.value);
            toast.success('Supplier updated');
        } else {
            await axios.post(route('suppliers.store'), form.value);
            toast.success('Supplier created');
        }
        showDialog.value = false;
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to save supplier');
    }
}

async function deleteSupplier(supplier) {
    if (!confirm(`Delete supplier "${supplier.name}"?`)) return;
    try {
        await axios.delete(route('suppliers.destroy', supplier.id));
        toast.success('Supplier deleted');
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to delete');
    }
}

function goToSupplier(event) {
    router.visit(route('suppliers.show', event.data.id));
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-100">Suppliers</h1>
            <button @click="openAdd" class="flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors">
                <i class="pi pi-plus text-xs"></i> Add Supplier
            </button>
        </div>

        <!-- Search -->
        <div class="mb-5">
            <div class="relative">
                <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search suppliers..."
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 py-2.5 pl-10 pr-4 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
            </div>
        </div>

        <!-- DataTable -->
        <div class="rounded-xl border border-slate-700 overflow-hidden">
            <DataTable
                :value="filtered"
                :paginator="true"
                :rows="15"
                :rowHover="true"
                @row-click="goToSupplier"
                class="[&_.p-datatable-thead>tr>th]:!bg-slate-800 [&_.p-datatable-thead>tr>th]:!text-slate-400 [&_.p-datatable-thead>tr>th]:!border-slate-700 [&_.p-datatable-tbody>tr>td]:!bg-slate-900 [&_.p-datatable-tbody>tr>td]:!text-slate-300 [&_.p-datatable-tbody>tr>td]:!border-slate-700 [&_.p-datatable-tbody>tr:hover>td]:!bg-slate-800 [&_.p-paginator]:!bg-slate-900 [&_.p-paginator]:!border-slate-700 [&_.p-paginator]:!text-slate-400 cursor-pointer"
                sortMode="single"
                removableSort
            >
                <Column field="name" header="Name" sortable style="min-width: 160px">
                    <template #body="{ data }">
                        <span class="font-medium text-slate-100">{{ data.name }}</span>
                    </template>
                </Column>
                <Column field="contact_person" header="Contact" sortable style="min-width: 130px" />
                <Column field="email" header="Email" sortable style="min-width: 180px">
                    <template #body="{ data }">
                        <span class="text-cyan-400">{{ data.email }}</span>
                    </template>
                </Column>
                <Column field="phone" header="Phone" style="min-width: 120px" />
                <Column header="Location" sortable sortField="city" style="min-width: 130px">
                    <template #body="{ data }">
                        {{ [data.city, data.country].filter(Boolean).join(', ') }}
                    </template>
                </Column>
                <Column field="rating" header="Rating" sortable style="min-width: 90px">
                    <template #body="{ data }">
                        <span v-if="data.rating" :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', ratingColor(data.rating)]">
                            <i class="pi pi-star-fill text-[10px]"></i>
                            {{ Number(data.rating).toFixed(1) }}
                        </span>
                        <span v-else class="text-slate-600 text-xs">N/A</span>
                    </template>
                </Column>
                <Column field="payment_terms" header="Payment Terms" style="min-width: 120px">
                    <template #body="{ data }">
                        <span class="text-xs text-slate-400">{{ data.payment_terms || '—' }}</span>
                    </template>
                </Column>
                <Column header="Active RFQs" style="min-width: 90px">
                    <template #body="{ data }">
                        <span v-if="data.active_rfq_count" class="inline-flex items-center justify-center rounded-full bg-violet-500/20 text-violet-400 px-2.5 py-0.5 text-xs font-semibold">
                            {{ data.active_rfq_count }}
                        </span>
                        <span v-else class="text-slate-600 text-xs">0</span>
                    </template>
                </Column>
                <Column header="Status" style="min-width: 90px">
                    <template #body="{ data }">
                        <span :class="[
                            'inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            data.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/30 text-slate-500'
                        ]">
                            {{ data.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </template>
                </Column>
                <Column header="Actions" style="width: 80px">
                    <template #body="{ data }">
                        <div class="flex items-center gap-1" @click.stop>
                            <button @click="openEdit(data)" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-700 hover:text-cyan-400 transition-colors">
                                <i class="pi pi-pencil text-sm"></i>
                            </button>
                            <button @click="deleteSupplier(data)" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-700 hover:text-red-400 transition-colors">
                                <i class="pi pi-trash text-sm"></i>
                            </button>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Add/Edit Dialog -->
        <Dialog
            v-model:visible="showDialog"
            :header="editing ? 'Edit Supplier' : 'Add Supplier'"
            modal
            :style="{ width: '600px' }"
            :pt="{ root: { class: '!bg-slate-800 !border-slate-700' }, header: { class: '!bg-slate-800 !text-slate-100 !border-slate-700' }, content: { class: '!bg-slate-800 !text-slate-300' } }"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-400">Name *</label>
                    <InputText v-model="form.name" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Contact Person</label>
                    <InputText v-model="form.contact_person" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Email</label>
                    <InputText v-model="form.email" type="email" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Phone</label>
                    <InputText v-model="form.phone" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">City</label>
                    <InputText v-model="form.city" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Country</label>
                    <InputText v-model="form.country" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Tax Number</label>
                    <InputText v-model="form.tax_number" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Payment Terms</label>
                    <InputText v-model="form.payment_terms" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-400">Address</label>
                    <InputText v-model="form.address" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-400">Notes</label>
                    <Textarea v-model="form.notes" rows="3" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <button @click="showDialog = false" class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors">Cancel</button>
                    <button @click="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors">
                        {{ editing ? 'Update' : 'Create' }}
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
