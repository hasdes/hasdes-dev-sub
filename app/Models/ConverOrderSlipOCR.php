<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverOrderSlipOCR extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'D受注伝票_OCR';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'D受注伝票_OCR_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = [
        "D受注伝票_OCR_ID",
        "管轄部門CD",
        "OCR受注伝票NO",
        "受注日付",
        "ヘッダ希望納期",
        "土日着日指定",
        "得意先CD",
        "出荷先CD",
        "送り状印字内容",
        "担当者CD",
        "営業部門CD",
        "相手先注文NO_得意先",
        "相手先注文NO_出荷先",
        "営業用備考",
        '予備文字項目1',
        '予備文字項目2',
        '予備文字項目3',
        '登録日',
        '最終更新日',
        '最終更新時間',
        '最終更新ID',
    ];

}
