<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@comptastic.test'],
            ['name' => 'Demo', 'password' => Hash::make('password')],
        );

        $user->settings()->updateOrCreate([], [
            'monthly_income_cents' => 220000,
            'monthly_savings_contribution_cents' => 100000,
            'annual_return_rate_bps' => 200,
        ]);

        $accounts = [
            ['name' => 'Compte courant BNP Paribas', 'bank' => 'BNP Paribas', 'type' => 'checking', 'iban_last4' => '1234', 'opening_balance_cents' => -119902],
            ['name' => 'Compte courant Boursorama', 'bank' => 'Boursorama Banque', 'type' => 'checking', 'iban_last4' => '5678', 'opening_balance_cents' => 100272],
            ['name' => 'Compte courant Revolut', 'bank' => 'Revolut', 'type' => 'checking', 'iban_last4' => '7890', 'opening_balance_cents' => 41230],
            ['name' => 'Livret A (Crédit Agricole)', 'bank' => 'Crédit Agricole', 'type' => 'savings', 'iban_last4' => '9012', 'opening_balance_cents' => 795000],
            ['name' => 'LDDS (Crédit Agricole)', 'bank' => 'Crédit Agricole', 'type' => 'savings', 'iban_last4' => '3456', 'opening_balance_cents' => 302075],
        ];

        $accountsByName = collect($accounts)->mapWithKeys(function (array $attrs) use ($user) {
            $account = $user->accounts()->updateOrCreate(['name' => $attrs['name']], $attrs);

            return [$attrs['name'] => $account];
        });

        $categoriesByName = Category::pluck('id', 'name');

        $transactions = [
            ['date' => '2026-08-05', 'label' => 'Salaire Août', 'category' => 'Revenus', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => 220000, 'reconciled' => true],
            ['date' => '2026-08-04', 'label' => 'Loyer août', 'category' => 'Logement', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -78000, 'reconciled' => true],
            ['date' => '2026-08-03', 'label' => 'Supermarché Carrefour', 'category' => 'Alimentation', 'account' => 'Compte courant Boursorama', 'amount_cents' => -6420, 'reconciled' => true],
            ['date' => '2026-08-02', 'label' => 'Abonnement Navigo', 'category' => 'Transport', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -7520, 'reconciled' => false],
            ['date' => '2026-08-01', 'label' => 'Netflix', 'category' => 'Loisirs', 'account' => 'Compte courant Revolut', 'amount_cents' => -1599, 'reconciled' => false],
            ['date' => '2026-07-31', 'label' => 'Pharmacie', 'category' => 'Santé', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -2250, 'reconciled' => true],
            ['date' => '2026-07-28', 'label' => 'Restaurant Le Petit Zinc', 'category' => 'Alimentation', 'account' => 'Compte courant Boursorama', 'amount_cents' => -4800, 'reconciled' => true],
            ['date' => '2026-07-15', 'label' => 'Virement épargne', 'category' => 'Autres', 'account' => 'Livret A (Crédit Agricole)', 'amount_cents' => 20000, 'reconciled' => true],
            ['date' => '2026-07-10', 'label' => 'Essence', 'category' => 'Transport', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -5830, 'reconciled' => true],
            ['date' => '2026-07-05', 'label' => 'Salaire Juillet', 'category' => 'Revenus', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => 220000, 'reconciled' => true],
        ];

        foreach ($transactions as $txn) {
            $user->transactions()->updateOrCreate(
                ['label' => $txn['label'], 'date' => $txn['date']],
                [
                    'account_id' => $accountsByName[$txn['account']]->id,
                    'category_id' => $categoriesByName[$txn['category']],
                    'amount_cents' => $txn['amount_cents'],
                    'reconciled' => $txn['reconciled'],
                    'link_type' => 'none',
                ],
            );
        }

        $debts = [
            ['name' => 'Prêt automobile', 'original_amount_cents' => 1800000, 'remaining_amount_cents' => 1120000, 'monthly_payment_cents' => 32000, 'rate_bps' => 390, 'end_date' => '2029-06-15'],
            ['name' => 'Crédit conso — travaux', 'original_amount_cents' => 600000, 'remaining_amount_cents' => 245000, 'monthly_payment_cents' => 18000, 'rate_bps' => 520, 'end_date' => '2027-11-01'],
            ['name' => 'Smartphone en 4 fois', 'original_amount_cents' => 80000, 'remaining_amount_cents' => 20000, 'monthly_payment_cents' => 20000, 'rate_bps' => 0, 'end_date' => '2026-11-05'],
        ];

        foreach ($debts as $debt) {
            $user->debts()->updateOrCreate(['name' => $debt['name']], $debt);
        }

        $budgets = [
            'Logement' => 80000, 'Alimentation' => 50000, 'Transport' => 15000,
            'Loisirs' => 10000, 'Santé' => 10000, 'Autres' => 15000,
        ];

        foreach ($budgets as $name => $cents) {
            $user->budgets()->updateOrCreate(
                ['category_id' => $categoriesByName[$name]],
                ['monthly_amount_cents' => $cents],
            );
        }
    }
}
