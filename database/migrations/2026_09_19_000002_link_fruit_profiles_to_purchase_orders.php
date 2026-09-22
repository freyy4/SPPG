<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fruit_profiles', function (Blueprint $table) {
            $table->string('name', 255)->change();
            $table->foreignId('source_po_id')->nullable()->constrained('purchase_orders');
        });
    }

    public function down(): void
    {
        Schema::table('fruit_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_po_id');
        });
    }
};
