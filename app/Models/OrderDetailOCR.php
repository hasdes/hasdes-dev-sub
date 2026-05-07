<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetailOCR extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HD受注明細_OCR';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'HD受注明細_OCR_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = [
        'HD受注明細_OCR_ID', 'HD受注伝票_OCR_ID', '商品名', '呼び径1', '呼び径2', '呼び径3', '数量', '備考', '消去日時',
    ];

}