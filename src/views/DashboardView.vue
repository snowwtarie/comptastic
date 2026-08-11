<script setup>
import { ref, computed } from 'vue';
import { useLedgerStore, CAT_COLOR_LIST } from '../stores/ledger';
import { eur } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';

// Illustrative period datasets for the charts (mirrors the design's demo data —
// the 10 seed transactions are too sparse to derive a meaningful weekly/monthly
// breakdown from, so both breakpoints share this single hardcoded series).
const PERIODS = {
  current: {
    label: 'Ce mois',
    bars: [
      { label: 'Sem. 1', income: 620, expense: 540 },
      { label: 'Sem. 2', income: 580, expense: 610 },
      { label: 'Sem. 3', income: 700, expense: 520 },
      { label: 'Sem. 4', income: 540, expense: 630 },
    ],
    categories: [
      { name: 'Logement', amount: 780 },
      { name: 'Alimentation', amount: 540 },
      { name: 'Transport', amount: 310 },
      { name: 'Loisirs', amount: 260 },
      { name: 'Santé', amount: 190 },
      { name: 'Autres', amount: 220 },
    ],
  },
  previous: {
    label: 'Mois dernier',
    bars: [
      { label: 'Sem. 1', income: 550, expense: 600 },
      { label: 'Sem. 2', income: 610, expense: 540 },
      { label: 'Sem. 3', income: 590, expense: 560 },
      { label: 'Sem. 4', income: 600, expense: 700 },
    ],
    categories: [
      { name: 'Logement', amount: 780 },
      { name: 'Alimentation', amount: 610 },
      { name: 'Transport', amount: 340 },
      { name: 'Loisirs', amount: 300 },
      { name: 'Santé', amount: 175 },
      { name: 'Autres', amount: 195 },
    ],
  },
  year: {
    label: 'Cette année',
    bars: [
      { label: 'Jan', income: 2200, expense: 1900 },
      { label: 'Fév', income: 2100, expense: 2300 },
      { label: 'Mar', income: 2400, expense: 2000 },
      { label: 'Avr', income: 2250, expense: 2150 },
      { label: 'Mai', income: 2300, expense: 1950 },
      { label: 'Juin', income: 2500, expense: 2600 },
      { label: 'Juil', income: 2600, expense: 2800 },
      { label: 'Août', income: 2150, expense: 1900 },
      { label: 'Sep', income: 2400, expense: 2100 },
      { label: 'Oct', income: 2350, expense: 2250 },
      { label: 'Nov', income: 2450, expense: 2300 },
      { label: 'Déc', income: 2700, expense: 3100 },
    ],
    categories: [
      { name: 'Logement', amount: 9200 },
      { name: 'Alimentation', amount: 6400 },
      { name: 'Transport', amount: 3600 },
      { name: 'Loisirs', amount: 3800 },
      { name: 'Santé', amount: 2100 },
      { name: 'Autres', amount: 2250 },
    ],
  },
};
PERIODS.custom = PERIODS.current;

const store = useLedgerStore();
const isMobile = useIsMobile();

const period = ref('current');
const showAll = ref(false);
const data = computed(() => PERIODS[period.value] || PERIODS.current);

const accountsWithLabel = computed(() =>
  store.accountBalances().map((a) => ({
    ...a,
    balanceLabel: eur(a.balance),
    hasPending: Math.abs(a.pendingEncours) > 0.005,
    pendingLabel: `${a.pendingEncours >= 0 ? '+' : ''}${eur(a.pendingEncours)} non pointé`,
  }))
);
const totalBalance = computed(() => accountsWithLabel.value.reduce((s, a) => s + a.balance, 0));
const visibleAccounts = computed(() => (showAll.value ? accountsWithLabel.value : accountsWithLabel.value.slice(0, 3)));
const hasMoreAccounts = computed(() => accountsWithLabel.value.length > 3);
const toggleLabel = computed(() => (showAll.value ? 'Voir moins' : `Voir plus (${accountsWithLabel.value.length - 3})`));

