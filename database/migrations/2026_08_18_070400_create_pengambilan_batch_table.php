<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengambilan_batch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_detail_id')->constrained('resep_detail')->cascadeOnDelete();
            $table->foreignId('batch_obat_id')->constrained('batch_obat');
            $table->unsignedInteger('jumlah');
            $table->unsignedBigInteger('harga_beli');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengambilan_batch');
    }
};
