<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_detail', function (Blueprint $table) {
            $table->foreignId('resep_detail_id')->nullable()->after('tindakan_kunjungan_id')
                ->constrained('resep_detail')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_detail', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resep_detail_id');
        });
    }
};
