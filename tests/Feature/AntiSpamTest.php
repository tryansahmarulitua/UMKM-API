<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Project Plan §4.5 and FT13: Cloudflare Turnstile on chat (first open) and checkout, a honeypot on
 * checkout, and at most 10 orders per hour per IP.
 */
class AntiSpamTest extends TestCase
{
    use RefreshDatabase;

    private Produk $produk;

    private bool $turnstileMati = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.secret' => 'rahasia-turnstile']);
        $admin = Admin::create(['nama' => 'Owner', 'email' => 'owner@bunda.id', 'password' => 'rahasia123']);
        $kategori = Kategori::create(['namaKategori' => 'Camilan']);
        $this->produk = Produk::create([
            'idAdmin' => $admin->idAdmin, 'idKategori' => $kategori->idKategori,
            'namaProduk' => 'Abon', 'deskripsi' => '-', 'harga' => 45000, 'tampil' => true,
        ]);

        // Turnstile accepts only the token "lolos"; Gemini always answers.
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), 'challenges.cloudflare.com')) {
                if ($this->turnstileMati) {
                    return Http::response('down', 500);
                }

                return Http::response(['success' => $request['response'] === 'lolos' && $request['secret'] === 'rahasia-turnstile']);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"jawaban":"Halo, Kak!","rekomendasi":[]}']]]]]]);
        });
    }

    private function checkout(array $override = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/pesanan', array_merge([
            'namaPelanggan' => 'Rina',
            'noWhatsApp' => '081234567890',
            'pengiriman' => 'Ambil sendiri',
            'items' => [['idProduk' => $this->produk->idProduk, 'jumlah' => 1]],
            'turnstile' => 'lolos',
        ], $override));
    }

    private function geminiCalls(): int
    {
        return count(Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), 'generativelanguage')));
    }

    public function test_checkout_without_a_valid_turnstile_token_is_rejected(): void
    {
        $this->checkout(['turnstile' => null])->assertForbidden()
            ->assertJsonPath('message', 'Verifikasi keamanan gagal. Selesaikan verifikasi lalu coba lagi.');
        $this->checkout(['turnstile' => 'palsu'])->assertForbidden();
        $this->assertDatabaseCount('tb_pesanan', 0);

        $this->checkout()->assertCreated();
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'siteverify') && $request['remoteip'] === '127.0.0.1');
    }

    public function test_turnstile_is_only_checked_once_the_rest_of_the_order_is_valid(): void
    {
        // Tokens are single-use, so a typo in the form must not burn the customer's token.
        $this->checkout(['noWhatsApp' => '123'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_honeypot_filled_in_by_a_bot_is_rejected(): void
    {
        $this->checkout(['website' => 'http://spam.example'])->assertUnprocessable()->assertJsonPath('message', 'Pesanan ditolak.');
        $this->assertDatabaseCount('tb_pesanan', 0);
        Http::assertNothingSent();
    }

    public function test_the_11th_order_in_an_hour_from_one_ip_is_rejected(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->checkout()->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->checkout()->assertTooManyRequests()
            ->assertJsonPath('message', 'Sudah 10 pesanan dalam satu jam dari jaringan ini. Coba lagi nanti, atau pesan langsung lewat WhatsApp.');
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->checkout()->assertCreated();
        $this->assertDatabaseCount('tb_pesanan', 11);

        $this->travel(61)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->checkout()->assertCreated();
    }

    public function test_rejected_checkouts_do_not_use_up_the_hourly_order_limit(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->checkout(['turnstile' => 'palsu'])->assertForbidden();
        }

        $this->checkout()->assertCreated();
    }

    public function test_first_chat_message_needs_turnstile_and_returns_a_ticket_for_the_next_ones(): void
    {
        $this->postJson('/api/chat', ['message' => 'halo'])->assertForbidden()
            ->assertJsonPath('perluVerifikasi', true);
        $this->travel(4)->seconds(); // the 3-second gap between chat messages still applies
        $this->postJson('/api/chat', ['message' => 'halo', 'turnstile' => 'palsu'])->assertForbidden();
        $this->assertSame(0, $this->geminiCalls());

        $this->travel(4)->seconds();
        $tiket = $this->postJson('/api/chat', ['message' => 'halo', 'turnstile' => 'lolos'])->assertOk()->json('tiket');
        $this->assertIsString($tiket);

        $this->travel(4)->seconds();
        $this->postJson('/api/chat', ['message' => 'ada abon?', 'tiket' => $tiket])->assertOk()->assertJsonMissingPath('tiket');
        $this->assertSame(2, $this->geminiCalls());
    }

    public function test_chat_ticket_expires_after_an_hour_and_cannot_be_forged_or_moved_to_another_ip(): void
    {
        $tiket = $this->postJson('/api/chat', ['message' => 'halo', 'turnstile' => 'lolos'])->json('tiket');

        $this->travel(4)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->postJson('/api/chat', ['message' => 'halo', 'tiket' => $tiket])
            ->assertForbidden()->assertJsonPath('perluVerifikasi', true);
        $this->travel(4)->seconds();
        $this->postJson('/api/chat', ['message' => 'halo', 'tiket' => 'buatan-sendiri'])->assertForbidden();

        $this->travel(61)->minutes();
        $this->postJson('/api/chat', ['message' => 'halo', 'tiket' => $tiket])->assertForbidden()->assertJsonPath('perluVerifikasi', true);
    }

    public function test_production_without_a_turnstile_secret_rejects_instead_of_skipping_the_check(): void
    {
        config(['services.turnstile.secret' => null]);
        $this->app['env'] = 'production';

        $this->checkout()->assertForbidden();
        $this->postJson('/api/chat', ['message' => 'halo', 'turnstile' => 'lolos'])->assertForbidden();
        $this->assertDatabaseCount('tb_pesanan', 0);
    }

    public function test_turnstile_outage_fails_closed(): void
    {
        $this->turnstileMati = true;

        $this->checkout()->assertForbidden();
        $this->assertDatabaseCount('tb_pesanan', 0);
    }
}
