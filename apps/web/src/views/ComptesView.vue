<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useAccountsStore, ACCOUNT_TYPE_LABELS } from '../stores/accounts';
import { eur } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { extractErrorMessage } from '../lib/api';

const accountsStore = useAccountsStore();
const isMobile = useIsMobile();
const loadError = ref('');
const deleteError = ref('');

onMounted(async () => {
  try {
    await accountsStore.fetch();
  } catch {
    loadError.value = 'Impossible de charger les comptes.';
  }
});

const showForm = ref(false);
const editingId = ref(null);
const formError = ref('');
const submitting = ref(false);
function blankForm() {
  return { name: '', bank: '', type: 'checking', openingBalance: '' };
}
const form = reactive(blankForm());
const formTitle = computed(() => (editingId.value ? 'Modifier le compte' : 'Nouveau compte'));
const submitLabel = computed(() => (editingId.value ? 'Enregistrer' : 'Ajouter'));

const accounts = computed(() =>
  accountsStore.items.map((a) => ({
    ...a,
    typeLabel: ACCOUNT_TYPE_LABELS[a.type] || a.type,
    balanceLabel: eur(a.balance),
    hasPending: Math.abs(a.pending_encours) > 0.005,
    pendingLabel: `${a.pending_encours >= 0 ? '+' : ''}${eur(a.pending_encours)} non pointé`,
    ibanLabel: a.iban_last4 ? `IBAN se terminant par ${a.iban_last4}` : '',
  }))
);
const totalBalance = computed(() => accounts.value.reduce((s, a) => s + a.balance, 0));

function openForm() {
  editingId.value = null;
  formError.value = '';
  Object.assign(form, blankForm());
  showForm.value = true;
}
function openEditForm(acc) {
  editingId.value = acc.id;
  formError.value = '';
  Object.assign(form, { name: acc.name, bank: acc.bank || '', type: acc.type, openingBalance: acc.opening_balance });
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}
async function submitForm() {
  if (!form.name) return;
  formError.value = '';
  submitting.value = true;
  try {
    const payload = {
      name: form.name,
      bank: form.bank,
      type: form.type,
      openingBalance: Number(form.openingBalance) || 0,
    };
    if (editingId.value) {
      await accountsStore.update(editingId.value, payload);
    } else {
      await accountsStore.create(payload);
    }
    Object.assign(form, blankForm());
    showForm.value = false;
  } catch (e) {
    formError.value = extractErrorMessage(e);
  } finally {
    submitting.value = false;
  }
}

async function removeAccount(id) {
  if (!window.confirm('Supprimer ce compte ?')) return;
  deleteError.value = '';
  try {
    await accountsStore.remove(id);
  } catch (e) {
    deleteError.value = extractErrorMessage(e);
  }
}
</script>

