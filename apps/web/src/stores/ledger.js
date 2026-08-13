import { defineStore } from 'pinia';
import { reactive, ref, computed } from 'vue';
import { TODAY_ISO, monthBoundsISO } from '../lib/format';

const SEED_ACCOUNTS = [
  { name: 'Compte courant BNP Paribas', bank: 'BNP Paribas', type: 'Compte courant', iban: 'FR76 •••• •••• 1234', openingBalance: -1199.02 },
  { name: 'Compte courant Boursorama', bank: 'Boursorama Banque', type: 'Compte courant', iban: 'FR76 •••• •••• 5678', openingBalance: 1002.72 },
  { name: 'Compte courant Revolut', bank: 'Revolut', type: 'Compte courant', iban: 'FR76 •••• •••• 7890', openingBalance: 412.3 },
  { name: 'Livret A (Crédit Agricole)', bank: 'Crédit Agricole', type: 'Épargne', iban: 'FR76 •••• •••• 9012', openingBalance: 7950.0 },
  { name: 'LDDS (Crédit Agricole)', bank: 'Crédit Agricole', type: 'Épargne', iban: 'FR76 •••• •••• 3456', openingBalance: 3020.75 },
];

export const CATEGORIES = ['Revenus', 'Logement', 'Alimentation', 'Transport', 'Loisirs', 'Santé', 'Autres'];

// Tailwind indigo-700/600/500/400/300 + slate-300, matching the design's chart ramp.
export const CAT_COLORS = {
  Logement: '#4338ca',
  Alimentation: '#4f46e5',
  Transport: '#6366f1',
  Loisirs: '#818cf8',
  Santé: '#a5b4fc',
  Autres: '#cbd5e1',
};
export const CAT_COLOR_LIST = ['#4338ca', '#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#cbd5e1'];

const SEED_TRANSACTIONS = [
  { date: '2026-08-05', label: 'Salaire Août', category: 'Revenus', account: 'Compte courant BNP Paribas', amount: 2200, reconciled: true },
  { date: '2026-08-04', label: 'Loyer août', category: 'Logement', account: 'Compte courant BNP Paribas', amount: -780, reconciled: true },
  { date: '2026-08-03', label: 'Supermarché Carrefour', category: 'Alimentation', account: 'Compte courant Boursorama', amount: -64.2, reconciled: true },
  { date: '2026-08-02', label: 'Abonnement Navigo', category: 'Transport', account: 'Compte courant BNP Paribas', amount: -75.2, reconciled: false },
  { date: '2026-08-01', label: 'Netflix', category: 'Loisirs', account: 'Compte courant Revolut', amount: -15.99, reconciled: false },
  { date: '2026-07-31', label: 'Pharmacie', category: 'Santé', account: 'Compte courant BNP Paribas', amount: -22.5, reconciled: true },
  { date: '2026-07-28', label: 'Restaurant Le Petit Zinc', category: 'Alimentation', account: 'Compte courant Boursorama', amount: -48, reconciled: true },
  { date: '2026-07-15', label: 'Virement épargne', category: 'Autres', account: 'Livret A (Crédit Agricole)', amount: 200, reconciled: true },
  { date: '2026-07-10', label: 'Essence', category: 'Transport', account: 'Compte courant BNP Paribas', amount: -58.3, reconciled: true },
  { date: '2026-07-05', label: 'Salaire Juillet', category: 'Revenus', account: 'Compte courant BNP Paribas', amount: 2200, reconciled: true },
];

const SEED_DEBTS = [
  { name: 'Prêt automobile', originalAmount: 18000, remainingAmount: 11200, monthlyPayment: 320, rate: 3.9, endDate: '2029-06-15' },
  { name: 'Crédit conso — travaux', originalAmount: 6000, remainingAmount: 2450, monthlyPayment: 180, rate: 5.2, endDate: '2027-11-01' },
  { name: 'Smartphone en 4 fois', originalAmount: 800, remainingAmount: 200, monthlyPayment: 200, rate: 0, endDate: '2026-11-05' },
];

export const DEFAULT_BUDGETS = { Logement: 800, Alimentation: 500, Transport: 150, Loisirs: 100, Santé: 100, Autres: 150 };

let nextTxnId = 1000;
let nextAccountId = 5000;
let nextDebtId = 9000;

export const useLedgerStore = defineStore('ledger', () => {
  const categories = ref([...CATEGORIES]);
  const accounts = reactive(SEED_ACCOUNTS.map((a) => ({ ...a, id: nextAccountId++ })));
  const transactions = reactive(SEED_TRANSACTIONS.map((t) => ({ ...t, id: nextTxnId++ })));
  const debts = reactive(SEED_DEBTS.map((d) => ({ ...d, id: nextDebtId++ })));
  const budgets = reactive({ ...DEFAULT_BUDGETS });

  const { start: monthStart, end: monthEnd } = monthBoundsISO();
  const monthIncome = SEED_TRANSACTIONS
    .filter((t) => t.category === 'Revenus' && t.date >= monthStart && t.date <= monthEnd)
    .reduce((s, t) => s + t.amount, 0);
  const monthExpense = SEED_TRANSACTIONS
    .filter((t) => t.amount < 0 && t.date >= monthStart && t.date <= monthEnd)
    .reduce((s, t) => s + Math.abs(t.amount), 0);

  const income = ref(monthIncome);
  const monthlyContribution = ref(Math.max(Math.round(monthIncome - monthExpense), 0));
  const annualRate = ref(2);

  const accountNames = computed(() => accounts.map((a) => a.name));
  const savingsAccountNames = computed(() => accounts.filter((a) => a.type === 'Épargne').map((a) => a.name));
  const debtNames = computed(() => debts.map((d) => d.name));
  const expenseCategories = computed(() => categories.value.filter((c) => c !== 'Revenus'));

  function accountBalances(todayISO = TODAY_ISO) {
    return accounts.map((acc) => {
      let reconciledBalance = acc.openingBalance;
      let pendingEncours = 0;
      for (const t of transactions) {
        if (t.account !== acc.name || t.date > todayISO) continue;
        if (t.reconciled) reconciledBalance += t.amount;
        else pendingEncours += t.amount;
      }
      return { ...acc, balance: reconciledBalance, pendingEncours };
    });
  }

  function typeBalanceAt(type, dateISO) {
    return accounts.filter((a) => a.type === type).reduce((sum, acc) => {
      let bal = acc.openingBalance;
      for (const t of transactions) {
        if (t.account !== acc.name || t.date > dateISO || !t.reconciled) continue;
        bal += t.amount;
      }
      return sum + bal;
    }, 0);
  }

  function addTransactions(newOnes) {
    for (const t of newOnes) transactions.unshift({ ...t, id: nextTxnId++ });
  }

  function toggleReconciled(id) {
    const t = transactions.find((x) => x.id === id);
    if (t) t.reconciled = !t.reconciled;
  }

  function addAccount({ name, bank, type, openingBalance }) {
    const id = nextAccountId++;
    accounts.push({
      id,
      name,
      bank: bank || '—',
      type,
      iban: 'FR76 •••• •••• ' + String(1000 + id).slice(-4),
      openingBalance,
    });
  }

  function addDebt(d) {
    debts.push({ ...d, id: nextDebtId++ });
  }

  return {
    categories,
    expenseCategories,
    accounts,
    accountNames,
    savingsAccountNames,
    transactions,
    debts,
    debtNames,
    budgets,
    income,
    monthlyContribution,
    annualRate,
    accountBalances,
    typeBalanceAt,
    addTransactions,
    toggleReconciled,
    addAccount,
    addDebt,
  };
});
