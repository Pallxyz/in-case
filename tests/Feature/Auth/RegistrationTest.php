<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_halaman_daftar_tampil_tanpa_pilihan_kelas(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertDontSee('class_id')
            ->assertDontSee('Kelas');
    }

    public function test_pengguna_bisa_daftar_hanya_dengan_nama_email_dan_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password-kuat-123',
            'password_confirmation' => 'password-kuat-123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame('budi@example.com', User::first()->email);
    }

    public function test_data_tambahan_dari_form_diabaikan(): void
    {
        // Tidak ada lagi role/kelas: field ekstra tidak boleh sampai ke database.
        $this->post('/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password-kuat-123',
            'password_confirmation' => 'password-kuat-123',
            'role' => 'admin',
            'class_id' => 1,
        ])->assertRedirect();

        $user = User::first();
        $this->assertArrayNotHasKey('role', $user->getAttributes());
        $this->assertArrayNotHasKey('class_id', $user->getAttributes());
    }

    public function test_daftar_ditolak_kalau_data_tidak_valid(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->post('/register', [
            'name' => '',
            'email' => 'ada@example.com',
            'password' => 'password-kuat-123',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }
}
