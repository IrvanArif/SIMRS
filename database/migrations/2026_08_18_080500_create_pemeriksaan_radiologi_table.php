<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan_radiologi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->enum('modalitas', ['rontgen', 'usg', 'ct_scan', 'mri', 'mammografi']);
            // Instruksi yang harus disampaikan ke pasien sebelum datang, misalnya
            // puasa atau melepas benda logam. Tidak semua pemeriksaan punya.
            $table->string('persiapan', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_radiologi');
    }
};
