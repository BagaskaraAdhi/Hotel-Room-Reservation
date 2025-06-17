<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Room extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'room';

    protected $fillable = [
        'RoomNumber', 'RoomType', 'Capacity', 'Status', 'imageShowRoom',
        'defaultPrice', 'defaultExtraBedPrice', 'discount',
    ];

    protected $hidden = [
        'created_at', 'updated_at',
    ];
}
