<?php

namespace App\Models\src\chatBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\src\chatBot\Telegram;

class SendCallbackAnswerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $botToken;
    protected $callbackQueryId;
    protected $text;

    public function __construct($botToken, $callbackQueryId, $text)
    {
        $this->botToken = $botToken;
        $this->callbackQueryId = $callbackQueryId;
        $this->text = $text;
    }

    public function handle()
    {
        $tg = new Telegram($this->botToken);

        $params = [
            'callback_query_id' => $this->callbackQueryId,
        ];

        if(!empty($this->text)) {
            $params['text'] = $this->text;
        }

        $tg->send_request('answerCallbackQuery', $params);
    }
}