# Comptastic — Spécifications backend (Laravel + PostgreSQL)

Ce document décrit le backend à construire pour remplacer les données mockées
(`src/stores/ledger.js`) du frontend Vue actuel par une vraie API persistée.
Il est dérivé entièrement du comportement observé dans les 7 écrans existants
(`src/views/*.vue`) et du store Pinia qu'ils consomment — pas d'hypothèse
extérieure.

## 1. Contexte

Le frontend Vue (`/`) est aujourd'hui autonome : toute donnée (comptes,
transactions, dettes, budgets) vit en mémoire dans `useLedgerStore`, réinitialisée
à chaque rechargement, avec une authentification "stub" (`LoginView.vue` ne fait
aucun appel réseau). L'objectif est d'ajouter une vraie API Laravel + PostgreSQL
qui persiste ces données par utilisateur, sans changer le comportement/l'UX déjà
validés à l'écran.

## 2. Monorepo — structure cible

```
comptastic/
├── apps/
│   ├── web/              # ce projet Vue actuel (déplacé tel quel)
│   └── api/               # nouveau projet Laravel
├── package.json           # racine, éventuellement workspaces bun pour apps/web
└── docs/
    └── backend-spec.md
```

Déplacer l'app Vue actuelle de `/` vers `apps/web/` est un changement structurel
séparé (chemins d'import, CI, README) — à faire dans un commit dédié avant de
brancher l'API, pas dans le même mouvement que la création du backend.

## 3. Stack technique

- **Laravel 12**, PHP 8.3+
- **PostgreSQL 16**
- **Laravel Sanctum** en mode SPA (cookie de session, CSRF), puisque le frontend
  est un SPA Vue servi séparément — pas de tokens Bearer à gérer côté client
- Migrations + Eloquent, pas de query builder brut sauf cas de perf avérés
- **Form Requests** pour toute validation d'entrée
- **API Resources** pour le formatage de sortie (garantit la stabilité du
  contrat JSON indépendamment du schéma DB)
- Argent stocké en **entier (centimes)** dans toutes les tables — voir §7.1

## 4. Modèle de données

Toutes les tables métier (sauf `users`) ont une colonne `user_id` (FK,
`cascade` on delete) : chaque utilisateur ne voit que ses propres données.
Chaque requête API doit scoper systématiquement par `user_id` de l'utilisateur
authentifié (policy Laravel ou global scope).

### `users`
Standard Laravel (`id`, `name`, `email`, `password`, timestamps). Pas de champ
supplémentaire identifié dans les écrans actuels.

### `accounts`
Écran source : `ComptesView.vue`, `AppShell` (sélecteurs de compte).

| colonne          | type                          | notes |
|---|---|---|
| id | bigint pk | |
| user_id | bigint fk | |
| name | string | ex. "Compte courant BNP Paribas" |
| bank | string, nullable | affiché seul, pas de validation de format |
| type | enum('checking','savings') | mappe `Compte courant` / `Épargne` — stocker en anglais, traduire côté frontend |
| iban_last4 | string(4), nullable | l'app ne stocke/affiche que les 4 derniers chiffres (`FR76 •••• •••• 1234`) ; ne jamais stocker un IBAN complet côté API tant que l'écran ne le demande pas |
| opening_balance_cents | bigint | solde de départ, signé |
| created_at / updated_at | timestamps | |

### `categories`
Écran source : `BudgetsView.vue`, formulaire de transaction.

Le set actuel est fixe et partagé par tous (`Revenus, Logement, Alimentation,
Transport, Loisirs, Santé, Autres`) avec des couleurs figées (`CAT_COLORS`).
Deux options :

- **Global, non éditable par l'utilisateur** (fidèle à l'état actuel) : table
  `categories` sans `user_id`, seedée une fois, `color_hex` en colonne.
- **Par utilisateur, éditable** : ajoute `user_id`, permet personnalisation
  future — mais aucun écran actuel ne propose de créer/renommer une catégorie.

Recommandation : partir sur la version globale non éditable (fidèle à l'UI
actuelle) et migrer plus tard si un écran de gestion des catégories apparaît.

| colonne | type | notes |
|---|---|---|
| id | bigint pk | |
| name | string, unique | `Revenus` est traité spécialement (revenu, pas dépense) partout dans le frontend |
| color_hex | string(7) | reprend `CAT_COLORS` |
| is_income | boolean | `true` uniquement pour `Revenus` |
| sort_order | smallint | ordre d'affichage stable |

### `transactions`
Écran source : `TransactionsView.vue` (le plus riche en logique).

