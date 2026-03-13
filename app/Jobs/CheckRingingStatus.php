<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CheckRingingStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;

    public function __construct(protected int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order || $order->status === 'done' || $order->status === 'failed') {
            return;
        }

        $response = Http::get(env('RINGING_API_URL') . '/status', [
            'id'    => $order->api_id,
            'token' => env('TOKEN'),
        ]);

        $status = $response->json('status');

        if ($status === 'done') {
            $order->update(['status' => 'done']);

            $user = User::find($order->user_id);
            $user?->addWaitSendMessage(
                "Прозвон завершён!\nНомер: {$order->phone}\nИмя: {$order->name}",
            );
        } elseif ($status === 'failed') {
            $order->update(['status' => 'failed']);

            $user = User::find($order->user_id);
            $user?->addWaitSendMessage(
                "Прозвон не удался.\nНомер: {$order->phone}",
            );
        } else {
            // Ещё в процессе — повторить через 30 секунд
            self::dispatch($this->orderId)->delay(now()->addSeconds(30));
        }
    }
}
