<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverOrderDetailOCR extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'D受注明細_OCR';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'D受注明細_OCR_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = [
        '管轄部門CD',
        'OCR受注伝票NO',
        'OCR受注伝票行NO',
        '商品CD',
        '呼び径1',
        '呼び径2',
        '呼び径3',
        '商品名',
        '数量',
        '備考',
        '得意先CD',
        '予備文字項目1',
        '予備文字項目2',
        '予備文字項目3',
        '登録日',
        '最終更新日',
        '最終更新時間',
        '最終更新ID',
    ];


}