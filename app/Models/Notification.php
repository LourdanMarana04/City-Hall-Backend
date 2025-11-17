<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'created_by',
        'is_broadcast',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function readBy()
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function isReadBy($userId)
    {
        return $this->readBy()
            ->where('user_id', $userId)
            ->exists();
    }
}