<template>
  <!-- Mobile -->
  <div v-if="isMobile" class="flex-1 flex flex-col bg-slate-50">
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200 flex justify-between items-center">
      <div class="text-[19px] font-extrabold tracking-tight">Comptes</div>
      <button type="button" class="bg-indigo-50 text-indigo-600 rounded-lg px-3 py-2 text-xs font-bold flex items-center gap-1.5 cursor-pointer" @click="openForm">
        <Icon name="plus" :size="13" :stroke-width="2" />Ajouter
      </button>
    </header>

    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
      <div v-if="loadError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ loadError }}</div>
      <div v-if="deleteError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ deleteError }}</div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5">
        <div class="text-xs text-slate-500 mb-1">Solde total</div>
        <div class="text-[26px] font-extrabold tracking-tight">{{ eur(totalBalance) }}</div>
      </div>

      <div class="grid gap-2.5">
        <div v-for="acc in accounts" :key="acc.id" class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="flex justify-between items-start gap-2 mb-2">
            <div>
              <div class="text-[10px] font-semibold tracking-wide uppercase text-indigo-600 mb-0.5">{{ acc.bank }}</div>
              <div class="text-sm font-bold">{{ acc.name }}</div>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-indigo-50 text-indigo-700 whitespace-nowrap">{{ acc.typeLabel }}</span>
          </div>
          <div class="flex items-baseline gap-1.5 flex-wrap mb-2">
            <div class="text-lg font-extrabold">{{ acc.balanceLabel }}</div>
            <div v-if="acc.hasPending" class="text-[11px] text-slate-400">({{ acc.pendingLabel }})</div>
          </div>
          <div class="flex gap-2">
            <button type="button" class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 cursor-pointer" @click="openEditForm(acc)">
              <Icon name="edit" :size="11" />Modifier
            </button>
            <button type="button" class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-600 cursor-pointer" @click="removeAccount(acc.id)">
              <Icon name="trash" :size="11" />Supprimer
            </button>
          </div>
        </div>
      </div>
    </main>

    <ModalSheet v-if="showForm" mobile :title="formTitle" @close="closeForm">
      <div class="grid gap-3 mb-3.5">
        <input v-model="form.name" type="text" placeholder="Nom du compte" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]" />
        <input v-model="form.bank" type="text" placeholder="Banque" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]" />
        <select v-model="form.type" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option value="checking">Compte courant</option>
          <option value="savings">Épargne</option>
        </select>
        <input v-model="form.openingBalance" type="number" step="0.01" placeholder="Solde initial (€)" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px]" />
      </div>
      <div v-if="formError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">
        {{ formError }}
      </div>
      <div class="flex gap-2.5">
        <button type="button" :disabled="submitting" class="flex-1 bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer disabled:opacity-60" @click="submitForm">{{ submitLabel }}</button>
        <button type="button" class="flex-1 bg-slate-100 text-slate-600 rounded-[10px] py-3.5 text-sm font-bold cursor-pointer" @click="closeForm">Annuler</button>
      </div>
    </ModalSheet>
  </div>

  <!-- Desktop -->
  <main v-else class="max-w-[1120px] w-full mx-auto px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-2">
      <h1 class="m-0 text-[28px] font-bold tracking-tight">Comptes</h1>
      <button type="button" class="inline-flex items-center gap-1.5 bg-transparent border border-slate-200 rounded-lg px-3.5 py-2 text-[13px] font-semibold text-indigo-600 cursor-pointer hover:bg-slate-50" @click="openForm">
        <Icon name="plus" :size="14" :stroke-width="2" />Ajouter un compte
      </button>
    </div>
    <p class="mt-0 mb-7 text-sm text-slate-500">Soldes calculés à partir des opérations pointées, encours non pointé indiqué entre parenthèses.</p>

    <div v-if="loadError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ loadError }}</div>
    <div v-if="deleteError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ deleteError }}</div>

    <div class="flex items-baseline gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Solde total</div>
        <div class="text-4xl font-extrabold tracking-tight leading-none">{{ eur(totalBalance) }}</div>
      </div>
      <div class="text-[13px] text-slate-500">sur {{ accounts.length }} comptes</div>
    </div>

    <ModalSheet v-if="showForm" :title="formTitle" max-width="480px" @close="closeForm">
      <div class="grid gap-4 mb-5">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom du compte</label>
          <input v-model="form.name" type="text" placeholder="Ex. Compte courant" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Banque</label>
          <input v-model="form.bank" type="text" placeholder="Ex. BNP Paribas" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Type</label>
            <select v-model="form.type" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
              <option value="checking">Compte courant</option>
              <option value="savings">Épargne</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Solde initial (€)</label>
            <input v-model="form.openingBalance" type="number" step="0.01" placeholder="0,00" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg" />
          </div>
        </div>
      </div>
      <div v-if="formError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">
        {{ formError }}
      </div>
      <div class="flex gap-3">
        <button type="button" :disabled="submitting" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer disabled:opacity-60" @click="submitForm">
          <Icon name="plus" :stroke-width="2" />{{ submitLabel }}
        </button>
        <button type="button" class="inline-flex items-center gap-1.5 bg-transparent text-slate-600 border border-slate-200 rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50" @click="closeForm">
          <Icon name="close" :stroke-width="2" />Annuler
        </button>
      </div>
    </ModalSheet>

    <section class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
      <div v-for="acc in accounts" :key="acc.id" class="flex flex-col self-stretch gap-2.5 bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex justify-between items-start gap-2">
          <div>
            <div class="text-[11px] font-semibold tracking-wide uppercase text-indigo-600 mb-0.5">{{ acc.bank }}</div>
            <div class="text-base font-bold">{{ acc.name }}</div>
          </div>
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700 whitespace-nowrap">{{ acc.typeLabel }}</span>
        </div>
        <div class="mt-auto flex flex-col gap-1.5">
          <div class="flex items-baseline gap-2 flex-wrap">
            <div class="text-2xl font-extrabold tracking-tight">{{ acc.balanceLabel }}</div>
            <div v-if="acc.hasPending" class="text-[13px] text-slate-400">({{ acc.pendingLabel }})</div>
          </div>
          <div v-if="acc.ibanLabel" class="text-xs text-slate-400">{{ acc.ibanLabel }}</div>
        </div>
        <div class="flex gap-3 pt-1 border-t border-slate-100 mt-1 pt-2.5">
          <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 cursor-pointer hover:text-slate-900" @click="openEditForm(acc)">
            <Icon name="edit" :size="12" />Modifier
          </button>
          <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 cursor-pointer hover:text-red-700" @click="removeAccount(acc.id)">
            <Icon name="trash" :size="12" />Supprimer
          </button>
        </div>
      </div>
    </section>
  </main>
</template>
