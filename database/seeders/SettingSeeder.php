<?php

namespace Database\Seeders;

use App\Repositories\SettingRepository;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(SettingRepository $repository): void
    {
        $defaults = SettingService::DEFAULT_SETTINGS;

        foreach ($defaults as $key => $val) {
            $repository->setKey($key, $val, "Setting key {$key}");
        }
    }
}
