<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GarmentType extends Model
{
    protected $fillable = ['name', 'requires_size', 'requires_color', 'is_active'];

    protected function casts(): array
    {
        return [
            'requires_size' => 'boolean',
            'requires_color' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
