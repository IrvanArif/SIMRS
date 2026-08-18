<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pasien;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembuatan_pasien_tercatat_di_audit_log(): void
    {
        $pasien = Pasien::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'create',
            'model_tipe' => Pasien::class,
            'model_id' => $pasien->id,
        ]);
    }

    public function test_perubahan_data_pasien_mencatat_nilai_sebelum_dan_sesudah(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Nama Lama']);
        $pasien->update(['nama' => 'Nama Baru']);

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertSame('Nama Lama', $catatan->perubahan['sebelum']['nama']);
        $this->assertSame('Nama Baru', $catatan->perubahan['sesudah']['nama']);
    }

    public function test_audit_mencatat_pengguna_yang_melakukan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pasien = Pasien::factory()->create();

        $this->assertSame($user->id, AuditLog::where('model_id', $pasien->id)->first()->user_id);
    }

    public function test_alasan_perubahan_ikut_tercatat(): void
    {
        $pasien = Pasien::factory()->create();

        KonteksAudit::dengan('Salah ketik nama saat pendaftaran', function () use ($pasien) {
            $pasien->update(['nama' => 'Nama Terkoreksi']);
        });

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertSame('Salah ketik nama saat pendaftaran', $catatan->alasan);
    }

    public function test_alasan_kembali_kosong_setelah_konteks_selesai(): void
    {
        $pasien = Pasien::factory()->create();

        KonteksAudit::dengan('Alasan pertama', fn () => $pasien->update(['nama' => 'Satu']));
        $pasien->update(['nama' => 'Dua']);

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertNull($catatan->alasan);
    }

    public function test_penghapusan_pasien_tercatat_sebagai_delete(): void
    {
        $pasien = Pasien::factory()->create();
        $pasien->delete();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'delete',
            'model_id' => $pasien->id,
        ]);
    }
}
