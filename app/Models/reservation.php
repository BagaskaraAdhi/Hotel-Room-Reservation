<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class reservation extends Model
{
    //
    protected $fillable = [
        'user_id', 'room_id', 'check_in_date', 'check_out_date',
        'extra_beds', 'price', 'discount', 'extra_beds_price',
        'total_price', 'status'
    ];

}
