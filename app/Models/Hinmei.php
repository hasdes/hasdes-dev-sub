<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hinmei extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'M品名';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = '品名_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;
}