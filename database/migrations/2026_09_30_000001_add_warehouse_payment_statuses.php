<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['supplier', 'partner_fee'] as $type) {
                foreach (['sbg', 'b'] as $warehouse) {
                    $table->boolean("is_{$type}_{$warehouse}_paid")->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['is_supplier_sbg_paid', 'is_supplier_b_paid', 'is_partner_fee_sbg_paid', 'is_partner_fee_b_paid']);
        });
    }
};
