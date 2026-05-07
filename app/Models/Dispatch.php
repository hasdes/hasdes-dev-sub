<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    use HasFactory;

    // テーブル名を指定
    protected $table = 'D出荷予定';

    // 主キーが 'id' ではない場合、以下のように設定
    protected $primaryKey = 'D出荷予定_ID';

    // タイムスタンプを使用しない場合は以下を追加
    public $timestamps = false;

    // マスアサインメントを許可するカラム
    // protected $fillable = [
    //     '車両',
    //     '積み下ろし順',
    //     'その他情報',
    //     '地図リンク',
    // ];

}
