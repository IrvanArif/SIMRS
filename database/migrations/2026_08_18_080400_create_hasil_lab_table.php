<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_lab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_lab_detail_id')->constrained('order_lab_detail')->cascadeOnDelete();
            $table->foreignId('parameter_lab_id')->constrained('parameter_lab');
            $table->decimal('nilai', 12, 2);
            // Boleh kosong: parameter tanpa rujukan yang cocok tersimpan tanpa
            // penanda, bukan ditebak (aturan 41).
            $table->string('penanda', 10)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
            $table->unique(['order_lab_detail_id', 'parameter_lab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_lab');
    }
};
