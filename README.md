# Comptastic

Comptastic est une application web de gestion de finances personnelles : suivi des comptes, des transactions, des budgets par catégorie, des dettes et une projection de l'épargne — le tout en français, avec une interface responsive (bureau et mobile).

L'application a été implémentée à partir de maquettes HTML/CSS/JS exportées de Claude Design.

## Fonctionnalités

- **Connexion / inscription** — écran unique avec bascule entre les deux modes, authentifié via l'API Laravel (sessions Sanctum).
- **Tableau de bord** — solde total, tendance, graphique dépenses/recettes, répartition des dépenses par catégorie, aperçu des comptes.
- **Transactions** — liste filtrable par compte et par période, pointage ligne par ligne, solde théorique après opération, saisie de paiements échelonnés (N fois) ou de transactions récurrentes (loyer, abonnements...), affectation à une dette ou à une épargne.
- **Budgets** — enveloppe mensuelle éditable par catégorie avec barre de consommation en direct, répartition du revenu mensuel entre catégories budgétées et épargne possible.
- **Comptes** — liste des comptes avec solde pointé et encours non pointé, ajout de nouveaux comptes.
- **Dettes** — suivi des crédits et prêts en cours (progression du remboursement, mensualité, taux, échéance).
- **Projection d'épargne** — historique réel des soldes d'épargne et projection selon un effort d'épargne mensuel et un taux de rendement, sur un horizon choisi.

## Stack technique

- [Vue 3](https://vuejs.org/) (`<script setup>`) + [Vite](https://vite.dev/)
- [Vue Router](https://router.vuejs.org/) pour la navigation
- [Pinia](https://pinia.vuejs.org/) pour l'état partagé (comptes, transactions, budgets...)
- [Tailwind CSS](https://tailwindcss.com/) pour le style
- [Bun](https://bun.sh) comme gestionnaire de paquets et runner de scripts

Chaque page adapte sa mise en page (navigation, densité des graphiques, formulaires en modale centrée vs. bottom sheet...) entre bureau et mobile via un composable de breakpoint, en partageant la même logique métier.

## Structure

```
apps/
  web/            Application front-end Vue (voir ci-dessous)
    src/
      components/   Composants partagés (navigation, icônes, modale/bottom sheet, champ éditable)
      lib/          Formatage (devise, dates), composable de breakpoint mobile
      router/       Déclaration des routes
      stores/       Store Pinia des données financières (comptes, transactions, budgets...)
      views/        Une vue par page (Connexion, Tableau de bord, Transactions, Budgets, Comptes, Dettes, Projection)
```

Le backend API (Laravel) est décrit dans [`docs/backend-spec.md`](docs/backend-spec.md).

## Démarrage

Le front-end appelle une vraie API — les deux doivent tourner en parallèle.

**Backend** (`apps/api`) :

```sh
cd apps/api
composer install
cp .env.example .env   # configure DB_* pour PostgreSQL, puis :
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

**Front-end** (`apps/web`) :

```sh
cd apps/web
cp .env.example .env   # définit VITE_API_BASE_URL (http://localhost:8000 par défaut)
bun install
bun run dev
```

`apps/web/.env` n'est pas versionné : sans cette copie, `VITE_API_BASE_URL` vaut `undefined` et tous les appels API échouent silencieusement (404 sur `/undefined/api/...`).

## Tests

```sh
cd apps/api && php artisan test    # suite Pest
cd apps/web && bun run test        # suite Vitest (stores Pinia)
```

## Build de production

```sh
cd apps/web
bun run build
bun run preview
```
