<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_obat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obat_id')->constrained('obat');
            $table->string('no_batch', 40);
            $table->date('tanggal_kedaluwarsa');
            $table->unsignedInteger('jumlah_awal');
            $table->unsignedInteger('jumlah_tersisa');
            $table->unsignedBigInteger('harga_beli')->default(0);
            $table->timestamp('diterima_pada')->nullable();
            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['obat_id', 'no_batch']);
            $table->index(['obat_id', 'tanggal_kedaluwarsa']);
        });

        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_obat_id')->constrained('batch_obat')->cascadeOnDelete();
            $table->foreignId('obat_id')->constrained('obat');
            $table->string('jenis', 20);
            // Bertanda, bukan unsigned: mutasi keluar dicatat negatif supaya kartu
            // stok bisa dijumlahkan langsung tanpa memeriksa jenisnya.
            $table->integer('jumlah');
            $table->unsignedInteger('stok_sesudah');
            $table->foreignId('resep_id')->nullable()->constrained('resep')->nullOnDelete();
            $table->string('catatan', 255)->nullable();
            $table->foreignId('dilakukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['obat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
        Schema::dropIfExists('batch_obat');
    }
};
