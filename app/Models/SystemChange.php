<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'scope',
        'action',
        'message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}

