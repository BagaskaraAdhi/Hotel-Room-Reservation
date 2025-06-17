<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class reviews extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'reviews';

    protected $fillable = [
        'user_id', 
        'reservation_id', 
        'rating', 
        'comment'
    ];

    protected $hidden = [
        'created_at', 
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }   

    public function reservation()
    {
        return $this->belongsTo(reservation::class);
    }
}
