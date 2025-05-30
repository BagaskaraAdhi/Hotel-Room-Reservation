<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class payment extends Model
{
    //
    protected $fillable = [
        'reservation_id', 'payment_method', 'payment_status', 'paid_at',
    ];

}
