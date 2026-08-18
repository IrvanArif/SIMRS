<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplikasi_menyala_dan_terhubung_ke_database_pengujian(): void
    {
        $this->assertSame('simrs_test', config('database.connections.mysql.database'));
        $this->get('/')->assertSuccessful();
    }
}
