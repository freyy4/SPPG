<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruit_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('region')->index();
            $table->string('name', 100);
            $table->string('unit', 20);
            $table->decimal('units_per_kg', 12, 4);
            $table->decimal('portion_pb', 10, 4);
            $table->decimal('portion_pk', 10, 4);
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('fruit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fruit_id')->constrained('fruit_profiles');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('price', 14, 2);
            $table->timestamp('created_at');
            $table->index(['fruit_id', 'created_at']);
        });
        Schema::create('fruit_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('region')->index();
            $table->uuid('request_key');
            $table->date('decision_date');
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruit_decisions');
        Schema::dropIfExists('fruit_prices');
        Schema::dropIfExists('fruit_profiles');
    }
};
