<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        // Application-level checks are useful for a clear error message, but
        // only a database constraint protects two concurrent settlements.
        $duplicateReferenceExists = DB::table('withdrawals')
            ->select('provider', 'provider_transaction_id')
            ->whereNotNull('provider_transaction_id')
            ->groupBy('provider', 'provider_transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateReferenceExists) {
            throw new RuntimeException(
                'Migration interrompue : plusieurs retraits utilisent déjà la même référence externe pour un même canal. '
                .'Rapprochez manuellement les dossiers concernés avant de créer la contrainte unique.'
            );
        }

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->unique(
                ['provider', 'provider_transaction_id'],
                'withdrawals_provider_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropUnique('withdrawals_provider_reference_unique');
        });
    }
};
