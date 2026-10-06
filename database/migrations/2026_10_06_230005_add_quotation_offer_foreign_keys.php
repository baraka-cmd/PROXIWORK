<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreign('current_offer_id')->references('id')->on('quotation_offers')->nullOnDelete();
            $table->foreign('accepted_offer_id')->references('id')->on('quotation_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropForeign(['current_offer_id']);
            $table->dropForeign(['accepted_offer_id']);
        });
    }
};