// Desktop bar + net-line chart
const chart = computed(() => {
  const bars = data.value.bars;
  const maxTotal = Math.max(...bars.map((b) => b.income + b.expense));
  const scale = 260 / maxTotal;
  const points = bars.map((b, i) => {
    const net = b.income - b.expense;
    return {
      label: b.label,
      incomeH: Math.round(b.income * scale),
      expenseH: Math.round(b.expense * scale),
      leftPct: ((i + 0.5) / bars.length) * 100,
      netDotTop: Math.round(260 - net * scale) - 4,
    };
  });
  const netPolylinePoints = bars
    .map((b, i) => {
      const net = b.income - b.expense;
      const x = ((i + 0.5) / bars.length) * 100;
      const y = 260 - net * scale;
      return `${x},${y}`;
    })
    .join(' ');
  return { bars: points, netPolylinePoints };
});

// Desktop category donut (all categories, with legend)
const categoryChart = computed(() => {
  const catTotal = data.value.categories.reduce((s, c) => s + c.amount, 0);
  const sorted = [...data.value.categories].sort((a, b) => b.amount - a.amount);
  let cum = 0;
  const gradientParts = [];
  const categories = sorted.map((c, i) => {
    const pct = (c.amount / catTotal) * 100;
    const start = cum;
    cum += pct;
    const color = CAT_COLOR_LIST[i % CAT_COLOR_LIST.length];
    gradientParts.push(`${color} ${start.toFixed(2)}% ${cum.toFixed(2)}%`);
    return { name: c.name, color, amountLabel: eur(c.amount, 0), pctLabel: `${Math.round(pct)}%` };
  });
  return { categories, donutGradient: `conic-gradient(${gradientParts.join(', ')})`, categoryTotalLabel: eur(catTotal, 0) };
});

// Desktop trend badge: spending this month vs last month (fixed comparison, independent of selected period)
const curExpense = PERIODS.current.categories.reduce((s, c) => s + c.amount, 0);
const prevExpense = PERIODS.previous.categories.reduce((s, c) => s + c.amount, 0);
const trendPct = ((prevExpense - curExpense) / prevExpense) * 100;
const trendPositive = trendPct >= 0;
const trendLabel = `${trendPositive ? '-' : '+'}${Math.abs(trendPct).toFixed(1)}% de dépenses vs mois dernier`;

// Mobile summary: totals + top categories for the selected period
const mobileSummary = computed(() => {
  const bars = data.value.bars;
  const income = bars.reduce((s, b) => s + b.income, 0);
  const expense = bars.reduce((s, b) => s + b.expense, 0);
  const maxIE = Math.max(income, expense, 1);
  const catTotal = data.value.categories.reduce((s, c) => s + c.amount, 0);
  const sorted = [...data.value.categories].sort((a, b) => b.amount - a.amount);
  let cum = 0;
  const gradientParts = [];
  const topCategories = sorted.slice(0, 4).map((c, i) => {
    const pct = (c.amount / catTotal) * 100;
    const start = cum;
    cum += pct;
    const color = CAT_COLOR_LIST[i % CAT_COLOR_LIST.length];
    gradientParts.push(`${color} ${start.toFixed(1)}% ${cum.toFixed(1)}%`);
    return { name: c.name, color, pctLabel: `${Math.round(pct)}%` };
  });
  if (cum < 100) gradientParts.push(`#e2e8f0 ${cum.toFixed(1)}% 100%`);
  const surplusPositive = income >= expense;
  return {
    incomeLabel: eur(income, 0),
    expenseLabel: eur(expense, 0),
    incomeBarPct: ((income / maxIE) * 100).toFixed(1),
    expenseBarPct: ((expense / maxIE) * 100).toFixed(1),
    donutGradient: `conic-gradient(${gradientParts.join(', ')})`,
    topCategories,
    trendLabelShort: surplusPositive ? 'Épargne +' : 'Déficit',
    trendPositive: surplusPositive,
  };
});
</script>

