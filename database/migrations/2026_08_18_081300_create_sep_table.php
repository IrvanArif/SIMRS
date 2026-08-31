<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sep', function (Blueprint $table) {
            $table->id();
            $table->string('no_sep', 30)->unique();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->string('no_kartu', 30);
            $table->string('jenis_pelayanan', 2);
            $table->string('kelas_rawat', 20)->nullable();
            $table->string('diagnosa_awal', 255);
            $table->string('no_rujukan', 40)->nullable();
            $table->date('tanggal');
            $table->string('status', 20)->default('berlaku');
            // Mencatat penerapan mana yang menerbitkannya, supaya nomor hasil
            // simulasi tidak pernah tertukar dengan nomor sungguhan saat
            // kredensial BPJS akhirnya tersedia.
            $table->string('diterbitkan_dengan', 30)->default('lokal');
            $table->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Satu SEP berlaku per kunjungan (aturan 79). Yang batal boleh
            // menumpuk, jadi kuncinya menyertakan status.
            $table->unique(['kunjungan_id', 'status'], 'sep_berlaku_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sep');
    }
};
