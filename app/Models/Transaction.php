<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'archived',
        'requirements',
        'procedures',
    ];

    protected $casts = [
        'requirements' => 'array',
        'procedures' => 'array',
        'archived' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
