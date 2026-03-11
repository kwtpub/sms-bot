<?php

namespace App\Models\chatBot\traits;

trait referral
{
    public function referral_info($edit = 1)
    {
        $this->main->keyBoard->add('Назад', 'lk');

        $text = implode("\n", [
            '<b>Реферальная ссылка</b>',
            '',
            'Ссылка:',
            '<code>' . e($this->getReferralLink()) . '</code>',
            '',
            'Приглашено пользователей: <b>' . $this->getReferralCount() . '</b>',
            'Начислено бонусов: <b>' . $this->formatMoney($this->getReferralBonusAmount()) . '</b>',
        ]);

        return $this->sendOrEditMsg($edit, $text);
    }
}
