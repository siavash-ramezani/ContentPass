<?php

namespace App\Models;

use Database\Factories\ContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    /** @use HasFactory<ContentFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'video_url',
        'type',
        'required_plan_level',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'required_plan_level' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
