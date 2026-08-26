<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rawat_inap', function (Blueprint $table) {
            $table->id();
            $table->string('no_rawat_inap', 20)->unique();
            // Unik: satu kunjungan satu masa rawat (aturan 60), dijamin basis
            // data dan bukan sekadar pemeriksaan di service.
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('dokter_id')->constrained('dokter');
            $table->foreignId('kelas_diminta_id')->constrained('kelas_kamar');
            $table->string('indikasi', 255);
            $table->string('status', 20)->default('dirawat');
            // Baru terisi saat pasien benar-benar menempati bed; perintah rawat
            // inap saja belum berarti pasien sudah masuk.
            $table->timestamp('waktu_masuk')->nullable();
            $table->timestamp('waktu_pulang')->nullable();
            $table->string('cara_pulang', 20)->nullable();
            $table->foreignId('diagnosa_akhir_id')->nullable()->constrained('icd10')->nullOnDelete();
            $table->text('ringkasan_pulang')->nullable();
            $table->foreignId('diperintahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dipulangkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('bed', function (Blueprint $table) {
            $table->foreign('rawat_inap_id')->references('id')->on('rawat_inap')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bed', function (Blueprint $table) {
            $table->dropForeign(['rawat_inap_id']);
        });

        Schema::dropIfExists('rawat_inap');
    }
};
