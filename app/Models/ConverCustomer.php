<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverCustomer extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HD変換得意先';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'HD変換得意先_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = ['得意先CD', '変換名'];

}