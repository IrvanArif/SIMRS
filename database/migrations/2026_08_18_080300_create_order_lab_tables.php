<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lab', function (Blueprint $table) {
            $table->id();
            $table->string('no_order', 20)->unique();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('dokter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('dipesan');
            $table->string('catatan_klinis', 255)->nullable();
            $table->timestamp('waktu_sampel')->nullable();
            $table->foreignId('diambil_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waktu_hasil')->nullable();
            $table->foreignId('dientri_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waktu_validasi')->nullable();
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kunjungan_id', 'status']);
        });

        Schema::create('order_lab_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_lab_id')->constrained('order_lab')->cascadeOnDelete();
            $table->foreignId('pemeriksaan_lab_id')->constrained('pemeriksaan_lab');
            $table->unsignedBigInteger('tarif_satuan');
            $table->timestamps();
            $table->unique(['order_lab_id', 'pemeriksaan_lab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lab_detail');
        Schema::dropIfExists('order_lab');
    }
};
