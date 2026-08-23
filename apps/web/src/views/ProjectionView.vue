<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useProjectionStore } from '../stores/projection';
import { useSettingsStore } from '../stores/settings';
import { eur, TODAY } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import EditableAmount from '../components/EditableAmount.vue';
import { ApiError } from '../lib/api';

const projectionStore = useProjectionStore();
const settingsStore = useSettingsStore();
const isMobile = useIsMobile();

const horizon = ref(12);
const settingsError = ref('');
const loadError = ref('');

onMounted(async () => {
  try {
    await Promise.all([settingsStore.fetch(), projectionStore.fetch(horizon.value)]);
  } catch {
    loadError.value = 'Impossible de charger la projection.';
  }
});
watch(horizon, async (h) => {
  try {
    await projectionStore.fetch(h);
  } catch {
    loadError.value = 'Impossible de charger la projection.';
  }
});

const historyPoints = computed(() => projectionStore.history.map((p) => ({ monthOffset: p.month_offset, value: p.balance })));
const currentSavings = computed(() => (historyPoints.value.length ? historyPoints.value[historyPoints.value.length - 1].value : 0));
const projected = computed(() => projectionStore.projection);

const svgDims = computed(() => (isMobile.value ? { w: 320, h: 150, pad: 6 } : { w: 600, h: 260, pad: 8 }));

const chart = computed(() => {
  const { w, h, pad } = svgDims.value;
  const history = historyPoints.value;
  if (!history.length || !projected.value.length) return { w, h, historyLinePoints: '', projectionLinePoints: '', areaPoints: '', todayX: '0', axisLabels: [] };
  const historyStart = history[0].monthOffset;
  const totalSpanMonths = horizon.value - historyStart;
  const allValues = [...history.map((p) => p.value), ...projected.value];
  const maxVal = Math.max(...allValues, 1);
  const minVal = Math.min(...allValues, 0);
  const range = maxVal - minVal || 1;
  const xFor = (mo) => ((mo - historyStart) / totalSpanMonths) * w;
  const yFor = (v) => h - pad - ((v - minVal) / range) * (h - 2 * pad);

  const historyXY = history.map((p) => [xFor(p.monthOffset), yFor(p.value)]);
  const historyLinePoints = historyXY.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const projectionXY = projected.value.map((v, i) => [xFor(i), yFor(v)]);
  const projectionLinePoints = projectionXY.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const fullXY = [...historyXY, ...projectionXY];
  const areaPoints = `0,${h} ` + fullXY.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ') + ` ${w},${h}`;
  const todayX = xFor(0).toFixed(1);

  const axisLabels = [historyStart, Math.round(historyStart / 2), 0, Math.round(horizon.value / 2), horizon.value].map((off) => {
    const d = new Date(TODAY.getFullYear(), TODAY.getMonth() + off, 1);
    return d.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
  });

  return { w, h, historyLinePoints, projectionLinePoints, areaPoints, todayX, axisLabels };
});

const finalAmountLabel = computed(() => (projected.value.length ? eur(projected.value[horizon.value], 0) : eur(0, 0)));
const milestone6Label = computed(() => (projected.value.length ? eur(projected.value[Math.min(6, horizon.value)], 0) : eur(0, 0)));
const milestone12Label = computed(() => (projected.value.length ? eur(projected.value[Math.min(12, horizon.value)], 0) : eur(0, 0)));
const totalContributedLabel = computed(() => eur(settingsStore.monthlySavingsContribution * horizon.value, 0));
const contributionColor = computed(() => (settingsStore.monthlySavingsContribution >= 0 ? 'text-slate-900' : 'text-red-600'));

let updateChain = Promise.resolve();
function queueSettingsUpdate(fn) {
  updateChain = updateChain.then(fn, fn);
  return updateChain;
}

async function updateContribution(value) {
  return queueSettingsUpdate(async () => {
    settingsError.value = '';
    try {
      await settingsStore.update({ monthlySavingsContribution: value });
      await projectionStore.fetch(horizon.value);
    } catch (e) {
      settingsError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
    }
  });
}
async function updateRate(value) {
  return queueSettingsUpdate(async () => {
    settingsError.value = '';
    try {
      await settingsStore.update({ annualReturnRate: value });
      await projectionStore.fetch(horizon.value);
    } catch (e) {
      settingsError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
    }
  });
}
</script>

