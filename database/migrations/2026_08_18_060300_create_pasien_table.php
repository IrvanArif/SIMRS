<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasien', function (Blueprint $table) {
            $table->id();
            $table->string('no_rm', 10)->unique();
            $table->string('nik', 16)->unique();
            $table->string('nama', 100);
            $table->string('tempat_lahir', 60)->nullable();
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('alamat', 255);
            $table->string('rt', 3)->nullable();
            $table->string('rw', 3)->nullable();
            $table->string('kelurahan', 60)->nullable();
            $table->string('kecamatan', 60)->nullable();
            $table->string('kabupaten', 60)->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('pekerjaan', 60)->nullable();
            $table->string('agama', 20)->nullable();
            $table->string('status_perkawinan', 20)->nullable();
            $table->string('nama_penanggung_jawab', 100)->nullable();
            $table->string('hubungan_penanggung_jawab', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasien');
    }
};
