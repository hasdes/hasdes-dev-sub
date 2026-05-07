<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemMessage extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HMメッセージ';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'Mメッセージ_ID';
    protected $fillable = [
        'メッセージCD', // メッセージコード
        '送信者CD',    // 送信者コード
        '受信者CD',    // 受信者コード
        'メッセージ',  // メッセージ内容
    ];

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;
}