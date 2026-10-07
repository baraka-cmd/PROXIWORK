<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Notifications\AccountActivityNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderPaidNotification implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order->loadMissing([
            'client',
            'professional.user',
        ]);

        $recipients = collect([
            $order->client,
            $order->professional?->user,
        ])->filter()->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new AccountActivityNotification(
                'Paiement confirmé',
                'Le paiement de la commande #'.$order->getKey().' a été confirmé.',
                'order.paid',
            ));
        }
    }
}
