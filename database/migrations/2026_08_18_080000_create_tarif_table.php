<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_layanan', 20);
            $table->unsignedBigInteger('layanan_id');
            $table->foreignId('penjamin_id')->constrained('penjamin');
            $table->unsignedBigInteger('harga');
            $table->date('berlaku_mulai');
            $table->timestamps();
            $table->unique(['jenis_layanan', 'layanan_id', 'penjamin_id', 'berlaku_mulai'], 'tarif_unik');
            $table->index(['jenis_layanan', 'layanan_id']);
        });

        // Pindahkan isi kedua tabel lama sebelum keduanya dihapus, supaya data
        // harga yang sudah ada tidak hilang saat migrasi dijalankan pada basis
        // data yang sudah terisi.
        foreach ([
            ['tarif_tindakan', 'tindakan', 'tindakan_id', 'tarif'],
            ['harga_obat', 'obat', 'obat_id', 'harga'],
        ] as [$tabelLama, $jenis, $kolomLayanan, $kolomHarga]) {
            if (! Schema::hasTable($tabelLama)) {
                continue;
            }

            DB::table($tabelLama)->orderBy('id')->chunk(200, function ($baris) use ($jenis, $kolomLayanan, $kolomHarga) {
                DB::table('tarif')->insert($baris->map(fn ($b) => [
                    'jenis_layanan' => $jenis,
                    'layanan_id' => $b->{$kolomLayanan},
                    'penjamin_id' => $b->penjamin_id,
                    'harga' => $b->{$kolomHarga},
                    'berlaku_mulai' => $b->berlaku_mulai,
                    'created_at' => $b->created_at,
                    'updated_at' => $b->updated_at,
                ])->all());
            });
        }

        Schema::dropIfExists('harga_obat');
        Schema::dropIfExists('tarif_tindakan');
    }

    /**
     * Sengaja tidak membangun ulang kedua tabel lama: migrasi ini jalan satu arah,
     * dan memulihkannya setengah jadi lebih berbahaya daripada memulai ulang
     * dari migrate:fresh.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarif');
    }
};
