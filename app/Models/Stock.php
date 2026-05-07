<?php
//stock

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'C商品月間';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'C商品月間_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;
}