<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
        ]);

        $device = DB::transaction(function () use ($data, $request): NotificationDevice {
            $existing = NotificationDevice::query()
                ->where('token', $data['token'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null && (string) $existing->user_id !== (string) $request->user()->getKey()) {
                abort(409, 'Cet appareil est déjà associé à un autre compte. Révoquez-le depuis ce compte avant de le réenregistrer.');
            }

            return NotificationDevice::query()->updateOrCreate(
                ['token' => $data['token']],
                [
                    'user_id' => $request->user()->getKey(),
                    'platform' => $data['platform'],
                    'last_used_at' => now(),
                ],
            );
        }, attempts: 3);

        return response()->json([
            'message' => 'Appareil de notification enregistré.',
            'data' => [
                'id' => $device->getKey(),
                'platform' => $device->platform,
            ],
            'meta' => [],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
        ]);

        NotificationDevice::query()
            ->where('user_id', $request->user()->getKey())
            ->where('token', $data['token'])
            ->delete();

        return response()->json([
            'message' => 'Appareil de notification supprimé.',
            'data' => [],
            'meta' => [],
        ]);
    }
}
