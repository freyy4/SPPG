<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginPopupSetting extends Model
{
    protected $fillable = ['user_id', 'enabled', 'title', 'message'];

    protected $casts = ['enabled' => 'boolean'];
}