<template>
  <!-- Mobile -->
  <div v-if="isMobile" class="flex-1 flex flex-col bg-slate-50">
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200">
      <div class="text-[19px] font-extrabold tracking-tight mb-1">Projection</div>
      <div class="text-xs text-slate-500">Épargne actuelle : {{ eur(currentSavings, 0) }}</div>
    </header>

    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
      <div v-if="loadError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ loadError }}</div>
      <div v-if="settingsError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ settingsError }}</div>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5">
        <div class="flex gap-2.5 mb-3">
          <select v-model.number="horizon" class="flex-1 text-xs font-semibold text-slate-900 bg-slate-100 border-none rounded-lg px-2.5 py-2">
            <option :value="6">6 mois</option>
            <option :value="12">12 mois</option>
            <option :value="24">24 mois</option>
            <option :value="36">36 mois</option>
          </select>
        </div>
        <div class="flex gap-2.5 mb-3.5">
          <div class="flex-1">
            <div class="text-[10px] text-slate-500 mb-1">Effort mensuel</div>
            <EditableAmount
              :model-value="settingsStore.monthlySavingsContribution"
              :display="`${settingsStore.monthlySavingsContribution >= 0 ? '+' : ''}${eur(settingsStore.monthlySavingsContribution, 0)}/m`"
              compact
              :step="10"
              @update:model-value="updateContribution"
            />
          </div>
          <div class="flex-1">
            <div class="text-[10px] text-slate-500 mb-1">Taux annuel</div>
            <EditableAmount
              :model-value="settingsStore.annualReturnRate"
              :display="`${settingsStore.annualReturnRate}%`"
              compact
              :step="0.1"
              min="0"
              @update:model-value="updateRate"
            />
          </div>
        </div>
        <div class="relative h-[150px]">
          <svg :viewBox="`0 0 ${chart.w} ${chart.h}`" preserveAspectRatio="none" class="absolute inset-0 w-full h-full">
            <polygon :points="chart.areaPoints" fill="#eef2ff" />
            <line :x1="chart.todayX" y1="0" :x2="chart.todayX" :y2="chart.h" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
            <polyline :points="chart.historyLinePoints" fill="none" stroke="#0f172a" stroke-width="2.5" vector-effect="non-scaling-stroke" />
            <polyline :points="chart.projectionLinePoints" fill="none" stroke="#4f46e5" stroke-width="2.5" stroke-dasharray="6 5" vector-effect="non-scaling-stroke" />
          </svg>
        </div>
        <div class="text-xs text-slate-500 mt-2.5">Dans {{ horizon }} mois : <strong class="text-slate-900">{{ finalAmountLabel }}</strong></div>
      </div>

      <div class="grid grid-cols-2 gap-2.5">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="text-[10px] text-slate-500 mb-1">Dans 6 mois</div>
          <div class="text-base font-extrabold">{{ milestone6Label }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="text-[10px] text-slate-500 mb-1">Dans 12 mois</div>
          <div class="text-base font-extrabold">{{ milestone12Label }}</div>
        </div>
      </div>
    </main>
  </div>

  <!-- Desktop -->
  <main v-else class="max-w-[1120px] w-full mx-auto px-8 pt-10 pb-14">
    <h1 class="m-0 mb-2 text-[28px] font-bold tracking-tight">Projection d'épargne</h1>
    <p class="mt-0 mb-7 text-sm text-slate-500">Historique réel des soldes d'épargne (tous comptes de type Épargne) et projection selon votre effort mensuel et un taux de rendement.</p>

    <div v-if="loadError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ loadError }}</div>
    <div v-if="settingsError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ settingsError }}</div>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Épargne actuelle</div>
        <div class="text-[28px] font-extrabold tracking-tight leading-none">{{ eur(currentSavings, 0) }}</div>
      </div>
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Effort d'épargne mensuel</div>
        <EditableAmount
          :model-value="settingsStore.monthlySavingsContribution"
          :display="`${settingsStore.monthlySavingsContribution >= 0 ? '+' : ''}${eur(settingsStore.monthlySavingsContribution, 0)} / mois`"
          suffix="€ / mois"
          :step="10"
          :value-class="contributionColor"
          @update:model-value="updateContribution"
        />
      </div>
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Taux de rendement annuel</div>
        <EditableAmount
          :model-value="settingsStore.annualReturnRate"
          :display="`${settingsStore.annualReturnRate}%`"
          suffix="%"
          :step="0.1"
          min="0"
          @update:model-value="updateRate"
        />
      </div>
      <div class="ml-auto">
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Horizon</label>
        <select v-model.number="horizon" class="min-w-[160px] bg-white text-slate-900 text-sm font-medium px-3 py-2.5 border border-slate-200 rounded-lg">
          <option :value="6">6 mois</option>
          <option :value="12">12 mois</option>
          <option :value="24">24 mois</option>
          <option :value="36">36 mois</option>
        </select>
      </div>
    </div>

    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
      <div class="flex justify-between items-baseline mb-4 flex-wrap gap-2">
        <h2 class="text-base font-bold m-0">Évolution réelle et projetée</h2>
        <div class="text-[13px] text-slate-500">Dans {{ horizon }} mois : <strong class="text-slate-900">{{ finalAmountLabel }}</strong></div>
      </div>
      <div class="relative h-[260px]">
        <svg :viewBox="`0 0 ${chart.w} ${chart.h}`" preserveAspectRatio="none" class="absolute inset-0 w-full h-full">
          <polygon :points="chart.areaPoints" fill="#eef2ff" />
          <line :x1="chart.todayX" y1="0" :x2="chart.todayX" :y2="chart.h" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
          <polyline :points="chart.historyLinePoints" fill="none" stroke="#0f172a" stroke-width="2.5" vector-effect="non-scaling-stroke" />
          <polyline :points="chart.projectionLinePoints" fill="none" stroke="#4f46e5" stroke-width="2.5" stroke-dasharray="6 5" vector-effect="non-scaling-stroke" />
        </svg>
      </div>
      <div class="flex justify-between mt-2">
        <span v-for="(lbl, i) in chart.axisLabels" :key="i" class="text-[11px] text-slate-400">{{ lbl }}</span>
      </div>
      <div class="flex gap-5 mt-3.5 text-xs text-slate-600">
        <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-0.5 bg-slate-900 inline-block"></span>Historique réel</span>
        <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-0.5 bg-indigo-600 inline-block border-t-2 border-dashed border-indigo-600"></span>Projection</span>
      </div>
    </section>

    <section class="grid grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="text-xs font-semibold text-slate-500 mb-1.5">Dans 6 mois</div>
        <div class="text-xl font-extrabold">{{ milestone6Label }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="text-xs font-semibold text-slate-500 mb-1.5">Dans 12 mois</div>
        <div class="text-xl font-extrabold">{{ milestone12Label }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
        <div class="text-xs font-semibold text-slate-500 mb-1.5">Épargné sur la période</div>
        <div class="text-xl font-extrabold">{{ totalContributedLabel }}</div>
      </div>
    </section>
  </main>
</template>
