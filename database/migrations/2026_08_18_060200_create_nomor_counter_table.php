<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomor_counter', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 50);
            $table->string('periode', 10)->default('global');
            $table->unsignedBigInteger('nilai')->default(0);
            $table->timestamps();
            $table->unique(['kunci', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_counter');
    }
};
