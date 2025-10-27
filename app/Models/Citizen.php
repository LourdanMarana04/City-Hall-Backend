<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Citizen extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'citizens';

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'birth_date',
        'birthday',
        'gender',
        'mobile_number',
        'phone_number',
        'is_resident',
        'is_senior_citizen',
        'address',
        'is_verified',
        'security_question',
        'security_answer',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birthday' => 'date',
        'is_resident' => 'boolean',
        'is_senior_citizen' => 'boolean',
        'is_verified' => 'boolean',
    ];
}


