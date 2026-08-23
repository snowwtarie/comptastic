<script setup>
import { computed, ref, onMounted } from 'vue';
import { useBudgetsStore } from '../stores/budgets';
import { useSettingsStore } from '../stores/settings';
import { eur, TODAY } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import EditableAmount from '../components/EditableAmount.vue';
import { ApiError } from '../lib/api';

const budgetsStore = useBudgetsStore();
const settingsStore = useSettingsStore();
const isMobile = useIsMobile();
const loadError = ref('');

onMounted(async () => {
  try {
    await Promise.all([budgetsStore.fetch(), settingsStore.fetch()]);
  } catch {
    loadError.value = 'Impossible de charger les budgets.';
  }
});

const monthLabel = TODAY.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

const STATUS_STYLES = {
  over: { label: 'Dépassé', bar: 'bg-red-600', badge: 'bg-red-50 text-red-700' },
  warn: { label: 'Presque atteint', bar: 'bg-amber-600', badge: 'bg-amber-50 text-amber-700' },
  ok: { label: 'Sous contrôle', bar: 'bg-indigo-600', badge: 'bg-indigo-50 text-indigo-700' },
};

const rows = computed(() =>
  budgetsStore.rows.map((row) => {
    const style = STATUS_STYLES[row.status];
    return {
      categoryId: row.category_id,
      category: row.name,
      color: row.color_hex,
      budget: row.budget,
      budgetLabel: eur(row.budget),
      barWidthPct: Math.min(row.pct, 100).toFixed(1),
      barClass: style.bar,
      statusLabel: style.label,
      statusBadgeClass: style.badge,
      spentLabel: eur(row.spent),
      remainingLabel: row.budget - row.spent >= 0 ? `${eur(row.budget - row.spent)} restants` : `${eur(row.spent - row.budget)} de dépassement`,
    };
  })
);

