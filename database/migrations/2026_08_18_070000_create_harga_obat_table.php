<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harga_obat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obat_id')->constrained('obat')->cascadeOnDelete();
            $table->foreignId('penjamin_id')->constrained('penjamin');
            $table->unsignedBigInteger('harga');
            $table->date('berlaku_mulai');
            $table->timestamps();
            $table->unique(['obat_id', 'penjamin_id', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harga_obat');
    }
};
