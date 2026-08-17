<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Model;

class CaseStatus extends Model
{
    use BelongsToFirm;

    protected $fillable = [
        'firm_id',
        'slug',
        'name',
        'color',
        'sort_order',
        'is_closed',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }
}
