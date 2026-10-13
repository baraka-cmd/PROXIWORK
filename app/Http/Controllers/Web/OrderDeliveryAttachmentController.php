<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDeliveryAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderDeliveryAttachmentController extends Controller
{
    public function download(Request $request, Order $order, OrderDeliveryAttachment $attachment): BinaryFileResponse
    {
        $this->authorize('view', $order);

        abort_unless($attachment->order_id === $order->getKey(), 404);
        abort_unless(
            $order->client_id === $request->user()->getKey()
                || $order->professional?->user_id === $request->user()->getKey(),
            404,
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return response()->download(
            $disk->path($attachment->path),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
