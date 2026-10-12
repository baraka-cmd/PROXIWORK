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
        Schema::table('addresses', function (Blueprint $table): void {
            $table->text('access_instructions')->nullable()->after('landmark');
        });

        Schema::table('order_address_snapshots', function (Blueprint $table): void {
            $table->text('access_instructions')->nullable()->after('landmark');
            $table->string('contact_phone', 50)->nullable()->change();
        });

        Schema::create('service_request_address_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained('service_requests')->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('recipient_name', 255)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->char('country_code', 2);
            $table->string('province', 100)->nullable();
            $table->string('city', 100);
            $table->string('commune', 100)->nullable();
            $table->string('neighborhood', 150)->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->text('access_instructions')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        DB::table('service_requests')
            ->join('addresses', 'addresses.id', '=', 'service_requests.address_id')
            ->select([
                'service_requests.id as service_request_id',
                'addresses.label',
                'addresses.recipient_name',
                'addresses.contact_phone',
                'addresses.country_code',
                'addresses.province',
                'addresses.city',
                'addresses.commune',
                'addresses.neighborhood',
                'addresses.address_line_1',
                'addresses.address_line_2',
                'addresses.landmark',
                'addresses.postal_code',
                'addresses.latitude',
                'addresses.longitude',
            ])
            ->orderBy('service_requests.id')
            ->chunk(200, function ($rows): void {
                $now = now();

                foreach ($rows as $row) {
                    DB::table('service_request_address_snapshots')->insert([
                        'service_request_id' => $row->service_request_id,
                        'label' => $row->label,
                        'recipient_name' => $row->recipient_name,
                        'contact_phone' => $row->contact_phone,
                        'country_code' => $row->country_code,
                        'province' => $row->province,
                        'city' => $row->city,
                        'commune' => $row->commune,
                        'neighborhood' => $row->neighborhood,
                        'address_line_1' => $row->address_line_1,
                        'address_line_2' => $row->address_line_2,
                        'landmark' => $row->landmark,
                        'postal_code' => $row->postal_code,
                        'latitude' => $row->latitude,
                        'longitude' => $row->longitude,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_address_snapshots');

        Schema::table('order_address_snapshots', function (Blueprint $table): void {
            $table->dropColumn('access_instructions');
            $table->string('contact_phone', 50)->nullable(false)->change();
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn('access_instructions');
        });
    }
};
