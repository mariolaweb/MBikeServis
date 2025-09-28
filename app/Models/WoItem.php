<?php

namespace App\Models;

use App\Enums\WoItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WoItem extends Model
{
    protected $table = 'wo_items';

    protected $fillable = [
        'work_order_id',
        'sku',
        'name',
        'kind',
        'qty',
        'unit_price',
        'line_total',
        'added_by',
        'removed_at',
        'removed_by',
    ];

    protected $casts = [
        // decimal cast drži broj decimala u serializaciji/atributima
        'qty'        => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'removed_at' => 'datetime',
        // eleganten guard za vrijednosti tipa
        'kind'       => WoItemKind::class, // ili 'string' ako ne želiš enum
    ];

    /* -------------------- Relationships -------------------- */

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function addedByUser()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function removedByUser()
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /* -------------------- Scopes -------------------- */

    // Samo aktivne (neuklonjene) stavke
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('removed_at');
    }

    // Samo uklonjene (soft remove preko removed_at)
    public function scopeRemoved(Builder $q): Builder
    {
        return $q->whereNotNull('removed_at');
    }

    /* -------------------- Mutators / Hooks -------------------- */

    protected static function booted(): void
    {
        static::saving(function (WoItem $item) {
            // Normalizuj prazne stringove na null
            if ($item->sku === '') $item->sku = null;

            // Ako koristiš enum cast, dopusti null vrijednost
            if ($item->kind instanceof WoItemKind === false && empty($item->kind)) {
                $item->kind = null;
            }

            // Sigurna matematika: qty * unit_price -> line_total
            // Castovi već osiguravaju decimal:2
            $qty  = (float) $item->qty ?: 0.0;
            $unit = (float) $item->unit_price ?: 0.0;
            $item->line_total = round($qty * $unit, 2);
        });
    }

    /* -------------------- Helpers -------------------- */

    // “Soft” uklanjanje bez brisanja reda
    public function markRemoved(?int $userId = null): bool
    {
        $this->removed_at = now();
        $this->removed_by = $userId;
        return $this->save();
    }

    // Poništi uklanjanje
    public function restoreRemoved(): bool
    {
        $this->removed_at = null;
        $this->removed_by = null;
        return $this->save();
    }
}