<template>
  <!-- Mobile -->
  <div v-if="isMobile" class="flex-1 flex flex-col bg-slate-50">
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200">
      <div class="flex justify-between items-center mb-2.5">
        <span class="text-[17px] font-extrabold tracking-tight">Comptastic</span>
        <select v-model="period" class="text-xs font-semibold text-indigo-600 bg-indigo-50 border-none rounded-lg px-2.5 py-1.5">
          <option value="current">Ce mois</option>
          <option value="previous">Mois dernier</option>
          <option value="year">Cette année</option>
        </select>
      </div>
      <div class="text-xs text-slate-500">Solde total</div>
      <div class="flex items-baseline gap-2">
        <div class="text-[26px] font-extrabold tracking-tight">{{ eur(totalBalance) }}</div>
        <span
          class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
          :class="mobileSummary.trendPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
        >{{ mobileSummary.trendLabelShort }}</span>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto px-4 pt-4 pb-24">
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5">
        <div class="text-[13px] font-bold mb-3">Recettes vs dépenses</div>
        <div class="flex gap-4 mb-2.5">
          <div class="flex-1">
            <div class="text-[11px] text-slate-500 mb-1">Recettes</div>
            <div class="text-lg font-extrabold text-emerald-700">{{ mobileSummary.incomeLabel }}</div>
          </div>
          <div class="flex-1">
            <div class="text-[11px] text-slate-500 mb-1">Dépenses</div>
            <div class="text-lg font-extrabold">{{ mobileSummary.expenseLabel }}</div>
          </div>
        </div>
        <div class="flex h-2.5 rounded-full overflow-hidden bg-slate-100">
          <div class="bg-indigo-600" :style="{ width: mobileSummary.incomeBarPct + '%' }"></div>
          <div class="bg-indigo-200" :style="{ width: mobileSummary.expenseBarPct + '%' }"></div>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5">
        <div class="text-[13px] font-bold mb-3">Dépenses par catégorie</div>
        <div class="flex items-center gap-4">
          <div class="relative w-24 h-24 shrink-0">
            <div class="w-full h-full rounded-full" :style="{ background: mobileSummary.donutGradient }"></div>
            <div class="absolute inset-[24%] rounded-full bg-white"></div>
          </div>
          <div class="flex-1 grid gap-2">
            <div v-for="c in mobileSummary.topCategories" :key="c.name" class="flex items-center gap-1.5 text-xs">
              <span class="w-2 h-2 rounded-sm shrink-0" :style="{ background: c.color }"></span>
              <span class="flex-1">{{ c.name }}</span>
              <span class="font-semibold">{{ c.pctLabel }}</span>
            </div>
          </div>
        </div>
      </section>

      <section>
        <div class="flex justify-between items-center mb-2.5">
          <div class="text-[13px] font-bold">Comptes</div>
          <router-link to="/comptes" class="text-xs font-semibold text-indigo-600">Tout voir</router-link>
        </div>
        <div class="grid gap-2.5">
          <div
            v-for="acc in accountsWithLabel.slice(0, 3)"
            :key="acc.id"
            class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5 flex justify-between items-center"
          >
            <div>
              <div class="text-[10px] font-semibold tracking-wide uppercase text-indigo-600 mb-0.5">{{ acc.bank }}</div>
              <div class="text-sm font-bold">{{ acc.name }}</div>
            </div>
            <div class="text-base font-extrabold">{{ acc.balanceLabel }}</div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Desktop -->
  <main v-else class="max-w-[1280px] w-full mx-auto px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-8">
      <h1 class="m-0 text-[28px] font-bold tracking-tight">Tableau de bord</h1>
      <select v-model="period" class="min-w-[200px] bg-white text-slate-900 text-sm font-medium px-3.5 py-2.5 border border-slate-200 rounded-lg shadow-sm">
        <option value="current">Ce mois</option>
        <option value="previous">Mois dernier</option>
        <option value="year">Cette année</option>
        <option value="custom">Personnalisé</option>
      </select>
    </div>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-8">
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Solde total</div>
        <div class="text-4xl font-extrabold tracking-tight leading-none">{{ eur(totalBalance) }}</div>
      </div>
      <span
        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
        :class="trendPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
      >{{ trendLabel }}</span>
      <div class="text-[13px] text-slate-500">sur {{ accountsWithLabel.length }} comptes</div>
    </div>

    <div class="grid grid-cols-[1.3fr_1fr] gap-6 mb-8">
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6">
        <h2 class="text-base font-bold m-0 mb-5">Dépenses et recettes</h2>
        <div class="relative h-[300px]">
          <div class="absolute inset-0 flex items-end gap-4 pb-10 box-border">
            <div v-for="b in chart.bars" :key="b.label" class="flex-1 h-full flex flex-col-reverse">
              <div class="w-full bg-indigo-200 rounded-t-md" :style="{ height: b.expenseH + 'px' }"></div>
              <div class="w-full bg-indigo-600" :style="{ height: b.incomeH + 'px' }"></div>
            </div>
          </div>
          <svg viewBox="0 0 100 300" preserveAspectRatio="none" class="absolute inset-0 w-full h-full pointer-events-none">
            <polyline :points="chart.netPolylinePoints" fill="none" stroke="#0f172a" stroke-width="2" vector-effect="non-scaling-stroke" />
          </svg>
          <div
            v-for="b in chart.bars"
            :key="'dot-' + b.label"
            class="absolute w-2 h-2 rounded-full bg-slate-900 shadow-[0_0_0_2px_#ffffff]"
            :style="{ left: `calc(${b.leftPct}% - 4px)`, top: b.netDotTop + 'px' }"
          ></div>
        </div>
        <div class="flex gap-4 mt-2">
          <div v-for="b in chart.bars" :key="'lbl-' + b.label" class="flex-1 text-center text-xs text-slate-400 font-medium">{{ b.label }}</div>
        </div>
        <div class="flex gap-5 mt-4 text-xs text-slate-600">
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-indigo-600 inline-block rounded"></span>Recettes</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-indigo-200 inline-block rounded"></span>Dépenses</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-0.5 bg-slate-900 inline-block"></span>Solde net</span>
        </div>
      </section>

      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6">
        <h2 class="text-base font-bold m-0 mb-5">Dépenses par catégorie</h2>
        <div class="flex flex-col items-center gap-6">
          <div class="relative w-[180px] h-[180px] shrink-0">
            <div class="w-full h-full rounded-full" :style="{ background: categoryChart.donutGradient }"></div>
            <div class="absolute inset-[22%] rounded-full bg-white flex flex-col items-center justify-center">
              <div class="text-lg font-extrabold">{{ categoryChart.categoryTotalLabel }}</div>
              <div class="text-[11px] text-slate-400">dépensés</div>
            </div>
          </div>
          <div class="w-full grid gap-3">
            <div v-for="c in categoryChart.categories" :key="c.name" class="flex items-center gap-2 text-[13px]">
              <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: c.color }"></span>
              <span class="flex-1 text-slate-700">{{ c.name }}</span>
              <span class="text-slate-400 w-[38px] text-right">{{ c.pctLabel }}</span>
              <span class="font-semibold w-20 text-right">{{ c.amountLabel }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section>
      <h2 class="text-base font-bold m-0 mb-4">Comptes</h2>
      <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
        <div
          v-for="acc in visibleAccounts"
          :key="acc.id"
          class="flex flex-col self-stretch gap-2.5 bg-white border border-slate-200 rounded-xl shadow-sm p-5"
        >
          <div class="flex justify-between items-start gap-2">
            <div>
              <div class="text-[11px] font-semibold tracking-wide uppercase text-indigo-600 mb-0.5">{{ acc.bank }}</div>
              <div class="text-base font-bold">{{ acc.name }}</div>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700 whitespace-nowrap">{{ acc.type }}</span>
          </div>
          <div class="mt-auto flex flex-col gap-1.5">
            <div class="flex items-baseline gap-2 flex-wrap">
              <div class="text-2xl font-extrabold tracking-tight">{{ acc.balanceLabel }}</div>
              <div v-if="acc.hasPending" class="text-[13px] text-slate-400">({{ acc.pendingLabel }})</div>
            </div>
            <div class="text-xs text-slate-400">{{ acc.iban }}</div>
          </div>
        </div>
      </div>
      <button
        v-if="hasMoreAccounts"
        type="button"
        class="mt-4 inline-flex items-center gap-1.5 bg-transparent border-none text-indigo-600 text-sm font-semibold cursor-pointer px-1 py-2"
        @click="showAll = !showAll"
      >
        <Icon name="chevron" :size="14" :stroke-width="2" :style="{ transform: showAll ? 'rotate(180deg)' : '' }" />{{ toggleLabel }}
      </button>
    </section>
  </main>
</template>
