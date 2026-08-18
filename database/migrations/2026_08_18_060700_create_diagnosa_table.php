<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('icd10_id')->constrained('icd10');
            $table->enum('jenis', ['primer', 'sekunder']);
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
            $table->unique(['kunjungan_id', 'icd10_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosa');
    }
};
