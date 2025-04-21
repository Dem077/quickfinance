<?php

namespace Database\Seeders;

use App\Models\BudgetAccounts;
use App\Models\Departments;
use App\Models\Item;
use App\Models\Location;
use App\Models\Project;
use App\Models\SubBudgetAccounts;
use App\Models\Vendors;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Mpdf\Tag\Sub;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
       
        Location::factory()->create([
            'name'=> 'Head Office',
        ]);
        Location::factory()->create([
            'name'=> 'Warehouse',
        ]);
        Departments::factory()->create([
            'name' => 'IT',
            'petty_cash_float_amount' => 5000,
        ]);
        Departments::factory()->create([
            'name' => 'Finance',
            'petty_cash_float_amount' => 5000,
        ]);
        BudgetAccounts::factory()->create([
            'name' => 'Building',
            'expenditure_type' => 'CAPPEX',
            'account' => 'Property, Plant and Equipment',]);

        SubBudgetAccounts::factory()->create([
            'code' => 'CAPPEX1010102',
            'name' => '1010102 · Building',
            'amount' => 500000,
        ]);
        Item::factory()->create([
            'item_code' => '312312',
            'name' => 'Beef',
        ]);
        Item::factory()->create([
            'item_code' => '312313',
            'name' => 'Chicken',
        ]);

        Project::factory()->create([
            'name' => 'Isdhoo 100',
        ]);

        Vendors::factory()->create([
            'name' => 'Miadhu Trading',
            'address'    => 'Male, Maldives',
            'account_no' => '777771023123',
            'mobile'     => '9999999',
            'gst_no'   => 'GST12332',
            'bank'       => 'BML',
        ]);

        $user = \App\Models\User::factory()->create([
            'name' => 'Admin',
            'email' => 'it@agronational.mv',
            'email_verified_at' => now(),
            'bank_account_name' => null,
            'bank_account_no' => null,
            'designation' => "IT Admin",
            'department_id' => Departments::first()->id,
            'location_id' => Location::inRandomOrder()->first()->id,
            'mobile' => '9940018',
            'password' => Hash::make('12345'),
        ]);

        

        

        $user1 = \App\Models\User::factory()->create([
            'name' => 'finance',
            'email' => 'finance@example.com'
        ]);

       
        $this->call([
            ShieldSeeder::class,
        ]);
        $user->assignRole('super_admin');
        $user1->assignRole('finance');
    }
}
