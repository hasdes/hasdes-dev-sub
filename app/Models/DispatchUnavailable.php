<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispatchUnavailable extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HD配車不可';

    // 主キーを指定（もしあれば）
    protected $primaryKey = 'HD配車不可_ID'; // テーブル設計に応じて変更

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    // マスアサインメントを許可するカラム
    protected $fillable = [
        '工場部門CD',
        '出荷予定日',
        '配車不可',
    ];
}