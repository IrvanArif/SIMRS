<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_perkembangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rawat_inap_id')->constrained('rawat_inap')->cascadeOnDelete();
            $table->text('subjective');
            $table->text('objective');
            $table->text('assessment');
            $table->text('plan');
            $table->foreignId('ditulis_oleh')->nullable()->constrained('users')->nullOnDelete();
            // Peran penulis disalin, bukan dibaca ulang dari perannya sekarang:
            // catatan yang ditulis perawat tetap catatan perawat meski orangnya
            // kemudian berganti peran.
            $table->string('peran_penulis', 20);
            $table->timestamp('waktu');
            $table->timestamps();
            $table->index(['rawat_inap_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_perkembangan');
    }
};
