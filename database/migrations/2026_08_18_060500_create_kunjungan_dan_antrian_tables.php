<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan', function (Blueprint $table) {
            $table->id();
            $table->string('no_kunjungan', 20)->unique();
            $table->foreignId('pasien_id')->constrained('pasien');
            $table->foreignId('poli_id')->constrained('poli');
            $table->foreignId('dokter_id')->constrained('dokter');
            $table->foreignId('penjamin_id')->constrained('penjamin');
            $table->string('no_kartu_penjamin', 30)->nullable();
            $table->enum('jenis_kunjungan', ['baru', 'lama']);
            $table->date('tanggal');
            $table->string('status', 20)->default('terdaftar');
            $table->timestamp('waktu_daftar')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->foreignId('didaftarkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tanggal', 'poli_id', 'status']);
        });

        Schema::create('antrian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('poli_id')->constrained('poli');
            $table->date('tanggal');
            $table->unsignedSmallInteger('nomor');
            $table->string('status', 20)->default('menunggu');
            $table->timestamp('waktu_panggil')->nullable();
            $table->timestamps();
            $table->unique(['poli_id', 'tanggal', 'nomor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrian');
        Schema::dropIfExists('kunjungan');
    }
};
