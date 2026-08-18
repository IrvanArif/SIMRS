<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resep', function (Blueprint $table) {
            $table->id();
            $table->string('no_resep', 20)->unique();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('dokter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('dibuat');
            $table->timestamps();
        });

        Schema::create('resep_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_id')->constrained('resep')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat');
            $table->unsignedSmallInteger('jumlah');
            $table->string('aturan_pakai', 100);
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
            $table->unique(['resep_id', 'obat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resep_detail');
        Schema::dropIfExists('resep');
    }
};
