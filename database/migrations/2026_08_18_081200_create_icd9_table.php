<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd9', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 255);
            $table->timestamps();
        });

        Schema::table('tindakan', function (Blueprint $table) {
            // Nullable: tidak semua tindakan punya padanan ICD-9-CM, dan yang
            // tidak punya tidak boleh menggagalkan klaim (aturan 88).
            $table->foreignId('icd9_id')->nullable()->after('kategori')
                ->constrained('icd9')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tindakan', function (Blueprint $table) {
            $table->dropForeign(['icd9_id']);
            $table->dropColumn('icd9_id');
        });

        Schema::dropIfExists('icd9');
    }
};
