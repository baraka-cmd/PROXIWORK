<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->decimal('rate', 5, 2);
            $table->timestamp('effective_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $initialRate = (string) config('commission.rate', '10.00');
        $now = now();
        $ruleId = DB::table('commission_rules')->insertGetId([
            'rate' => $initialRate,
            'effective_at' => $now,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('total');
            $table->foreignId('commission_rule_id')->nullable()->after('commission_rate')
                ->constrained('commission_rules')->nullOnDelete();
        });

        // Preserve the rate already booked on existing commission records.
        DB::table('commissions')->select(['order_id', 'commission_rate'])->orderBy('id')->get()
            ->each(function (object $commission): void {
                DB::table('orders')->where('id', $commission->order_id)->update([
                    'commission_rate' => $commission->commission_rate,
                ]);
            });

        // Existing orders without a booked commission are frozen at the rollout rate.
        DB::table('orders')->whereNull('commission_rate')->update([
            'commission_rate' => $initialRate,
            'commission_rule_id' => $ruleId,
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commission_rule_id');
            $table->dropColumn('commission_rate');
        });

        Schema::dropIfExists('commission_rules');
    }
};
