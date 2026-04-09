<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RbacSeeder::class,
            AdminConfigsTableSeeder::class,
            ArticleMenuSeeder::class,
            MemberMenuSeeder::class,
            MemberSubmenuSeeder::class,
            CommentMenuSeeder::class,
            TgBotMenuSeeder::class,
            TgHoldingMenuSeeder::class,
            ApiUrlConfigSeeder::class,
            RevenueUrlConfigSeeder::class,
            MarginRateConfigSeeder::class,
            AlertThresholdConfigSeeder::class,
        ]);
    }
}
