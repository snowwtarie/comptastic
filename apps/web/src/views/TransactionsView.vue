<script setup>
import { reactive, ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useTransactionsStore } from '../stores/transactions';
import { useAccountsStore } from '../stores/accounts';
import { useCategoriesStore } from '../stores/categories';
import { useDebtsStore } from '../stores/debts';
import { eur, fmtDateLabel, addMonthsISO, addStepISO, TODAY_ISO } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { ApiError } from '../lib/api';

const transactionsStore = useTransactionsStore();
const accountsStore = useAccountsStore();
const categoriesStore = useCategoriesStore();
const debtsStore = useDebtsStore();
const route = useRoute();
const isMobile = useIsMobile();

const period = ref('current');
const accountFilter = ref('all');
const showForm = ref(false);
const formError = ref('');
const submitting = ref(false);
const loadError = ref('');
const listError = ref('');

async function loadTransactions() {
  await transactionsStore.fetch({
    period: period.value,
    accountId: accountFilter.value === 'all' ? null : accountFilter.value,
  });
}

onMounted(async () => {
  try {
    await Promise.all([categoriesStore.fetch(), accountsStore.fetch(), debtsStore.fetch(), loadTransactions()]);
    if (form.category_id === null) form.category_id = categoriesStore.expense[0]?.id ?? null;
    if (form.account_id === null) form.account_id = accountsStore.items[0]?.id ?? null;
    if (form.linkedDebtId === null) form.linkedDebtId = debtsStore.items[0]?.id ?? null;
    if (form.savingsAccountId === null) form.savingsAccountId = accountsStore.savings[0]?.id ?? null;
  } catch {
    loadError.value = 'Impossible de charger les transactions.';
  }
});
watch([period, accountFilter], loadTransactions);

function blankForm() {
  return {
    label: '',
    amount: '',
    type: 'expense',
    category_id: categoriesStore.expense[0]?.id ?? null,
    account_id: accountsStore.items[0]?.id ?? null,
    date: TODAY_ISO,
    reconciled: false,
    installment: false,
    installmentCount: 4,
    recurring: false,
    recurringFrequency: 'monthly',
    recurringCount: 12,
    linkType: 'none',
    linkedDebtId: debtsStore.items[0]?.id ?? null,
    savingsAccountId: accountsStore.savings[0]?.id ?? null,
  };
}
const form = reactive(blankForm());

watch(
  () => route.query.new,
  (v) => {
    if (v) showForm.value = true;
  }
);

const installmentRows = computed(() => {
  const total = Number(form.amount) || 0;
  const count = Number(form.installmentCount) || 1;
  const per = total / count;
  const dates = [];
  for (let i = 0; i < count; i++) dates.push(addMonthsISO(form.date, i));
  return dates.map((d, i) => ({ index: i, indexLabel: `${i + 1}/${count}`, date: d, amountLabel: eur(per) }));
});
const recurringRows = computed(() => {
  const total = Number(form.amount) || 0;
  const count = Number(form.recurringCount) || 1;
  const dates = [];
  for (let i = 0; i < count; i++) dates.push(addStepISO(form.date, form.recurringFrequency, i));
  return dates.map((d, i) => ({ index: i, indexLabel: `#${i + 1}`, date: d, amountLabel: eur(total) }));
});

function toggleInstallment(checked) {
  form.installment = checked;
  if (checked) form.recurring = false;
}
function toggleRecurring(checked) {
  form.recurring = checked;
  if (checked) form.installment = false;
}

const transactions = computed(() =>
  transactionsStore.items.map((t) => ({
    ...t,
    categoryName: categoriesStore.byId[t.category_id]?.name ?? '—',
    accountName: accountsStore.byId[t.account_id]?.name ?? '—',
    dateLabel: fmtDateLabel(t.date, { short: isMobile.value }),
    amountLabel: `${t.amount >= 0 ? '+' : ''}${eur(t.amount)}`,
    amountColor: t.amount >= 0 ? 'text-emerald-700' : 'text-slate-900',
    runningBalanceLabel: t.running_balance !== null && t.running_balance !== undefined ? eur(t.running_balance) : '—',
    hasLink: t.link_type === 'debt' || t.link_type === 'savings',
    linkLabel: t.link_type === 'debt' ? `Dette · ${debtsStore.byId[t.linked_debt_id]?.name ?? ''}` : t.link_type === 'savings' ? 'Épargne' : '',
    linkTitle: t.link_type === 'savings' ? `Vers ${accountsStore.byId[t.linked_savings_account_id]?.name ?? ''}` : '',
  }))
);

