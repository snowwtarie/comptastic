<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useDebtsStore } from '../stores/debts';
import { eur, fmtDateLabel } from '../lib/format';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { useIsMobile } from '../lib/useIsMobile';
import { extractErrorMessage } from '../lib/api';

const debtsStore = useDebtsStore();
const isMobile = useIsMobile();

const loadError = ref('');

onMounted(async () => {
  try {
    await debtsStore.fetch();
  } catch {
    loadError.value = 'Impossible de charger les dettes.';
  }
});

const showForm = ref(false);
const formError = ref('');
const submitting = ref(false);
function blankForm() {
  return { name: '', originalAmount: '', remainingAmount: '', monthlyPayment: '', rate: '', endDate: '' };
}
const form = reactive(blankForm());

const debts = computed(() =>
  debtsStore.items.map((d) => ({
    ...d,
    remainingLabel: eur(d.remaining_amount),
    originalLabel: eur(d.original_amount),
    monthlyLabel: eur(d.monthly_payment),
    rateLabel: `${d.rate}%`,
    endDateLabel: fmtDateLabel(d.end_date),
    progressPct: d.progress_pct.toFixed(1),
    progressLabel: `${Math.round(d.progress_pct)}%`,
    monthsLeftLabel: d.months_left !== null ? `${d.months_left} mensualité(s) restante(s)` : '—',
  }))
);
const totalRemaining = computed(() => debts.value.reduce((s, d) => s + d.remaining_amount, 0));
const totalMonthly = computed(() => debts.value.reduce((s, d) => s + d.monthly_payment, 0));

function openForm() {
  formError.value = '';
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}
async function submitForm() {
  if (!form.name || !form.originalAmount) return;
  formError.value = '';
  submitting.value = true;
  try {
    await debtsStore.create({
      name: form.name,
      originalAmount: Number(form.originalAmount) || 0,
      remainingAmount: Number(form.remainingAmount) || Number(form.originalAmount) || 0,
      monthlyPayment: Number(form.monthlyPayment) || 0,
      rate: Number(form.rate) || 0,
      endDate: form.endDate || '2027-01-01',
    });
    Object.assign(form, blankForm());
    showForm.value = false;
  } catch (e) {
    formError.value = extractErrorMessage(e);
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <main class="max-w-[1120px] w-full mx-auto px-4 sm:px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-2">
      <h1 class="m-0 text-[28px] font-bold tracking-tight">Dettes</h1>
      <button type="button" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2.5 text-sm font-semibold cursor-pointer" @click="openForm">
        <Icon name="plus" :stroke-width="2" />Nouvelle dette
      </button>
    </div>
    <p class="mt-0 mb-7 text-sm text-slate-500">Suivez le remboursement de vos crédits, prêts et paiements échelonnés.</p>

    <div v-if="loadError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ loadError }}</div>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Encours total</div>
        <div class="text-[32px] font-extrabold tracking-tight leading-none">{{ eur(totalRemaining) }}</div>
      </div>
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Mensualités totales</div>
        <div class="text-[32px] font-extrabold tracking-tight leading-none">{{ eur(totalMonthly) }}</div>
      </div>
      <div class="text-[13px] text-slate-500">sur {{ debts.length }} dette(s)</div>
    </div>

    <ModalSheet v-if="showForm" :mobile="isMobile" title="Nouvelle dette" max-width="520px" @close="closeForm">
      <div class="grid gap-4 mb-5">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom</label>
          <input v-model="form.name" type="text" placeholder="Ex. Prêt auto, Crédit conso..." class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Montant initial (€)</label>
            <input v-model="form.originalAmount" type="number" step="0.01" min="0" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Restant à rembourser (€)</label>
            <input v-model="form.remainingAmount" type="number" step="0.01" min="0" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mensualité (€)</label>
            <input v-model="form.monthlyPayment" type="number" step="0.01" min="0" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Taux annuel (%)</label>
            <input v-model="form.rate" type="number" step="0.1" min="0" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Échéance finale</label>
          <input v-model="form.endDate" type="date" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-lg" />
        </div>
      </div>
      <div v-if="formError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">
        {{ formError }}
      </div>
      <div class="flex gap-3">
        <button type="button" :disabled="submitting" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer disabled:opacity-60" @click="submitForm">
          <Icon name="plus" :stroke-width="2" />Ajouter
        </button>
        <button type="button" class="inline-flex items-center gap-1.5 bg-transparent text-slate-600 border border-slate-200 rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50" @click="closeForm">
          <Icon name="close" :stroke-width="2" />Annuler
        </button>
      </div>
    </ModalSheet>

    <section class="grid gap-4">
      <div v-for="d in debts" :key="d.id" class="bg-white border border-slate-200 rounded-2xl shadow-sm px-6 py-5">
        <div class="flex justify-between items-start gap-4 flex-wrap mb-3.5">
          <div>
            <div class="text-[15px] font-bold mb-0.5">{{ d.name }}</div>
            <div class="text-xs text-slate-400">Échéance {{ d.endDateLabel }} · Taux {{ d.rateLabel }} · Mensualité {{ d.monthlyLabel }}</div>
          </div>
          <div class="text-right">
            <div class="text-[22px] font-extrabold tracking-tight">{{ d.remainingLabel }}</div>
            <div class="text-xs text-slate-400">restant sur {{ d.originalLabel }}</div>
          </div>
        </div>
        <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden mb-2">
          <div class="h-full rounded-full bg-indigo-600" :style="{ width: d.progressPct + '%' }"></div>
        </div>
        <div class="flex justify-between text-[13px] text-slate-500">
          <span>{{ d.progressLabel }} remboursés</span>
          <span>{{ d.monthsLeftLabel }}</span>
        </div>
      </div>
    </section>
  </main>
</template>
