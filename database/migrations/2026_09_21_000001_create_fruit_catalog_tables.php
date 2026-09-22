<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruit_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('region')->index();
            $table->string('name');
            $table->string('unit', 20);
            $table->decimal('units_per_kg', 12, 4);
            $table->decimal('portion_pb', 10, 4);
            $table->decimal('portion_pk', 10, 4);
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('fruit_catalog_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fruit_id')->constrained('fruit_catalog');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('price', 14, 2);
            $table->timestamp('created_at');
            $table->index(['fruit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruit_catalog_prices');
        Schema::dropIfExists('fruit_catalog');
    }
};
