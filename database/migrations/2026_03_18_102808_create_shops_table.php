<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('address');
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->string('zipcode')->nullable();
            $table->string('logo');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['vendor_id', 'name', 'state', 'city', 'zipcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
