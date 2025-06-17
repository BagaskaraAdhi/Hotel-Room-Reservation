<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class reservation extends Model
{
    protected $table = 'reservation';

    protected $fillable = [
        'user_id', 'room_id', 'check_in_date', 'check_out_date',
        'extra_beds', 'price', 'discount', 'extra_beds_price',
        'total_price', 'status'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

}
