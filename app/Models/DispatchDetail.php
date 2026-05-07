<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispatchDetail extends Model
{
    use HasFactory;

    protected $table = 'HD出荷詳細'; // テーブル名

    protected $primaryKey = 'HD出荷詳細_ID'; // 主キー

    public $timestamps = false; // Laravelのタイムスタンプ列（created_at, updated_at）を使わない

    protected $fillable = [
        '出荷予定日',
        '出荷先CD',
        '車両',
        '積み下ろし順',
        'その他情報',
        '地図リンク',
    ];

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class, '出荷予定日', '出荷予定日')
                    ->whereColumn('出荷先CD', '出荷先CD');
    }
}
