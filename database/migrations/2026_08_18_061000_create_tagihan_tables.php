<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('no_tagihan', 20)->unique();
            $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
            $table->foreignId('penjamin_id')->constrained('penjamin');
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('ditanggung_penjamin')->default(0);
            $table->unsignedBigInteger('ditagihkan_ke_pasien')->default(0);
            $table->string('status', 25)->default('belum_bayar');
            $table->timestamp('disusun_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('tagihan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('tindakan_kunjungan_id')->nullable()->constrained('tindakan_kunjungan')->nullOnDelete();
            $table->string('deskripsi', 150);
            $table->unsignedSmallInteger('jumlah');
            $table->unsignedBigInteger('tarif_satuan');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_detail');
        Schema::dropIfExists('tagihan');
    }
};
