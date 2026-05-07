<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSlipOCR extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HD受注伝票_OCR';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'HD受注伝票_OCR_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = [
        '管轄部門CD', 'ヘッダ希望納期', '得意先名', '出荷先名', '送り状印字内容', '相手先注文NO_得意先', '土日着日指定', '相手先注文NO_出荷先', '営業用備考',
        'PDF', 'OCR受注伝票NO'
    ];

}