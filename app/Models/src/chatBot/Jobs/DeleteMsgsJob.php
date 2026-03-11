<?php

namespace App\Models\src\chatBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\src\chatBot\Telegram;

class DeleteMsgsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $botToken;
    protected $tg_id;
    protected $deleteMessages;

    public function __construct($botToken, $tg_id, $deleteMessages)
    {
        $this->botToken = $botToken;
        $this->tg_id = $tg_id;
        $this->deleteMessages = $deleteMessages;
    }

    public function handle()
    {
        $tg = new Telegram($this->botToken);

        $tg->deleteMessages = $this->deleteMessages;
        $tg->deleteMsgs($this->tg_id);
    }
}