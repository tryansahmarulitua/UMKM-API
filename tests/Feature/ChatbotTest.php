<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private function gemini(string $answer): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $answer]]]]]])]);
    }

    private function prompt(): string
    {
        $prompt = null;
        Http::assertSent(function ($request) use (&$prompt) {
            $prompt = $request['systemInstruction']['parts'][0]['text'];

            return true;
        });

        return $prompt;
    }

    public function test_prompt_treats_the_visitor_as_a_buyer_and_points_orders_to_whatsapp(): void
    {
        $this->gemini('Halo!');

        $this->postJson('/api/chat', ['message' => 'Halo'])->assertOk();

        $prompt = $this->prompt();
        $this->assertStringContainsString('calon pembeli', $prompt);
        $this->assertStringContainsString('"kami"', $prompt);
        $this->assertStringContainsString('tanpa format markdown', $prompt);
        $this->assertStringContainsString('Pesan lewat WhatsApp', $prompt);
    }

    public function test_prices_reach_the_model_as_rupiah_instead_of_raw_decimals(): void
    {
        $this->gemini('Halo!');
        $admin = Admin::create(['nama' => 'Owner', 'email' => 'owner@bunda.id', 'password' => 'rahasia123']);
        $kategori = Kategori::create(['namaKategori' => 'Minuman']);
        foreach ([['Kopi Hitam', 5000], ['Abon Sapi', 125000.5]] as [$nama, $harga]) {
            Produk::create(['idAdmin' => $admin->idAdmin, 'idKategori' => $kategori->idKategori, 'namaProduk' => $nama, 'deskripsi' => '-', 'harga' => $harga, 'tampil' => true]);
        }

        $this->postJson('/api/chat', ['message' => 'Harganya berapa?'])->assertOk();

        $prompt = $this->prompt();
        $this->assertStringContainsString('"harga":"Rp5.000"', $prompt);
        $this->assertStringContainsString('"harga":"Rp125.000,50"', $prompt);
        $this->assertStringNotContainsString('5000.00', $prompt);
    }

    public function test_markdown_in_the_answer_is_turned_into_plain_text(): void
    {
        // The chat bubble shows plain text, so **bold** and "* " bullets would appear as raw asterisks.
        $this->gemini("## Menu kami\n* **Kopi Hitam** - Rp5.000\n*   __Abon__ - Rp45.000\n\n\n\nSilakan pesan, *ya*.");

        $this->postJson('/api/chat', ['message' => 'Ada menu apa?'])
            ->assertOk()
            ->assertJsonPath('answer', "Menu kami\n- Kopi Hitam - Rp5.000\n- Abon - Rp45.000\n\nSilakan pesan, ya.");
    }
}
