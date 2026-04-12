<?php

use App\Models\Book;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnauthenticatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_unauthenticated(): void
    {
        //untuk visit homepage
        $response = $this->get('/');

        //harus bisa akses homepage
        $response->assertStatus(200);

        //borrow tidak boleh muncul untuk user yang tidak login
        $response->assertDontSeeText('borrow');

        $response->assertSee('login');
        $response->assertSee('register');

        Book::create([
            'title' => 'Naruto',
            'author' => 'Kishimoto',
            'year' => "1998",
            'copies_in_circulation' => 5,
        ]);

        //refresh homepage
        $response = $this->get('/');

        //buku yang ditampilkan harus sesuai saat create
        $response->assertStatus(200);
        $response->assertSeeText('Naruto');
        $response->assertSeeText('1998');
        $response->assertSeeText('Kishimoto');
    }

    public function test_guest_cannot_access_loans(): void
    {
        // akses route yang butuh login
        $response = $this->get('/loans');

        // harus redirect ke login
        $response->assertRedirect('/login');
    }
    public function test_guest_cannot_access_profile(): void
    {
        // akses route yang butuh login
        $response = $this->get('/profile');

        // harus redirect ke login
        $response->assertRedirect('/login');
    }

    public function test_guest_can_register(): void
    {
        // kirim request register
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // setelah register biasanya redirect ke dashboard/home
        $response->assertStatus(302);

        // pastikan user masuk ke database
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_guest_can_login(): void
    {
        // buat user dulu
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // login
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // harus redirect setelah login
        $response->assertRedirect('/dashboard');

        // pastikan user sudah login
        $this->assertAuthenticatedAs($user);
    }
}