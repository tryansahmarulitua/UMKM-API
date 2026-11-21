<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Rasa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Project Plan §1.2: flavour labels (Manis, Asin, Gurih, Pedas, Renyah; the owner can add more), and
 * chatbot recommendations shown as product cards whose name, price and photo come from the database (§4.5, FT16).
 */
class RasaTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::create(['nama' => 'Owner', 'email' => 'owner@bunda.id', 'password' => 'rahasia123']);
        $this->kategori = Kategori::create(['namaKategori' => 'Camilan']);
    }

    private function produk(string $nama, float $harga, array $rasa = [], bool $tampil = true): Produk
    {
        $produk = Produk::create([
            'idAdmin' => $this->admin->idAdmin, 'idKategori' => $this->kategori->idKategori,
            'namaProduk' => $nama, 'deskripsi' => '-', 'harga' => $harga, 'tampil' => $tampil, 'foto' => 'produk/'.strtolower($nama).'.jpg',
        ]);
        $produk->rasa()->sync(Rasa::whereIn('namaRasa', $rasa)->pluck('idRasa'));

        return $produk;
    }

    private function idRasa(string $nama): int
    {
        return Rasa::where('namaRasa', $nama)->value('idRasa');
    }

    private function gemini(array|string $reply): void
    {
        $text = is_array($reply) ? json_encode($reply) : $reply;
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]])]);
    }

    public function test_the_five_default_flavours_exist_after_migrating(): void
    {
        $this->assertEqualsCanonicalizing(['Manis', 'Asin', 'Gurih', 'Pedas', 'Renyah'], Rasa::pluck('namaRasa')->all());
    }

    public function test_owner_can_add_rename_and_delete_flavours(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/rasa', ['namaRasa' => 'Gurih Pedas'])->assertCreated()->json('rasa.idRasa');
        $this->postJson('/api/rasa', ['namaRasa' => 'Manis'])->assertJsonPath('errors.namaRasa.0', 'Nama rasa sudah dipakai.');
        $this->putJson("/api/rasa/{$id}", ['namaRasa' => 'Balado'])->assertOk()->assertJsonPath('rasa.namaRasa', 'Balado');

        // Deleting a flavour only removes the label from products; the products stay.
        $produk = $this->produk('Keripik', 15000, ['Balado']);
        $this->deleteJson("/api/rasa/{$id}")->assertOk();
        $this->assertDatabaseHas('tb_produk', ['idProduk' => $produk->idProduk]);
        $this->assertSame([], $produk->fresh()->rasa->pluck('namaRasa')->all());
    }

    public function test_flavour_endpoints_require_login(): void
    {
        $this->getJson('/api/rasa')->assertUnauthorized();
        $this->postJson('/api/rasa', ['namaRasa' => 'Asam'])->assertUnauthorized();
    }

    public function test_product_flavours_are_saved_changed_and_cleared_from_the_dashboard_form(): void
    {
        Sanctum::actingAs($this->admin);
        $form = ['idKategori' => $this->kategori->idKategori, 'namaProduk' => 'Abon', 'deskripsi' => 'Gurih', 'harga' => 45000, 'tampil' => 'Y'];

        $id = $this->post('/api/produk', $form + ['rasa' => [$this->idRasa('Gurih'), $this->idRasa('Pedas')]], ['Accept' => 'application/json'])
            ->assertCreated()->json('produk.idProduk');
        $this->assertEqualsCanonicalizing(['Gurih', 'Pedas'], Produk::find($id)->rasa->pluck('namaRasa')->all());

        $this->post("/api/produk/{$id}?_method=PUT", $form + ['rasa' => [$this->idRasa('Manis')]], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['Manis'], Produk::find($id)->rasa->pluck('namaRasa')->all());

        // FormData cannot send an empty array, so the dashboard sends rasa="" when every box is unticked.
        $this->post("/api/produk/{$id}?_method=PUT", $form + ['rasa' => ''], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame([], Produk::find($id)->rasa->pluck('namaRasa')->all());

        $this->post('/api/produk', $form + ['rasa' => [99999]], ['Accept' => 'application/json'])->assertJsonValidationErrors('rasa.0');
    }

    public function test_public_catalogue_shows_flavour_names(): void
    {
        $this->produk('Abon', 45000, ['Gurih', 'Pedas']);

        $rasa = $this->getJson('/api/public/produk')->assertOk()->json('0.rasa');

        $this->assertEqualsCanonicalizing(['Gurih', 'Pedas'], array_column($rasa, 'namaRasa'));
        $this->assertArrayNotHasKey('pivot', $rasa[0]);
    }

    public function test_chatbot_recommendations_become_cards_with_name_price_and_photo_from_the_database(): void
    {
        $kue = $this->produk('Nastar', 60000, ['Manis']);
        $hidden = $this->produk('Bolu', 40000, ['Manis'], tampil: false);
        // The model may name hidden or made-up IDs, or repeat one; only real, visible products become cards.
        $this->gemini(['jawaban' => 'Untuk yang manis, kami punya Nastar, Kak.', 'rekomendasi' => [$kue->idProduk, $hidden->idProduk, 99999, $kue->idProduk]]);

        $response = $this->postJson('/api/chat', ['message' => 'ada yang manis?'])->assertOk();

        $response->assertJsonPath('answer', 'Untuk yang manis, kami punya Nastar, Kak.');
        $this->assertCount(1, $response->json('produk'));
        $response->assertJsonPath('produk.0.idProduk', $kue->idProduk)
            ->assertJsonPath('produk.0.namaProduk', 'Nastar')
            ->assertJsonPath('produk.0.harga', '60000.00')
            ->assertJsonPath('produk.0.foto', 'produk/nastar.jpg')
            ->assertJsonPath('produk.0.rasa', ['Manis']);
    }

    public function test_chatbot_asks_gemini_for_json_with_product_ids_and_sends_flavours_as_context(): void
    {
        $this->produk('Nastar', 60000, ['Manis']);
        $this->gemini(['jawaban' => 'Halo, Kak!', 'rekomendasi' => []]);

        $this->postJson('/api/chat', ['message' => 'halo'])->assertOk()->assertJsonPath('produk', []);

        Http::assertSent(function ($request) {
            $config = $request['generationConfig'];
            $prompt = $request['systemInstruction']['parts'][0]['text'];

            return $config['responseMimeType'] === 'application/json'
                && $config['responseSchema']['properties']['rekomendasi']['items']['type'] === 'INTEGER'
                && str_contains($prompt, '"rasa":["Manis"]')
                && str_contains($prompt, '"id":');
        });
    }

    public function test_at_most_four_cards_are_shown(): void
    {
        $ids = collect(range(1, 6))->map(fn ($i) => $this->produk("Kue{$i}", 10000, ['Manis'])->idProduk)->all();
        $this->gemini(['jawaban' => 'Ini pilihan yang manis.', 'rekomendasi' => $ids]);

        $this->assertCount(4, $this->postJson('/api/chat', ['message' => 'yang manis apa saja?'])->json('produk'));
    }

    public function test_plain_text_reply_still_works_without_cards(): void
    {
        $this->gemini('Halo, Kak! Ada yang bisa kami bantu?');

        $this->postJson('/api/chat', ['message' => 'halo'])
            ->assertOk()
            ->assertJsonPath('answer', 'Halo, Kak! Ada yang bisa kami bantu?')
            ->assertJsonPath('produk', []);
    }
}
