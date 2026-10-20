<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // NULL preserves the original quantity as SBG for historical orders.
            $table->decimal('quantity_sbg', 10, 2)->nullable();
            $table->decimal('quantity_b', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['quantity_sbg', 'quantity_b']);
        });
    }
};
