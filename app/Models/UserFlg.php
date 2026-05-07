<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UserFlg extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'HM担当者フラグ';
    protected $primaryKey = '担当者CD';
    public $incrementing = false; // 自動インクリメントではない場合、falseに設定
    protected $keyType = 'string'; // プライマリキーのタイプが文字列の場合に指定

    public $timestamps = false; // created_at、updated_atを無効化

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        '担当者CD', // emailの代わりに担当者CD
        'PASSWORD', // passwordの代わりにPASSWORD
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'PASSWORD', // passwordの代わりにPASSWORD
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [        
        'PASSWORD' => 'hashed', // passwordの代わりにPASSWORD
    ];

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->PASSWORD; // Laravelがpasswordの代わりにPASSWORDを使う
    }
}
