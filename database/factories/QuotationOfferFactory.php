<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuotationDurationUnit;
use App\Enums\QuotationOfferActor;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationOffer>
 */
class QuotationOfferFactory extends Factory
{
    protected $model = QuotationOffer::class;

    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'created_by' => User::factory(),
            'actor_type' => QuotationOfferActor::PROFESSIONAL->value,
            'version' => 1,
            'amount' => 100.00,
            'currency' => 'USD',
            'description' => fake()->sentence(),
            'duration_value' => 3,
            'duration_unit' => QuotationDurationUnit::DAYS->value,
            'conditions' => null,
            'valid_until' => now()->addDays(7),
            'created_at' => now(),
        ];
    }
}
