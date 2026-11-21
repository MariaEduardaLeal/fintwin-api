<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'salario', 'name' => 'Salário', 'type' => TransactionType::Income->value, 'color' => '#10B981', 'icon' => 'briefcase', 'is_essential' => true],
            ['slug' => 'proventos', 'name' => 'Proventos e Dividendos', 'type' => TransactionType::Income->value, 'color' => '#34D399', 'icon' => 'trending-up', 'is_essential' => false],
            ['slug' => 'moradia', 'name' => 'Moradia', 'type' => TransactionType::Expense->value, 'color' => '#3B82F6', 'icon' => 'home', 'is_essential' => true],
            ['slug' => 'alimentacao', 'name' => 'Alimentação', 'type' => TransactionType::Expense->value, 'color' => '#F59E0B', 'icon' => 'utensils', 'is_essential' => true],
            ['slug' => 'transporte', 'name' => 'Transporte', 'type' => TransactionType::Expense->value, 'color' => '#6366F1', 'icon' => 'car', 'is_essential' => true],
            ['slug' => 'saude', 'name' => 'Saúde', 'type' => TransactionType::Expense->value, 'color' => '#EC4899', 'icon' => 'heart-pulse', 'is_essential' => true],
            ['slug' => 'educacao', 'name' => 'Educação', 'type' => TransactionType::Expense->value, 'color' => '#8B5CF6', 'icon' => 'graduation-cap', 'is_essential' => true],
            ['slug' => 'lazer', 'name' => 'Lazer e Assinaturas', 'type' => TransactionType::Expense->value, 'color' => '#F97316', 'icon' => 'gamepad-2', 'is_essential' => false],
            ['slug' => 'compras', 'name' => 'Compras', 'type' => TransactionType::Expense->value, 'color' => '#EF4444', 'icon' => 'shopping-bag', 'is_essential' => false],
            ['slug' => 'investimentos', 'name' => 'Aportes e Investimentos', 'type' => TransactionType::Transfer->value, 'color' => '#14B8A6', 'icon' => 'piggy-bank', 'is_essential' => false],
            ['slug' => 'outros', 'name' => 'Outros', 'type' => TransactionType::Expense->value, 'color' => '#64748B', 'icon' => 'circle-ellipsis', 'is_essential' => false],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'slug' => $category['slug'],
                ],
                $category,
            );
        }
    }
}