| colonne | type | notes |
|---|---|---|
| id | bigint pk | |
| user_id | bigint fk | |
| account_id | bigint fk → accounts | |
| category_id | bigint fk → categories | |
| label | string | |
| amount_cents | bigint | signé : positif = recette, négatif = dépense (`form.type` ne fait que forcer le signe à la saisie) |
| date | date | |
| reconciled | boolean, default false | "pointée" |
| link_type | enum('none','debt','savings'), default 'none' | |
| linked_debt_id | bigint fk → debts, nullable | requis si `link_type = 'debt'` |
| linked_savings_account_id | bigint fk → accounts, nullable | requis si `link_type = 'savings'`, doit référencer un compte `type = 'savings'` |
| series_id | uuid, nullable | regroupe les lignes générées par un paiement échelonné ou une récurrence (voir §5.3) — `null` pour une transaction simple |
| series_kind | enum('installment','recurring'), nullable | |
| series_index | smallint, nullable | position dans la série (1/4, 2/4…) |
| created_at / updated_at | timestamps | |

Index : `(user_id, date)`, `(account_id, date)`, `(user_id, category_id, date)`
pour les agrégations mensuelles ; `(series_id)`.

### `debts`
Écran source : `DettesView.vue`.

| colonne | type | notes |
|---|---|---|
| id | bigint pk | |
| user_id | bigint fk | |
| name | string | |
| original_amount_cents | bigint | |
| remaining_amount_cents | bigint | |
| monthly_payment_cents | bigint | |
| rate_bps | integer | taux annuel en points de base (ex. 3.9% → 390) pour éviter le flottant |
| end_date | date | |

Point ouvert : dans l'UI, `remainingAmount` ne diminue **jamais** automatiquement
quand une transaction est liée à cette dette (`link_type = 'debt'`) — le champ
est saisi/édité manuellement à la création. À confirmer si le backend doit
recalculer `remaining_amount` à partir de la somme des transactions liées, ou
garder la saisie manuelle telle quelle (comportement actuel du prototype).

### `budgets`
Écran source : `BudgetsView.vue`.

Un budget mensuel par catégorie de dépense, sans notion de mois dans l'UI
actuelle (`store.budgets` est un objet plat, réutilisé identique tous les mois).

| colonne | type | notes |
|---|---|---|
| id | bigint pk | |
| user_id | bigint fk | |
| category_id | bigint fk → categories | |
| monthly_amount_cents | bigint | |

Contrainte unique `(user_id, category_id)`. Pas d'historisation mensuelle pour
l'instant — un budget "Logement" s'applique à tous les mois passés et futurs
tel qu'affiché aujourd'hui. Si un futur écran veut un historique par mois
(ex. comparer budget de juillet vs août), il faudra ajouter une colonne `month`
et changer la contrainte unique en `(user_id, category_id, month)` — signalé
ici comme extension probable, pas implémenté au MVP.

### `user_settings`
Écran source : `ProjectionView.vue`, `BudgetsView.vue` (revenu, effort d'épargne,
taux de rendement — actuellement 3 refs globales dans le store, pas liées à un
mois).

| colonne | type | notes |
|---|---|---|
| user_id | bigint pk/fk | one-to-one avec `users` |
| monthly_income_cents | bigint | utilisé par `BudgetsView` pour la répartition du revenu |
| monthly_savings_contribution_cents | bigint | "effort mensuel" utilisé par `ProjectionView` |
| annual_return_rate_bps | integer | taux de rendement annuel de la projection d'épargne |

## 5. Règles métier à porter

### 5.1 Solde de compte (`ComptesView`, `DashboardView`, `accountBalances()`)

```
balance(compte, à_date)      = opening_balance + Σ(montant des transactions
                                pointées, date <= à_date, sur ce compte)
pending_encours(compte, à_date) = Σ(montant des transactions NON pointées,
                                date <= à_date, sur ce compte)
```

Les transactions futures (`date > à_date`) ne comptent dans aucun des deux
totaux. Endpoint attendu : `GET /api/accounts` retourne directement `balance`
et `pending_encours` calculés côté serveur (pas de calcul frontend).

### 5.2 Solde "après opération" (colonne du tableau `TransactionsView`)

