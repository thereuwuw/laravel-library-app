<?php

use App\Models\Book;
use App\Models\User;
use App\Models\Loan;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrow_book_with_negative_input(): void
    {
        // 1. Buat user & login
        $user = User::factory()->create();

        $this->actingAs($user);

        // 2. Pastikan loans kosong
        $response = $this->get('/loans');
        $response->assertStatus(200);
        $response->assertSee('You have no active loans');

        // 3. Buat buku
        $book = Book::create([
            'title' => 'Test Book',
            'author' => 'Tester',
            'year' => 2020,
            'copies_in_circulation' => 10,
        ]);

        // 4. Pastikan buku tampil
        $response = $this->get('/books');
        $response->assertSee('Test Book');
        $response->assertSee('10');
        $response->assertSee('Borrow book');

        // 5. Akses halaman borrow
        $response = $this->get('/loans/' . $book->id);
        $response->assertStatus(200);
        $response->assertSee('How many copies would you like to borrow?');

        // 6. Submit borrow dengan -1
        $response = $this->post('/loans/' . $book->id, [
            'number_borrowed' => -1,
            'return_date' => now()->addDays(3)->toDateString(),
        ]);

        // harus redirect ke loans
        $response->assertRedirect('/loans');
        $response = $this->get('/loans');
        $response->assertSee('Book borrowed successfully');


        // 7. Pastikan data loan masuk
        $this->assertDatabaseHas('loans', [
            'book_id' => $book->id,
            'number_borrowed' => -1,
        ]);
        $response->assertSee('-1');


        // 8. Cek available copies naik jadi 11 
        $this->assertEquals(11, $book->fresh()->availableCopies());

        // 9. Ambil loan
        $loan = Loan::where('book_id', $book->id)->first();

        // 10. Return buku
        $response = $this->followRedirects(
            $this->get('/loans/terminate/' . $loan->id)
        );

        // 11. Pesan berhasil return muncul 
        $response->assertSee('Book returned successfully');
        $response->assertDontSee('Test Book');

        // 12. Available copies kembali ke 10
        $response = $this->get('/books');
        $this->assertEquals(10, $book->fresh()->availableCopies());
    }

    public function test_borrow_book_exceeding_stock(): void
    {
        // 1. Buat user & login
        $user = User::factory()->create();

        $this->actingAs($user);

        // 2. Buat buku dengan 5 copies
        $book = Book::create([
            'title' => 'Test Book',
            'author' => 'Tester',
            'year' => 2020,
            'copies_in_circulation' => 5,
        ]);

        // 3. Submit borrow dengan 6 (lebih dari stock)
        $response = $this->post('/loans/' . $book->id, [
            'number_borrowed' => 6,
            'return_date' => now()->addDays(3)->toDateString(),
        ]);

        // 4. Harus redirect balik ke form
        $response->assertRedirect('/loans/' . $book->id);

        // 5. Ikuti redirect → baru bisa cek error
        $response = $this->followRedirects($response);

        // pesan error muncul
        $response->assertSee('You cannot borrow more than 5 book(s)');

        //tetap di halaman borrow
        $response->assertSee('How many copies would you like to borrow?');

        // pastikan data loan tidak masuk
        $this->assertDatabaseMissing('loans', [
            'book_id' => $book->id,
            'number_borrowed' => 6,
        ]);
    }

    public function test_borrow_when_stock_empty(): void
    {
        // 1. Buat user & login
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. Buat buku dengan 2 copies
        $book = Book::create([
            'title' => 'Test Book',
            'author' => 'Tester',
            'year' => 2020,
            'copies_in_circulation' => 2,
        ]);

        // 3. Snggap bukunya udah dipinjam semua (available copies = 0)
        Loan::create([
            'book_id' => $book->id,
            'user_id' => $user->id,
            'number_borrowed' => 2,
            'return_date' => now()->addDays(3),
            'is_returned' => false,
        ]);

        // 4. Pastikan available = 0
        $this->assertEquals(0, $book->fresh()->availableCopies());

        // 5. Akses halaman books
        $response = $this->get('/books');

        // 6. Harus muncul pesan tidak bisa borrow
        $response->assertSee('No copies available to borrow');

        // 7. Pastikan tidak ada link ke halaman borrow
        $response->assertDontSee('href="/loans/' . $book->id . '"');
    }
}
