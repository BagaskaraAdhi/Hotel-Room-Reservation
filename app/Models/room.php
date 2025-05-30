<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class room extends Model
{
    protected $fillable = [
        'RoomNumber', 'Type', 'capacity', 'status', 'imagesRoom',
        'default_price', 'default_extra_bed_price',
    ];

}
