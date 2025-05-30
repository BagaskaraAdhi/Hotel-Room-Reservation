<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class reviews extends Model
{
    //
    protected $fillable = [
        'reservation_id', 'user_id', 'room_id', 'rating', 'comment'
    ];

}
