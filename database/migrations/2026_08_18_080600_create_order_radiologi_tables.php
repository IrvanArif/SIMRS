<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_radiologi', function (Blueprint $table) {
            $table->id();
            $table->string('no_order', 20)->unique();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('dokter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('dipesan');
            $table->string('indikasi_klinis', 255);
            $table->timestamp('waktu_dikerjakan')->nullable();
            $table->foreignId('dikerjakan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('no_film', 50)->nullable();
            $table->timestamp('waktu_ekspertise')->nullable();
            $table->foreignId('ditulis_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kunjungan_id', 'status']);
        });

        Schema::create('order_radiologi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_radiologi_id')->constrained('order_radiologi')->cascadeOnDelete();
            $table->foreignId('pemeriksaan_radiologi_id')->constrained('pemeriksaan_radiologi');
            $table->unsignedBigInteger('tarif_satuan');
            $table->timestamps();
            $table->unique(['order_radiologi_id', 'pemeriksaan_radiologi_id'], 'order_radiologi_detail_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_radiologi_detail');
        Schema::dropIfExists('order_radiologi');
    }
};
