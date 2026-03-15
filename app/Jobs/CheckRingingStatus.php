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

        $response = Http::get(env('RINGING_API_URL') . '/status_example', [
            'id_example' => $order->api_id,
            'token'      => env('TOKEN'),
        ]);

        $data   = $response->json();
        $status = $data['status'] ?? null;

        $stats = [
            'good'        => $data['good'] ?? null,
            'total_sites' => $data['total_sites'] ?? null,
            'rate'        => $data['rate'] ?? null,
            'link_report' => $data['link_report'] ?? null,
            'token_report'=> $data['token_report'] ?? null,
            'elapsed_time'=> $data['elapsed_time'] ?? null,
        ];

        if ($status === 'done') {
            $order->update(array_merge($stats, ['status' => 'done']));

            $user = User::find($order->user_id);
            $user?->addWaitSendMessage(
                "Прозвон завершён!\nНомер: {$order->phone}\nИмя: {$order->name}",
            );
        } elseif ($status === 'failed') {
            $order->update(array_merge($stats, ['status' => 'failed']));

            $user = User::find($order->user_id);
            $user?->addWaitSendMessage(
                "Прозвон не удался.\nНомер: {$order->phone}",
            );
        } else {
            $order->update($stats);
            self::dispatch($this->orderId)->delay(now()->addSeconds(30));
        }
    }
}
