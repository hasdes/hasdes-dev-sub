<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConverShipping extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HD変換出荷先';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'HD変換出荷先_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    protected $fillable = ['出荷先_エンドユーザーCD', '管轄部門CD', 'M出荷先_管轄部門CD', '変換名'];

}