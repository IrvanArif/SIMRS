<?php

namespace Database\Factories;

use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarifTindakanFactory extends Factory
{
    protected $model = TarifTindakan::class;

    public function definition(): array
    {
        return [
            'tindakan_id' => Tindakan::factory(),
            'penjamin_id' => Penjamin::factory(),
            'tarif' => $this->faker->numberBetween(10000, 500000),
            'berlaku_mulai' => '2026-01-01',
        ];
    }
}
