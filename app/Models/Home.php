<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Home extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'Mコンテンツ';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'Mコンテンツ_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;
}