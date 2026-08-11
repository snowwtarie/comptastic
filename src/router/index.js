import { createRouter, createWebHistory } from 'vue-router';
import AppShell from '../components/AppShell.vue';
import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import TransactionsView from '../views/TransactionsView.vue';
import BudgetsView from '../views/BudgetsView.vue';
import ComptesView from '../views/ComptesView.vue';
import DettesView from '../views/DettesView.vue';
import ProjectionView from '../views/ProjectionView.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView },
    {
      path: '/',
      component: AppShell,
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: DashboardView },
        { path: 'transactions', name: 'transactions', component: TransactionsView },
        { path: 'budgets', name: 'budgets', component: BudgetsView },
        { path: 'comptes', name: 'comptes', component: ComptesView },
        { path: 'dettes', name: 'dettes', component: DettesView },
        { path: 'projection', name: 'projection', component: ProjectionView },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/login' },
  ],
});

export default router;
