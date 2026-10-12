<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
        ]);

        $device = NotificationDevice::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->getKey(),
                'platform' => $data['platform'],
                'last_used_at' => now(),
            ],
        );

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
