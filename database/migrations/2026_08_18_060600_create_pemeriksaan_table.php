<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->unsignedSmallInteger('sistolik')->nullable();
            $table->unsignedSmallInteger('diastolik')->nullable();
            $table->unsignedSmallInteger('nadi')->nullable();
            $table->decimal('suhu', 4, 1)->nullable();
            $table->unsignedSmallInteger('respirasi')->nullable();
            $table->decimal('berat_badan', 5, 1)->nullable();
            $table->unsignedSmallInteger('tinggi_badan')->nullable();
            $table->text('keluhan_awal')->nullable();
            $table->string('alergi', 255)->nullable();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->foreignId('dicatat_perawat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dicatat_dokter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waktu_perawat')->nullable();
            $table->timestamp('waktu_dokter')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan');
    }
};
