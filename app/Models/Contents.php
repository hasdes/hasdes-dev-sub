<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contents extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'HMコンテンツ';

    
    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'Mコンテンツ_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    // マスアサインメント可能なフィールドを指定
    protected $fillable = [
        '品名CD',
        'ジャンル',
        'タイトル',
        '詳細',
        'YouTube動画リンク',
        'files', // ファイルパス
    ];

    // 'files'フィールドをJSONとしてキャスト
    protected $casts = [
        'files' => 'array',
    ];
}
