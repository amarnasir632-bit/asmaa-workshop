<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'alternate_phone', 'notes'];

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