Différent du calcul ci-dessus : c'est une somme cumulée **toutes transactions
confondues (pointées ou non)**, triées par `(date, id)` croissant, par compte,
en partant de `opening_balance`. C'est une incohérence assumée du prototype
(le solde de la fiche compte ignore le non-pointé, la colonne "solde après
opération" l'inclut) — à reproduire telle quelle sauf décision contraire.

### 5.3 Paiement échelonné et transaction récurrente

Les deux sont **matérialisés à la création** : le formulaire calcule à l'avance
toutes les dates/montants et le store insère N lignes de transaction d'un coup
(pas de "règle" évaluée dynamiquement, pas de job planifié qui génère les
occurrences futures).

- **Échelonné** (`installment`) : montant total divisé par N échéances
  (`per = total / n`), une échéance tous les mois à partir de la date choisie.
  Libellé de chaque ligne : `"{label} ({i}/{n})"`.
- **Récurrent** (`recurring`) : montant plein répété à chaque occurrence
  (hebdo/mensuel/annuel), sur N occurrences. Seule la 1ère occurrence hérite du
  flag "pointée" saisi dans le formulaire ; les suivantes sont `reconciled =
  false` par défaut.

L'endpoint `POST /api/transactions` doit accepter soit une transaction simple,
soit un mode `installment`/`recurring` avec les mêmes paramètres que le
frontend (`count`, `frequency`) et faire la génération **côté serveur** (ne pas
faire confiance à un tableau de lignes pré-calculé envoyé par le client) :
recalculer les dates et montants à partir de `amount`, `date`, `count`,
`frequency` pour éviter toute incohérence/injection de données.

### 5.4 Affectation dette / épargne (`link_type`)

Purement déclaratif aujourd'hui : marquer une transaction comme liée à une
dette ou un virement d'épargne n'a aucun effet calculé (pas de mise à jour de
`debts.remaining_amount`, pas de transfert inter-comptes). À reproduire tel
quel — voir point ouvert en §4 `debts`.

### 5.5 Budgets (`BudgetsView`)

```
spent(catégorie, mois) = Σ|montant| des transactions de dépense
                          (amount < 0, catégorie ≠ Revenus)
                          dans les bornes du mois en cours
pct = spent / budget * 100          (0 si budget = 0)
statut = over  si pct >= 100
         warn  si pct >= 80
         ok    sinon
```

Répartition du revenu : chaque catégorie de dépense occupe
`(budget_catégorie / total_budgets) * (total_budgets / revenu * 100)` de la
barre ; le reste jusqu'à 100% (si `total_budgets < revenu`) est l'épargne
possible. Si `total_budgets > revenu`, aucune épargne, message d'alerte
("le budget prévu dépasse le revenu de X€").

L'API doit exposer un endpoint agrégé, ex. `GET /api/budgets?month=2026-08`
retournant pour chaque catégorie : `budget`, `spent`, `pct`, `status`, plutôt
que de faire calculer `spent` au frontend à partir de la liste brute des
transactions.

### 5.6 Dettes (`DettesView`)

```
progress_pct  = min((original - remaining) / original * 100, 100)   si original > 0
months_left   = ceil(remaining / monthly_payment)                    si monthly_payment > 0, sinon null
```

Purement dérivé des colonnes stockées, pas de logique d'amortissement
(pas de calcul d'intérêts composés sur la dette — contrairement à la
projection d'épargne).

### 5.7 Projection d'épargne (`ProjectionView`)

- **Historique réel** : solde total des comptes de `type = 'savings'`
  (pointées uniquement, même formule que §5.1) calculé à la fin de chacun des
  3 derniers mois + aujourd'hui (4 points).
- **Projection** : intérêts composés mensuels à partir du solde actuel :
  ```
  v[0] = solde_épargne_actuel
  v[i] = v[i-1] * (1 + taux_annuel/100/12) + effort_mensuel     pour i = 1..horizon
  ```
  `horizon` ∈ {6, 12, 24, 36} mois, choisi côté frontend (pas besoin de le
  précalculer côté serveur pour toutes les valeurs — un endpoint qui prend
  `horizon` en paramètre suffit, ou renvoyer 36 points et laisser le frontend
  tronquer).

Endpoint suggéré : `GET /api/savings-projection?horizon=12` renvoyant
`history` (4 points réels) + `projection` (jusqu'à `horizon` points).

## 6. API — endpoints

Toutes les routes sous `/api`, protégées par `auth:sanctum` sauf
`/login`, `/register`, `/logout`.

| Méthode | Route | Description |
|---|---|---|
| POST | `/register` | création de compte (email + mot de passe) |
| POST | `/login` | connexion, retourne le cookie de session Sanctum |
| POST | `/logout` | invalide la session |
| GET | `/user` | utilisateur courant (pour restaurer la session au chargement du SPA) |
| GET | `/accounts` | liste des comptes avec `balance` / `pending_encours` calculés |
| POST | `/accounts` | création de compte |
| GET | `/transactions?period=current\|previous\|year&account_id=&page=` | liste filtrée, paginée, avec `running_balance` par ligne |
| POST | `/transactions` | création — simple, échelonnée ou récurrente (voir §5.3) |
| PATCH | `/transactions/{id}` | édition (a minima : toggle `reconciled`) |
| DELETE | `/transactions/{id}` | suppression |
| GET | `/debts` | liste des dettes avec `progress_pct` / `months_left` |
| POST | `/debts` | création de dette |
| PATCH | `/debts/{id}` | édition |
| GET | `/budgets?month=` | budgets + consommation agrégée par catégorie |
| PUT | `/budgets/{category_id}` | mise à jour du montant budgété d'une catégorie |
| GET | `/categories` | liste globale (id, name, color, is_income) |
| GET/PUT | `/settings` | lecture/écriture de `monthly_income`, `monthly_savings_contribution`, `annual_return_rate` |
| GET | `/savings-projection?horizon=` | historique + projection (§5.7) |
| GET | `/dashboard/summary?period=` | agrégats du tableau de bord (barres recettes/dépenses par semaine ou par mois, répartition par catégorie) — **remplace le jeu de données `PERIODS` codé en dur dans `DashboardView.vue`**, qui n'a aucune source de vérité réelle aujourd'hui |

Point d'attention : `DashboardView.vue` actuel n'utilise **pas** les vraies
transactions du store pour ses graphiques — il consomme un objet `PERIODS`
statique commenté "illustrative … the 10 seed transactions are too sparse".
Le backend, lui, doit calculer ces agrégats à partir des vraies transactions
persistées ; il faut s'attendre à ce que les graphiques changent de forme une
fois branchés sur de vraies données (c'est voulu, pas une régression).

## 7. Points transverses

### 7.1 Argent

Toutes les valeurs monétaires en base sont des **entiers en centimes**
(`amount_cents`, etc.), jamais du `decimal`/`float`. Les `API Resources`
convertissent en euros (float à 2 décimales) uniquement en sortie JSON, pour
matcher le format attendu par `eur()` côté frontend sans lui imposer de
connaître les centimes. Le taux (`rate`, `annual_return_rate`) est stocké en
points de base (integer) pour la même raison.

### 7.2 Dates et fuseaux

Le frontend actuel raisonne uniquement en dates locales `YYYY-MM-DD` (pas de
composante horaire, pas de fuseau) — `TODAY_ISO` est une constante figée pour
la démo (`2026-08-06`). Le backend doit traiter `date` comme un type `date`
Postgres pur, comparer en ISO string, et utiliser la date du jour du serveur
(`now()`) une fois la donnée réelle branchée — supprimer toute notion de
`TODAY_ISO` figée côté frontend au moment de l'intégration.

### 7.3 Validation

- `amount` : requis, numérique, non nul (le formulaire actuel n'autorise pas
  0 — `if (!form.label || !amt) return`)
- `account_id`, `category_id`, `linked_debt_id`, `linked_savings_account_id` :
  doivent appartenir à l'utilisateur authentifié (403 sinon)
- `linked_savings_account_id` : doit référencer un compte `type = 'savings'`
- `installment.count` ∈ {2,3,4,6,12}, `recurring.count` ∈ {3,6,12,24},
  `recurring.frequency` ∈ {weekly, monthly, yearly} — reprendre exactement les
  options déjà proposées par les `<select>` du frontend, pas de valeurs libres

### 7.4 Auth

Écran `LoginView.vue` a deux formulaires (Connexion / Inscription) mais aucun
appel réseau réel aujourd'hui. Le backend expose `register`/`login`/`logout`
Sanctum standard ; pas de vérification d'email, pas de 2FA, pas de réinitialisation
de mot de passe visibles dans les maquettes — à ajouter uniquement si un écran
le demande explicitement plus tard.

## 8. Hors périmètre (non spécifié par les écrans actuels)

- Import CSV de relevés bancaires
- Édition/suppression d'un compte ou d'une dette existante (seule la création
  est câblée dans l'UI)
- Édition en masse d'une série récurrente déjà créée (ex. modifier toutes les
  occurrences futures d'un abonnement)
- Notifications, exports, multi-devise

Ces points ne sont pas dans ce spec car aucun écran ne les couvre — à
spécifier séparément si/quand un écran correspondant est designé.

## 9. Ordre de mise en œuvre suggéré

1. Squelette Laravel (`apps/api`), config PostgreSQL, Sanctum SPA, CORS pour
   `apps/web`
2. Migrations + seeders reproduisant les données `SEED_*` actuelles de
   `ledger.js` (permet de garder le même rendu visuel pendant la bascule)
3. `accounts`, `categories`, `transactions` (CRUD + règles §5.1/5.2/5.3)
4. `debts`, `budgets`, `settings` (§5.5/5.6)
5. `savings-projection`, `dashboard/summary` (agrégats, §5.7 + remplacement de
   `PERIODS`)
6. Auth réelle + retrait du stub dans `LoginView.vue`
7. Remplacement de `useLedgerStore` par des appels API (Pinia store devient un
   simple cache client, plus la source de vérité)

## 10. Décisions à valider

- Catégories globales figées vs personnalisables par utilisateur (§4, recommandé : figées pour le MVP)
- `debts.remaining_amount` : saisie manuelle (comme aujourd'hui) ou recalculée à partir des transactions liées (§4)
- Budgets sans historique mensuel (comme aujourd'hui) vs un budget par mois (§4)
