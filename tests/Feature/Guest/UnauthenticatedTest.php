<?php

use App\Models\Book;
use Tests\TestCase;

class UnauthenticatedTest extends TestCase
{
    public function test_homepage_unauthenticated(): void
    {
        //untuk visit homepage
        $response = $this->get('/');

        //harus bisa akses homepage
        $response->assertStatus(200);

        //borrow tidak boleh muncul untuk user yang tidak login
        $response->assertDontSeeText('borrow');

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
}
?>