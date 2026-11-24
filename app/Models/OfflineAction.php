<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineAction extends Model
{
    protected $fillable = ['uuid', 'user_id', 'type'];
}
