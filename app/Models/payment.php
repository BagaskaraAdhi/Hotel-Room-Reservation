<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class payment extends Model
{
    protected $table = 'payment';

    protected $fillable = [
        'reservation_id',
        'payment_method',
        'status',
        'paid_at',
        'amount',
    ];
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    protected $dates = ['paid_at'];
}
