<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poli', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->string('lokasi', 100)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('dokter', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->string('nama', 100);
            $table->string('spesialisasi', 100)->nullable();
            $table->string('no_sip', 50)->nullable();
            $table->foreignId('poli_id')->constrained('poli');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('jadwal_dokter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokter_id')->constrained('dokter')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->unsignedSmallInteger('kuota')->default(30);
            $table->timestamps();
            $table->unique(['dokter_id', 'hari', 'jam_mulai']);
        });

        Schema::create('penjamin', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->enum('jenis', ['tunai', 'penjamin']);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('tindakan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->enum('kategori', ['administrasi', 'konsultasi', 'tindakan_medis']);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('tarif_tindakan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tindakan_id')->constrained('tindakan')->cascadeOnDelete();
            $table->foreignId('penjamin_id')->constrained('penjamin');
            $table->unsignedBigInteger('tarif');
            $table->date('berlaku_mulai');
            $table->timestamps();
            $table->unique(['tindakan_id', 'penjamin_id', 'berlaku_mulai']);
        });

        Schema::create('icd10', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama_id', 255);
            $table->string('nama_en', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('obat', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->string('satuan', 20);
            $table->string('bentuk_sediaan', 50)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obat');
        Schema::dropIfExists('icd10');
        Schema::dropIfExists('tarif_tindakan');
        Schema::dropIfExists('tindakan');
        Schema::dropIfExists('penjamin');
        Schema::dropIfExists('jadwal_dokter');
        Schema::dropIfExists('dokter');
        Schema::dropIfExists('poli');
    }
};