function openForm() {
  formError.value = '';
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}

async function submitForm() {
  const amt = Number(form.amount) || 0;
  if (!form.label || !amt) return;

  const payload = {
    label: form.label,
    amount: Math.abs(amt),
    type: form.type,
    category_id: form.category_id,
    account_id: form.account_id,
    date: form.date,
    reconciled: form.reconciled,
    link_type: form.linkType,
    linked_debt_id: form.linkType === 'debt' ? form.linkedDebtId : null,
    linked_savings_account_id: form.linkType === 'savings' ? form.savingsAccountId : null,
    mode: form.installment ? 'installment' : form.recurring ? 'recurring' : 'simple',
  };
  if (form.installment) payload.installment = { count: Number(form.installmentCount) };
  if (form.recurring) payload.recurring = { count: Number(form.recurringCount), frequency: form.recurringFrequency };

  formError.value = '';
  submitting.value = true;
  try {
    await transactionsStore.create(payload);
    await loadTransactions();
    Object.assign(form, blankForm());
    showForm.value = false;
  } catch (e) {
    formError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  } finally {
    submitting.value = false;
  }
}

async function toggleReconciled(id) {
  listError.value = '';
  try {
    await transactionsStore.toggleReconciled(id);
  } catch {
    listError.value = 'Impossible de mettre à jour la transaction.';
  }
}
</script>

