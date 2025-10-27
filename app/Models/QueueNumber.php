<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'transaction_id',
        'queue_number',
        'full_queue_number',
        'status',
        'started_at',
        'cancel_reason',
        'source',
        'completed_at',
        'duration_minutes',
        'user_id',
        'priority',
        'is_senior_citizen',
        'citizen_id',
        // Added persisted details for reporting
        'citizen_name',
        'property_address',
        'assessment_value',
        'tax_amount',
        'assigned_staff',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_minutes' => 'integer',
        'is_senior_citizen' => 'boolean',
        'assessment_value' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function citizen()
    {
        return $this->belongsTo(Citizen::class);
    }
}
