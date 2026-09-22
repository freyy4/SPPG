<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Identitas & Detail Barang
            $table->string('po_number')->unique();
            $table->string('item_name');                               // Contoh: Muli, Lengkeng
            $table->string('category')->nullable();                    // Karbo, Protein Nabati, Protein Hewani, Sayur, Buah
            $table->decimal('quantity', 10, 2);                        // 106.00
            $table->string('unit', 50)->default('Kg');                 // Kg, Pcs, Box
            $table->date('transaction_date');                          // Tanggal transaksi (misal: 2026-08-23)
            
            // Status & Prioritas (Menggunakan STRING agar fleksibel)
            $table->string('priority')->default('Normal');             // Normal, High, Low, Urgent
            $table->string('status_stage')->default('Cari Supplier');  // Cari Supplier, Sortir, Pengiriman, Selesai, Retur, Kekurangan, Perubahan Item

            // Supplier & Entitas Pembuat
            $table->string('supplier_name')->nullable();               // Contoh: Indra
            $table->string('region')->default('Pusat');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Struktur Harga Unit
            $table->decimal('supplier_price', 15, 2)->default(0);      // Harga Supplier (8.500)
            $table->decimal('maker_price', 15, 2)->default(0);         // Harga Maker (13.500)
            $table->decimal('partner_margin', 15, 2)->default(0);      // Laba Mitra/unit (3.000)

            // Status Pembayaran (Tombol Aksi)
            $table->boolean('is_partner_fee_paid')->default(false);    // Status Bayar Mitra ($ Bayar Mitra)
            $table->boolean('is_supplier_paid')->default(false);       // Status Bayar Supplier ($ Bayar Supplier)
            $table->boolean('is_ksppg_fee_paid')->default(false);      // Status Bayar KSPPG ($ Bayar KSPPG)

            // Catatan Tambahan
            $table->text('notes')->nullable();                         // Contoh: Naik 11kg 148000

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};