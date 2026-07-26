<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'title',
        'category',
        'priority',
        'status',
        'reporter',
        'assignee',
        'description',
        'replies',
    ];

    protected $casts = [
        'replies' => 'array',
    ];
}