<template>
  <!-- Mobile -->
  <div v-if="isMobile" class="flex-1 flex flex-col bg-slate-50 relative">
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200">
      <div class="text-[19px] font-extrabold tracking-tight mb-2.5">Transactions</div>
      <div class="flex gap-2">
        <select v-model="accountFilter" class="flex-1 text-xs font-semibold text-slate-700 bg-slate-100 border-none rounded-lg px-2.5 py-2">
          <option value="all">Tous les comptes</option>
          <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <select v-model="period" class="flex-1 text-xs font-semibold text-indigo-600 bg-indigo-50 border-none rounded-lg px-2.5 py-2">
          <option value="current">Ce mois</option>
          <option value="previous">Mois dernier</option>
          <option value="year">Cette année</option>
        </select>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-24">
      <div v-if="loadError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ loadError }}</div>
      <div v-if="listError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ listError }}</div>

      <div class="text-xs text-slate-400 mb-2.5">{{ transactions.length }} transactions</div>
      <div class="grid gap-2.5">
        <div v-for="t in transactions" :key="t.id" class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="flex justify-between items-start gap-2.5 mb-2">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold mb-0.5">{{ t.label }}</div>
              <div class="text-[11px] text-slate-400">{{ t.dateLabel }} · {{ t.accountName }}</div>
            </div>
            <div class="text-right shrink-0">
              <div class="text-[15px] font-extrabold" :class="t.amountColor">{{ t.amountLabel }}</div>
            </div>
          </div>
          <div class="flex justify-between items-center">
            <div class="flex gap-1.5 flex-wrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-indigo-50 text-indigo-700">{{ t.categoryName }}</span>
              <span v-if="t.hasLink" :title="t.linkTitle" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-yellow-100 text-yellow-800">{{ t.linkLabel }}</span>
            </div>
            <button
              type="button"
              class="w-6 h-6 rounded-[7px] border-[1.5px] text-white text-[13px] font-bold flex items-center justify-center cursor-pointer"
              :class="t.reconciled ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
              @click="toggleReconciled(t.id)"
            >{{ t.reconciled ? '✓' : '' }}</button>
          </div>
        </div>
      </div>
    </main>

    <ModalSheet v-if="showForm" mobile title="Nouvelle transaction" @close="closeForm">
      <div class="grid gap-3 mb-3.5">
        <input v-model="form.label" type="text" placeholder="Libellé" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]" />
        <input v-model="form.amount" type="number" step="0.01" min="0" placeholder="Montant (€)" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]" />
        <div class="flex border border-slate-200 rounded-[10px] overflow-hidden">
          <button type="button" class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer" :class="form.type === 'expense' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'" @click="form.type = 'expense'">Dépense</button>
          <button type="button" class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer" :class="form.type === 'income' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'" @click="form.type = 'income'">Recette</button>
        </div>
        <select v-model="form.category_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="c in categoriesStore.items" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="form.account_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <input v-model="form.date" type="date" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]" />
        <label class="flex items-center gap-2 text-[13px] text-slate-700">
          <input type="checkbox" v-model="form.reconciled" class="w-4 h-4 accent-indigo-600" />
          Marquer comme pointée
        </label>

        <div class="flex gap-2">
          <select v-model="form.linkType" class="flex-1 text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
            <option value="none">Aucune affectation</option>
            <option value="debt">Remboursement de dette</option>
            <option value="savings">Épargne</option>
          </select>
        </div>
        <select v-if="form.linkType === 'debt'" v-model="form.linkedDebtId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="d in debtsStore.items" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-if="form.linkType === 'savings'" v-model="form.savingsAccountId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="s in accountsStore.savings" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>

        <label class="flex items-center gap-2 text-[13px] font-semibold text-slate-900">
          <input type="checkbox" :checked="form.installment" @change="toggleInstallment($event.target.checked)" class="w-4 h-4 accent-indigo-600" />
          Paiement échelonné
        </label>
        <div v-if="form.installment" class="bg-slate-50 border border-slate-200 rounded-xl p-3.5">
          <div class="flex items-center gap-2.5 mb-3 flex-wrap">
            <select v-model.number="form.installmentCount" class="text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white">
              <option :value="2">2 fois</option>
              <option :value="3">3 fois</option>
              <option :value="4">4 fois</option>
              <option :value="6">6 fois</option>
              <option :value="12">12 fois</option>
            </select>
            <span class="text-[11px] text-slate-400">un mois d'écart</span>
          </div>
          <div class="grid gap-2">
            <div v-for="row in installmentRows" :key="row.index" class="flex items-center gap-2.5">
              <span class="w-[54px] text-[11px] text-slate-500 font-semibold">{{ row.indexLabel }}</span>
              <input type="date" :value="row.date" disabled class="flex-1 text-[12px] px-2.5 py-1.5 border border-slate-200 rounded-[7px] bg-slate-100 text-slate-500" />
              <span class="w-20 text-right text-[12px] font-semibold">{{ row.amountLabel }}</span>
            </div>
          </div>
        </div>

        <label class="flex items-center gap-2 text-[13px] font-semibold text-slate-900">
          <input type="checkbox" :checked="form.recurring" @change="toggleRecurring($event.target.checked)" class="w-4 h-4 accent-indigo-600" />
          Transaction récurrente
        </label>
        <div v-if="form.recurring" class="bg-slate-50 border border-slate-200 rounded-xl p-3.5">
          <div class="flex items-center gap-2.5 mb-3 flex-wrap">
            <select v-model="form.recurringFrequency" class="text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white">
              <option value="weekly">Hebdomadaire</option>
              <option value="monthly">Mensuelle</option>
              <option value="yearly">Annuelle</option>
            </select>
            <select v-model.number="form.recurringCount" class="text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white">
              <option :value="3">3</option>
              <option :value="6">6</option>
              <option :value="12">12</option>
              <option :value="24">24</option>
            </select>
          </div>
          <div class="grid gap-2 max-h-[180px] overflow-y-auto">
            <div v-for="row in recurringRows" :key="row.index" class="flex items-center gap-2.5">
              <span class="w-[54px] text-[11px] text-slate-500 font-semibold">{{ row.indexLabel }}</span>
              <input type="date" :value="row.date" disabled class="flex-1 text-[12px] px-2.5 py-1.5 border border-slate-200 rounded-[7px] bg-slate-100 text-slate-500" />
              <span class="w-20 text-right text-[12px] font-semibold">{{ row.amountLabel }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-if="formError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">
        {{ formError }}
      </div>
      <div class="flex gap-2.5">
        <button type="button" :disabled="submitting" class="flex-1 bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer disabled:opacity-60" @click="submitForm">Ajouter</button>
        <button type="button" class="flex-1 bg-slate-100 text-slate-600 rounded-[10px] py-3.5 text-sm font-bold cursor-pointer" @click="closeForm">Annuler</button>
      </div>
    </ModalSheet>
  </div>

  <!-- Desktop -->
  <main v-else class="max-w-[1120px] w-full mx-auto px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-7">
      <h1 class="m-0 text-[28px] font-bold tracking-tight">Transactions</h1>
      <div class="flex items-center gap-4">
        <div class="text-[13px] text-slate-500">{{ transactions.length }} transactions</div>
        <select v-model="accountFilter" class="min-w-[220px] bg-white text-slate-900 text-sm font-medium px-3.5 py-2.5 border border-slate-200 rounded-lg shadow-sm">
          <option value="all">Tous les comptes</option>
          <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <select v-model="period" class="min-w-[200px] bg-white text-slate-900 text-sm font-medium px-3.5 py-2.5 border border-slate-200 rounded-lg shadow-sm">
          <option value="current">Ce mois</option>
          <option value="previous">Mois dernier</option>
          <option value="year">Cette année</option>
          <option value="custom">Personnalisé</option>
        </select>
        <button type="button" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2.5 text-sm font-semibold cursor-pointer" @click="openForm">
          <Icon name="plus" :stroke-width="2" />Nouvelle transaction
        </button>
      </div>
    </div>

    <div v-if="loadError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ loadError }}</div>
    <div v-if="listError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ listError }}</div>

    <ModalSheet v-if="showForm" title="Nouvelle transaction" max-width="640px" @close="closeForm">
      <div class="grid grid-cols-[2fr_1fr] gap-4 mb-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Libellé</label>
          <input v-model="form.label" type="text" placeholder="Ex. Supermarché, Loyer, Salaire..." class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Montant (€)</label>
          <input v-model="form.amount" type="number" step="0.01" min="0" placeholder="0,00" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Type</label>
          <div class="flex border border-slate-200 rounded-lg overflow-hidden">
            <button type="button" class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer" :class="form.type === 'expense' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" @click="form.type = 'expense'">Dépense</button>
            <button type="button" class="flex-1 py-2.5 text-[13px] font-semibold cursor-pointer" :class="form.type === 'income' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" @click="form.type = 'income'">Recette</button>
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Catégorie</label>
          <select v-model="form.category_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="c in categoriesStore.items" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Compte</label>
          <select v-model="form.account_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-[1fr_2fr] gap-4 mb-4 items-end">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Date</label>
          <input v-model="form.date" type="date" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-lg" />
        </div>
        <label class="flex items-center gap-2 text-[13px] text-slate-700 pb-2.5 cursor-pointer">
          <input type="checkbox" v-model="form.reconciled" class="w-4 h-4 accent-indigo-600" />
          Marquer comme pointée à l'ajout
        </label>
      </div>

      <div class="grid grid-cols-2 gap-4 mb-5">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Affecter à</label>
          <select v-model="form.linkType" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option value="none">Aucun</option>
            <option value="debt">Remboursement de dette</option>
            <option value="savings">Épargne</option>
          </select>
        </div>
        <div v-if="form.linkType === 'debt'">
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Dette concernée</label>
          <select v-model="form.linkedDebtId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="d in debtsStore.items" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div v-if="form.linkType === 'savings'">
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Compte d'épargne cible</label>
          <select v-model="form.savingsAccountId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="s in accountsStore.savings" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
      </div>

      <label class="flex items-center gap-2 text-[13px] font-semibold mb-3 cursor-pointer">
        <input type="checkbox" :checked="form.installment" @change="toggleInstallment($event.target.checked)" class="w-4 h-4 accent-indigo-600" />
        Paiement échelonné (ex. paiement en 4 fois)
      </label>
      <div v-if="form.installment" class="bg-slate-50 border border-slate-200 rounded-xl px-4.5 py-4 mb-4">
        <div class="flex items-center gap-3 mb-3.5">
          <label class="text-xs font-semibold text-slate-600">Nombre d'échéances</label>
          <select v-model.number="form.installmentCount" class="text-sm px-2.5 py-2 border border-slate-200 rounded-lg bg-white">
            <option :value="2">2 fois</option>
            <option :value="3">3 fois</option>
            <option :value="4">4 fois</option>
            <option :value="6">6 fois</option>
            <option :value="12">12 fois</option>
          </select>
          <span class="text-xs text-slate-400">un mois d'écart entre chaque échéance</span>
        </div>
        <div class="grid gap-2">
          <div v-for="row in installmentRows" :key="row.index" class="flex items-center gap-3">
            <span class="w-[70px] text-xs text-slate-500 font-semibold">{{ row.indexLabel }}</span>
            <input type="date" :value="row.date" disabled class="flex-1 text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-500" />
            <span class="w-[90px] text-right text-[13px] font-semibold">{{ row.amountLabel }}</span>
          </div>
        </div>
      </div>

      <label class="flex items-center gap-2 text-[13px] font-semibold mb-4 cursor-pointer">
        <input type="checkbox" :checked="form.recurring" @change="toggleRecurring($event.target.checked)" class="w-4 h-4 accent-indigo-600" />
        Transaction récurrente (facture, loyer, abonnement...)
      </label>
      <div v-if="form.recurring" class="bg-slate-50 border border-slate-200 rounded-xl px-4.5 py-4 mb-5">
        <div class="flex items-center gap-3 mb-3.5 flex-wrap">
          <label class="text-xs font-semibold text-slate-600">Fréquence</label>
          <select v-model="form.recurringFrequency" class="text-sm px-2.5 py-2 border border-slate-200 rounded-lg bg-white">
            <option value="weekly">Hebdomadaire</option>
            <option value="monthly">Mensuelle</option>
            <option value="yearly">Annuelle</option>
          </select>
          <label class="text-xs font-semibold text-slate-600">Occurrences</label>
          <select v-model.number="form.recurringCount" class="text-sm px-2.5 py-2 border border-slate-200 rounded-lg bg-white">
            <option :value="3">3</option>
            <option :value="6">6</option>
            <option :value="12">12</option>
            <option :value="24">24</option>
          </select>
          <span class="text-xs text-slate-400">montant plein à chaque échéance</span>
        </div>
        <div class="grid gap-2 max-h-[220px] overflow-y-auto">
          <div v-for="row in recurringRows" :key="row.index" class="flex items-center gap-3">
            <span class="w-[70px] text-xs text-slate-500 font-semibold">{{ row.indexLabel }}</span>
            <input type="date" :value="row.date" disabled class="flex-1 text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-500" />
            <span class="w-[90px] text-right text-[13px] font-semibold">{{ row.amountLabel }}</span>
          </div>
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

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <div class="grid gap-3 px-6 py-3 bg-slate-50 border-b border-slate-200 text-[11px] font-semibold tracking-wide uppercase text-slate-400" style="grid-template-columns: 40px 100px 2fr 1fr 1.3fr 110px 130px;">
        <span></span>
        <span>Date</span>
        <span>Libellé</span>
        <span>Catégorie</span>
        <span>Compte</span>
        <span class="text-right">Montant</span>
        <span class="text-right">Solde après op.</span>
      </div>
      <div
        v-for="t in transactions"
        :key="t.id"
        class="grid gap-3 px-6 py-3.5 border-b border-slate-100 items-center"
        style="grid-template-columns: 40px 100px 2fr 1fr 1.3fr 110px 130px;"
      >
        <button
          type="button"
          class="w-[22px] h-[22px] rounded-md border-[1.5px] text-white text-[13px] font-bold flex items-center justify-center cursor-pointer p-0"
          :class="t.reconciled ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
          aria-label="Basculer pointée"
          @click="toggleReconciled(t.id)"
        >{{ t.reconciled ? '✓' : '' }}</button>
        <span class="text-[13px] text-slate-500">{{ t.dateLabel }}</span>
        <span class="text-sm font-semibold">{{ t.label }}</span>
        <span class="flex gap-1.5 flex-wrap">
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ t.categoryName }}</span>
          <span v-if="t.hasLink" :title="t.linkTitle" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-yellow-100 text-yellow-800">{{ t.linkLabel }}</span>
        </span>
        <span class="text-[13px] text-slate-500">{{ t.accountName }}</span>
        <span class="text-right text-sm font-bold" :class="t.amountColor">{{ t.amountLabel }}</span>
        <span class="text-right text-[13px] text-slate-500">{{ t.runningBalanceLabel }}</span>
      </div>
    </section>
  </main>
</template>
