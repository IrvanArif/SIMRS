<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan_lab', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->enum('kategori', ['hematologi', 'kimia_klinik', 'urinalisis', 'imunologi', 'mikrobiologi']);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('parameter_lab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemeriksaan_lab_id')->constrained('pemeriksaan_lab')->cascadeOnDelete();
            $table->string('kode', 20);
            $table->string('nama', 100);
            $table->string('satuan', 20)->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->unique(['pemeriksaan_lab_id', 'kode']);
        });

        Schema::create('rujukan_lab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_lab_id')->constrained('parameter_lab')->cascadeOnDelete();
            $table->enum('jenis_kelamin', ['L', 'P', 'semua']);
            $table->decimal('nilai_min', 10, 2);
            $table->decimal('nilai_maks', 10, 2);
            $table->timestamps();
            $table->unique(['parameter_lab_id', 'jenis_kelamin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rujukan_lab');
        Schema::dropIfExists('parameter_lab');
        Schema::dropIfExists('pemeriksaan_lab');
    }
};
