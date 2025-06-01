<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class payment extends Model
{
    protected $table = 'payment';
    
    protected $fillable = [
        'payment_method',
        'status',
        'paid_at',
    ];

    protected $dates = ['paid_at'];
}
