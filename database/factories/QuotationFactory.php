<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'status' => QuotationStatus::SENT->value,
            'current_offer_id' => null,
            'accepted_offer_id' => null,
        ];
    }
}
