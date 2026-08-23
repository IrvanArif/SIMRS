<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_detail', function (Blueprint $table) {
            $table->string('sumber_tipe', 100)->nullable()->after('tagihan_id');
            $table->unsignedBigInteger('sumber_id')->nullable()->after('sumber_tipe');
            $table->index(['sumber_tipe', 'sumber_id']);
        });

        // Pindahkan penanda sumber dari dua kolom nullable ke sepasang kolom
        // polimorfik. Kolom nullable per jenis sumber tidak bisa tumbuh terus:
        // laboratorium akan menjadi yang ketiga, radiologi yang keempat.
        DB::table('tagihan_detail')->whereNotNull('tindakan_kunjungan_id')->update([
            'sumber_tipe' => \App\Models\TindakanKunjungan::class,
            'sumber_id' => DB::raw('tindakan_kunjungan_id'),
        ]);

        DB::table('tagihan_detail')->whereNotNull('resep_detail_id')->update([
            'sumber_tipe' => \App\Models\ResepDetail::class,
            'sumber_id' => DB::raw('resep_detail_id'),
        ]);

        Schema::table('tagihan_detail', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tindakan_kunjungan_id');
            $table->dropConstrainedForeignId('resep_detail_id');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_detail', function (Blueprint $table) {
            $table->dropIndex(['sumber_tipe', 'sumber_id']);
            $table->dropColumn(['sumber_tipe', 'sumber_id']);
        });
    }
};
