<script setup>
import { computed } from 'vue';
import { useLedgerStore, CAT_COLORS } from '../stores/ledger';
import { eur, monthBoundsISO, TODAY } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import EditableAmount from '../components/EditableAmount.vue';

const store = useLedgerStore();
const isMobile = useIsMobile();

const monthLabel = TODAY.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

const spentByCategory = computed(() => {
  const { start, end } = monthBoundsISO();
  const spent = {};
  for (const t of store.transactions) {
    if (t.amount < 0 && t.date >= start && t.date <= end && t.category !== 'Revenus') {
      spent[t.category] = (spent[t.category] || 0) + Math.abs(t.amount);
    }
  }
  return spent;
});

function statusFor(pct) {
  if (pct >= 100) return { key: 'over', label: 'Dépassé', bar: 'bg-red-600', badge: 'bg-red-50 text-red-700' };
  if (pct >= 80) return { key: 'warn', label: 'Presque atteint', bar: 'bg-amber-600', badge: 'bg-amber-50 text-amber-700' };
  return { key: 'ok', label: 'Sous contrôle', bar: 'bg-indigo-600', badge: 'bg-indigo-50 text-indigo-700' };
}

const rows = computed(() =>
  store.expenseCategories.map((cat) => {
    const budget = store.budgets[cat] ?? 0;
    const spent = spentByCategory.value[cat] || 0;
    const pct = budget > 0 ? (spent / budget) * 100 : 0;
    const status = statusFor(pct);
    return {
      category: cat,
      color: CAT_COLORS[cat] || '#94a3b8',
      budget,
      budgetLabel: eur(budget),
      barWidthPct: Math.min(pct, 100).toFixed(1),
      barClass: status.bar,
      statusLabel: status.label,
      statusBadgeClass: status.badge,
      spentLabel: eur(spent),
      remainingLabel: budget - spent >= 0 ? `${eur(budget - spent)} restants` : `${eur(spent - budget)} de dépassement`,
    };
  })
);

const totalBudget = computed(() => Object.values(store.budgets).reduce((a, b) => a + (Number(b) || 0), 0));
const totalSpent = computed(() => Object.values(spentByCategory.value).reduce((a, b) => a + b, 0));
const overPct = computed(() => (totalBudget.value > 0 ? (totalSpent.value / totalBudget.value) * 100 : 0));
const overallStatus = computed(() => statusFor(overPct.value));

const hasIncome = computed(() => store.income > 0);
const isOverBudget = computed(() => hasIncome.value && totalBudget.value > store.income);
const budgetedPortionPct = computed(() => (hasIncome.value ? Math.min((totalBudget.value / store.income) * 100, 100) : 0));

const incomeSegments = computed(() =>
  store.expenseCategories.map((cat) => {
    const budget = store.budgets[cat] ?? 0;
    const widthPct = totalBudget.value > 0 ? (budget / totalBudget.value) * budgetedPortionPct.value : 0;
    return {
      category: cat,
      color: CAT_COLORS[cat] || '#94a3b8',
      widthPct: widthPct.toFixed(1),
      amountLabel: eur(budget),
      pctLabel: hasIncome.value ? `${Math.round((budget / store.income) * 100)}%` : '—',
    };
  })
);
const savings = computed(() => store.income - totalBudget.value);
const savingsPct = computed(() => (hasIncome.value && !isOverBudget.value ? 100 - budgetedPortionPct.value : 0));
</script>

<template>
  <!-- Mobile -->
  <div v-if="isMobile" class="flex-1 flex flex-col bg-slate-50">
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200">
      <div class="text-[19px] font-extrabold tracking-tight mb-1">Budgets</div>
      <div class="text-xs text-slate-500 capitalize">{{ monthLabel }}</div>
    </header>

    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
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
        <div v-for="row in rows" :key="row.category" class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
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
              @update:model-value="(v) => (store.budgets[row.category] = v)"
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
          :model-value="store.income"
          :display="`Revenu mensuel : ${eur(store.income)}`"
          variant="inline"
          suffix="€"
          :step="50"
          min="0"
          @update:model-value="(v) => (store.income = v)"
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
      <div v-for="row in rows" :key="row.category" class="px-6 py-5 border-b border-slate-100 last:border-b-0">
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
            @update:model-value="(v) => (store.budgets[row.category] = v)"
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
