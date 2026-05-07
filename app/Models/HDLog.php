<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HDLog extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HDログ';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'HDログID';

    protected $fillable = [
        '担当者CD' ,
        'ログ種別', 
        '実行内容',
        'SQL種別',
        'エラー', 
        'SQL文',
        '登録日時',
    ];


    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;
}

