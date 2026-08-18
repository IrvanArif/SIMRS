<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resep', function (Blueprint $table) {
            $table->timestamp('disiapkan_pada')->nullable()->after('status');
            $table->foreignId('disiapkan_oleh')->nullable()->after('disiapkan_pada')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('diserahkan_pada')->nullable()->after('disiapkan_oleh');
            $table->foreignId('diserahkan_oleh')->nullable()->after('diserahkan_pada')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('resep_detail', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_diserahkan')->default(0)->after('jumlah');
            $table->unsignedBigInteger('harga_satuan')->default(0)->after('jumlah_diserahkan');
        });
    }

    public function down(): void
    {
        Schema::table('resep_detail', function (Blueprint $table) {
            $table->dropColumn(['jumlah_diserahkan', 'harga_satuan']);
        });

        Schema::table('resep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disiapkan_oleh');
            $table->dropConstrainedForeignId('diserahkan_oleh');
            $table->dropColumn(['disiapkan_pada', 'diserahkan_pada']);
        });
    }
};
