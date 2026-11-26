<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->string('name');
            $table->string('brand');
            $table->string('category')->index();
            $table->text('description');
            $table->unsignedInteger('initial_price');
            $table->unsignedInteger('selling_price');
            $table->unsignedInteger('stock')->default(0);
            $table->string('image')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};