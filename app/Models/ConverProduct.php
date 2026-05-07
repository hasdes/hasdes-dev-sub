<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverProduct extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HD変換商品';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'HD変換商品_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = ['商品CD', '変換名'];

}