<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number', 'type', 'guardian_id', 'customer_name_snapshot', 'created_by', 'created_on', 'due_on',
        'operational_status', 'notes', 'items_total', 'discount_total', 'approved_total', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'created_on' => 'date',
            'due_on' => 'date',
            'cancelled_at' => 'datetime',
            'items_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'approved_total' => 'decimal:2',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolContract(): HasOne
    {
        return $this->hasOne(SchoolContract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(Cancellation::class);
    }

    public function financialCorrections(): HasMany
    {
        return $this->hasMany(FinancialCorrection::class);
    }
}
