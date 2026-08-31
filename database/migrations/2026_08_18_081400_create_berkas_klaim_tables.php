<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkas_klaim', function (Blueprint $table) {
            $table->id();
            $table->string('no_berkas', 20)->unique();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('sep_id')->nullable()->constrained('sep')->nullOnDelete();
            // Identitas disalin, bukan dibaca lewat relasi: berkas klaim adalah
            // potret pada saat pengajuan. Nama pasien yang dikoreksi setahun
            // kemudian tidak boleh mengubah berkas yang sudah dikirim.
            $table->string('no_kartu', 30);
            $table->string('nama_peserta', 150);
            $table->string('jenis_pelayanan', 2);
            $table->string('kelas_rawat', 20)->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_pulang')->nullable();
            $table->unsignedSmallInteger('lama_rawat')->nullable();
            $table->unsignedBigInteger('total_biaya');
            $table->string('status', 20)->default('draf');
            $table->text('peringatan')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('diajukan_pada')->nullable();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->foreignId('disusun_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Satu berkas berlaku per kunjungan (aturan 87). Yang batal boleh
            // menumpuk, jadi kuncinya menyertakan status batal sebagai pembeda.
            $table->unique(['kunjungan_id', 'status'], 'berkas_klaim_berlaku_unik');
        });

        Schema::create('berkas_klaim_diagnosa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('berkas_klaim_id')->constrained('berkas_klaim')->cascadeOnDelete();
            $table->string('kode', 10);
            $table->string('nama', 255);
            $table->enum('jenis', ['primer', 'sekunder']);
            $table->timestamps();
        });

        Schema::create('berkas_klaim_prosedur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('berkas_klaim_id')->constrained('berkas_klaim')->cascadeOnDelete();
            $table->string('kode', 10);
            $table->string('nama', 255);
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkas_klaim_prosedur');
        Schema::dropIfExists('berkas_klaim_diagnosa');
        Schema::dropIfExists('berkas_klaim');
    }
};
