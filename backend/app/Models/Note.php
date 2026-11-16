<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'summary',
        'summary_status',
        'embedding',
    ];

    protected $hidden = [
        'embedding',
        'summary_status',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
