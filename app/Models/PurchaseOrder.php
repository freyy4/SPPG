<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_orders';

    /**
     * Field yang dapat diisi secara massal.
     */
    protected $fillable = [
        'po_number',
        'item_name',
        'category',
        'quantity',
        'unit',
        'transaction_date',
        'priority',
        'status_stage',
        'supplier_name',
        'region',
        'created_by',
        'supplier_price',
        'maker_price',
        'partner_margin',
        'is_partner_fee_paid',
        'is_supplier_paid',
        'is_ksppg_fee_paid',
        'notes',
    ];

    /**
     * Konversi tipe data otomatis (Casting).
     */
    protected $casts = [
        'transaction_date'    => 'date',
        'quantity'            => 'float',
        'supplier_price'      => 'float',
        'maker_price'         => 'float',
        'partner_margin'      => 'float',
        'is_partner_fee_paid' => 'boolean',
        'is_supplier_paid'    => 'boolean',
        'is_ksppg_fee_paid'   => 'boolean',
    ];

    /**
     * Appends atribut kustom ke dalam Serialisasi JSON / Array.
     * Ini membuat total otomatis tersedia di Vue/Alpine.js secara gratis.
     */
    protected $appends = [
        'total_supplier_amount',
        'total_partner_fee',
        'total_ksppg_fee',
        'ksppg_margin_per_unit',
        'verification_url',
    ];

    /* -------------------------------------------------------------------------- */
    /*                                 RELASI                                     */
    /* -------------------------------------------------------------------------- */

    /**
     * Relasi ke User pembuat Purchase Order.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* -------------------------------------------------------------------------- */
    /*                                ACCESSORS                                   */
    /* -------------------------------------------------------------------------- */

    /**
     * Hitung Margin / Laba KSPPG per unit.
     * Rumus: Harga Maker - Harga Supplier - Laba Mitra
     */
    public function getKsppgMarginPerUnitAttribute()
    {
        if (!$this->supplier_price) {
            return 0;
        }

        return $this->maker_price - $this->supplier_price - $this->partner_margin;
    }

    /**
     * Signed URL publik untuk verifikasi keaslian Nota Dapur.
     */
    public function getVerificationUrlAttribute(): string
    {
        return URL::signedRoute('purchase-orders.verify', [
            'purchaseOrder' => $this->getKey(),
        ], null, false);
    }

    /**
     * Total Bayar Supplier.
     * Rumus: Qty * Harga Supplier
     */
    public function getTotalSupplierAmountAttribute()
    {
        return $this->quantity * $this->supplier_price;
    }

    /**
     * Total Laba Mitra.
     * Rumus: Qty * Laba Mitra per unit
     */
    public function getTotalPartnerFeeAttribute()
    {
        return $this->quantity * $this->partner_margin;
    }

    /**
     * Total Laba KSPPG.
     * Rumus: Qty * Laba KSPPG per unit
     */
    public function getTotalKsppgFeeAttribute()
    {
        return $this->quantity * $this->ksppg_margin_per_unit;
    }
}