const rowError = ref('');
async function updateBudget(categoryId, value) {
  rowError.value = '';
  try {
    await budgetsStore.update(categoryId, value);
  } catch (e) {
    rowError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}

const totalBudget = computed(() => budgetsStore.rows.reduce((s, r) => s + r.budget, 0));
const totalSpent = computed(() => budgetsStore.rows.reduce((s, r) => s + r.spent, 0));
const overPct = computed(() => (totalBudget.value > 0 ? (totalSpent.value / totalBudget.value) * 100 : 0));
const overallStatus = computed(() => {
  const key = overPct.value >= 100 ? 'over' : overPct.value >= 80 ? 'warn' : 'ok';
  return { key, ...STATUS_STYLES[key] };
});

const hasIncome = computed(() => settingsStore.income > 0);
const isOverBudget = computed(() => hasIncome.value && totalBudget.value > settingsStore.income);
const budgetedPortionPct = computed(() => (hasIncome.value ? Math.min((totalBudget.value / settingsStore.income) * 100, 100) : 0));

const incomeSegments = computed(() =>
  rows.value.map((row) => {
    const widthPct = totalBudget.value > 0 ? (row.budget / totalBudget.value) * budgetedPortionPct.value : 0;
    return {
      category: row.category,
      color: row.color,
      widthPct: widthPct.toFixed(1),
      amountLabel: row.budgetLabel,
      pctLabel: hasIncome.value ? `${Math.round((row.budget / settingsStore.income) * 100)}%` : '—',
    };
  })
);
const savings = computed(() => settingsStore.income - totalBudget.value);
const savingsPct = computed(() => (hasIncome.value && !isOverBudget.value ? 100 - budgetedPortionPct.value : 0));

async function updateIncome(value) {
  rowError.value = '';
  try {
    await settingsStore.update({ income: value });
  } catch (e) {
    rowError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}
</script>

<template>
  <!-- Mobile -->
  <div v-if="isMobile" class="flex-1 flex flex-col bg-slate-50">
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200">
      <div class="text-[19px] font-extrabold tracking-tight mb-1">Budgets</div>
      <div class="text-xs text-slate-500 capitalize">{{ monthLabel }}</div>
    </header>

    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
      <div v-if="loadError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ loadError }}</div>
      <div v-if="rowError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ rowError }}</div>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5 flex justify-between items-center">
        <div>
          <div class="text-[11px] text-slate-500 mb-1">Budgété</div>
          <div class="text-lg font-extrabold">{{ eur(totalBudget) }}</div>
        </div>
        <div>
          <div class="text-[11px] text-slate-500 mb-1 text-right">Dépensé</div>
          <div class="text-lg font-extrabold text-right" :class="totalSpent > totalBudget ? 'text-red-600' : ''">{{ eur(totalSpent) }}</div>
        </div>
      </div>

      <div class="grid gap-2.5">
        <div v-for="row in rows" :key="row.categoryId" class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="flex justify-between items-center mb-2">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-sm" :style="{ background: row.color }"></span>
              <span class="text-[13px] font-bold">{{ row.category }}</span>
            </div>
            <EditableAmount
              :model-value="row.budget"
              :display="row.budgetLabel"
              variant="inline"
              compact
              :step="10"
              min="0"
              @update:model-value="(v) => updateBudget(row.categoryId, v)"
            />
          </div>
          <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden mb-1.5">
            <div class="h-full rounded-full" :class="row.barClass" :style="{ width: row.barWidthPct + '%' }"></div>
          </div>
          <div class="flex justify-between text-[11px] text-slate-500">
            <span>{{ row.spentLabel }} dépensés</span>
            <span>{{ row.remainingLabel }}</span>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Desktop -->
  <main v-else class="max-w-[960px] w-full mx-auto px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-2">
      <h1 class="m-0 text-[28px] font-bold tracking-tight">Budgets</h1>
      <div class="text-[13px] text-slate-500 capitalize">{{ monthLabel }}</div>
    </div>
    <p class="mt-0 mb-7 text-sm text-slate-500">Définissez une enveloppe mensuelle par catégorie et suivez sa consommation en direct.</p>

    <div v-if="loadError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ loadError }}</div>
    <div v-if="rowError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ rowError }}</div>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Budgété ce mois</div>
        <div class="text-[30px] font-extrabold tracking-tight leading-none">{{ eur(totalBudget) }}</div>
      </div>
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Dépensé ce mois</div>
        <div class="text-[30px] font-extrabold tracking-tight leading-none" :class="overallStatus.key === 'over' ? 'text-red-600' : ''">{{ eur(totalSpent) }}</div>
      </div>
      <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="overallStatus.badge">{{ Math.round(overPct) }}% du budget global consommé</span>
    </div>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
      <div class="flex justify-between items-center gap-4 flex-wrap mb-4">
        <h2 class="text-base font-bold m-0">Répartition du revenu</h2>
        <EditableAmount
          :model-value="settingsStore.income"
          :display="`Revenu mensuel : ${eur(settingsStore.income)}`"
          variant="inline"
          suffix="€"
          :step="50"
          min="0"
          @update:model-value="updateIncome"
        />
      </div>

      <div v-if="hasIncome">
        <div class="w-full h-7 rounded-lg overflow-hidden flex bg-slate-100 mb-3.5">
          <div v-for="seg in incomeSegments" :key="seg.category" class="h-full" :style="{ background: seg.color, width: seg.widthPct + '%' }"></div>
          <div class="h-full bg-emerald-500" :style="{ width: savingsPct + '%' }"></div>
        </div>
        <div class="flex flex-wrap gap-x-6 gap-y-3">
          <div v-for="seg in incomeSegments" :key="'lbl-' + seg.category" class="flex items-center gap-1.5 text-xs text-slate-600">
            <span class="w-[9px] h-[9px] rounded-sm shrink-0" :style="{ background: seg.color }"></span>
            {{ seg.category }} · {{ seg.amountLabel }} ({{ seg.pctLabel }})
          </div>
          <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
            <span class="w-[9px] h-[9px] rounded-sm shrink-0 bg-emerald-500"></span>
            Épargne possible · {{ eur(Math.max(savings, 0)) }} ({{ Math.round(savingsPct) }}%)
          </div>
        </div>
        <p v-if="isOverBudget" class="mt-3.5 mb-0 text-[13px] text-red-700 font-semibold">
          Le budget prévu dépasse le revenu saisi de {{ eur(Math.abs(savings)) }} — aucune épargne possible ce mois-ci.
        </p>
      </div>
      <p v-else class="m-0 text-[13px] text-slate-400">
        Saisissez votre revenu mensuel pour visualiser la répartition des dépenses budgétisées et l'épargne possible.
      </p>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <div v-for="row in rows" :key="row.categoryId" class="px-6 py-5 border-b border-slate-100 last:border-b-0">
        <div class="flex justify-between items-center gap-4 flex-wrap mb-3">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: row.color }"></span>
            <span class="text-[15px] font-bold">{{ row.category }}</span>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="row.statusBadgeClass">{{ row.statusLabel }}</span>
          </div>
          <EditableAmount
            :model-value="row.budget"
            :display="`${row.budgetLabel} / mois`"
            variant="inline"
            suffix="€"
            :step="10"
            min="0"
            @update:model-value="(v) => updateBudget(row.categoryId, v)"
          />
        </div>
        <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden mb-2.5">
          <div class="h-full rounded-full" :class="row.barClass" :style="{ width: row.barWidthPct + '%' }"></div>
        </div>
        <div class="flex justify-between text-[13px] text-slate-500">
          <span><strong class="text-slate-900">{{ row.spentLabel }}</strong> dépensés</span>
          <span>{{ row.remainingLabel }}</span>
        </div>
      </div>
    </section>
  </main>
</template>
