<?php

namespace App\Services;

use Carbon\Carbon;
use Yasumi\Yasumi;
use Illuminate\Support\Facades\Log;
use App\Models\Syozokubumon;



// ＜受注確認データ Ocr/Edit.vue＞ 出荷日、納期　受付時刻締切に対する日付調整
class ShippingDateService
{
    /**
     * ヘッダ希望納期（Ymdまたはワード）に応じて出荷日を補正する
     */
    public function adjustShippingDate(string $input, int $office): string
    {
        $now = Carbon::now();//現在時刻
        $holidays = Yasumi::create('Japan', (int)$now->format('Y'));//祝日

        // ---------- 最短系ワード判定 ----------
        if (preg_match('/(最短|即日|即納|至急|最短納期|早急|本日|当日)/u', $input)) {
            return $this->adjustBasedOnNow($now, $holidays, $office);
        }

        // ---------- Ymdの日付が今日と一致する場合 ----------
        if (preg_match('/^\d{8}$/', $input)) {
            if ($input === $now->format('Ymd')) {
                // return $this->getBasedOnNow($now, $holidays, $office);
                $adjusted = $this->adjustBasedOnNow($now, $holidays, $office);

                //デバック
                // Log::debug('ShippingDate adjusted from today match', [
                //     'input' => $input,
                //     'adjusted_date' => $adjusted,
                //     'office' => $office,
                //     'now' => $now->format('Y-m-d H:i'),
                // ]);

                return $adjusted;
            }
            return $input; // 本日以外の指定はそのまま返す
        }

        // ---------- その他（文字列や不明な日付） ----------
        return $input;
    }

    /**
     * 「本日」が出荷可能かどうかを判断し、不可なら翌営業日を返す 
     * ●工場向け出荷： 14:30
     * ●デポ向け出荷： 14:50
     */
    private function adjustBasedOnNow(Carbon $now, $holidays, int $office): string
    {
        if (
            $this->isHolidayOrWeekend($now, $holidays) ||
            // $now->format('H:i') >= $this->getCutoffTimeByOffice($office)
            $now->format('H:i') >= '14:30' //工場デポ共に14:30 (2026/02/06修正）
        ) {
            return $this->getNextBusinessDay($now);
        }

        return $now->format('Ymd');
    }

    /**
     * 翌営業日を取得（Carbonを返しても良いがここではYmdで）
     */
    public function getNextBusinessDay(Carbon $start): string
    {
        $holidays = Yasumi::create('Japan', $start->year);
        $date = $start->copy();

        do {
            $date->addDay();
        } while ($this->isHolidayOrWeekend($date, $holidays));

        return $date->format('Ymd');
    }

    /**
     * 土日祝の判定
     */
    private function isHolidayOrWeekend(Carbon $date, $holidays): bool
    {
        return $date->isWeekend() || $holidays->isHoliday($date);
    }

    /**
     * 営業所ごとの締切時刻
     */
    // // private function getCutoffTimeByOffice(int $office): string
    // public function getCutoffTimeByOffice(int $office): string
    // {
    //     // 担当工場部門CDを取得
    //     $tantokojoCD = Syozokubumon::where('部門CD', $office)
    //         ->value('担当工場部門CD');

    //     // 担当工場部門CDが取得できなかった場合のフォールバック
    //     if (is_null($tantokojoCD)) {
    //         return '14:30'; // データがなければ早めにしておく
    //     }

    //     // 条件分岐(工場は14:30,デポは14:50)
    //     if (in_array($tantokojoCD, [1, 2, 3], true)) {
    //         return '14:30';
    //     } else {
    //         return '14:50';
    //     }
    // }
}
