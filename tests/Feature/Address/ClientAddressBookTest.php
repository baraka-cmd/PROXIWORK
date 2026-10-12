<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Enums\ServiceRequestStatus;
use App\Models\Address;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAddressBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_open_the_client_address_book(): void
    {
        $this->get('/client/addresses')->assertRedirect('/login');
    }

    public function test_client_can_create_and_update_an_address_with_access_instructions(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->post('/client/addresses', $this->validAddress([
                'label' => 'Maison',
                'is_default' => '0',
                'access_instructions' => 'Portail bleu, appeler à l’arrivée.',
            ]))
            ->assertRedirect('/client/addresses')
            ->assertSessionHas('success');

        $address = Address::query()->where('user_id', $client->id)->sole();

        $this->assertTrue($address->is_default, 'La première adresse doit devenir l’adresse par défaut.');
        $this->assertSame('Portail bleu, appeler à l’arrivée.', $address->access_instructions);

        $this->actingAs($client)
            ->patch('/client/addresses/'.$address->id, $this->validAddress([
                'label' => 'Maison familiale',
                'city' => 'Bukavu',
                'is_default' => '1',
                'access_instructions' => 'Entrée par la cour intérieure.',
            ]))
            ->assertRedirect('/client/addresses')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'user_id' => $client->id,
            'label' => 'Maison familiale',
            'city' => 'Bukavu',
            'access_instructions' => 'Entrée par la cour intérieure.',
            'is_default' => true,
        ]);
    }

    public function test_client_can_change_default_without_leaving_two_default_addresses(): void
    {
        $client = $this->client();
        $first = Address::factory()->for($client)->create(['is_default' => true]);
        $second = Address::factory()->for($client)->create(['is_default' => false]);

        $this->actingAs($client)
            ->post('/client/addresses/'.$second->id.'/default')
            ->assertRedirect('/client/addresses')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', ['id' => $first->id, 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['id' => $second->id, 'is_default' => true]);
        $this->assertSame(1, $client->addresses()->where('is_default', true)->count());
    }

    public function test_client_cannot_edit_or_delete_another_clients_address(): void
    {
        $client = $this->client();
        $otherClient = $this->client();
        $address = Address::factory()->for($otherClient)->create();

        $this->actingAs($client)
            ->get('/client/addresses/'.$address->id.'/edit')
            ->assertForbidden();

        $this->actingAs($client)
            ->patch('/client/addresses/'.$address->id, $this->validAddress())
            ->assertForbidden();

        $this->actingAs($client)
            ->delete('/client/addresses/'.$address->id)
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'user_id' => $otherClient->id,
        ]);
    }

    public function test_address_used_by_an_operational_request_cannot_be_deleted(): void
    {
        $client = $this->client();
        $address = Address::factory()->for($client)->create(['is_default' => true]);

        ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'status' => ServiceRequestStatus::REQUESTED,
        ]);

        $this->actingAs($client)
            ->delete('/client/addresses/'.$address->id)
            ->assertRedirect()
            ->assertSessionHasErrors('address');

        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'user_id' => $client->id]);
    }

    public function test_client_cannot_supply_an_address_owner_id_or_invalid_coordinate_pair(): void
    {
        $client = $this->client();
        $otherClient = $this->client();

        $this->actingAs($client)
            ->from('/client/addresses/create')
            ->post('/client/addresses', $this->validAddress([
                'user_id' => $otherClient->id,
                'latitude' => '-1.68',
                'longitude' => '',
            ]))
            ->assertRedirect('/client/addresses/create')
            ->assertSessionHasErrors('longitude');

        $this->assertDatabaseMissing('addresses', ['user_id' => $otherClient->id, 'label' => 'Maison']);
        $this->assertSame(0, $client->addresses()->count());
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function validAddress(array $overrides = []): array
    {
        return [
            'label' => 'Maison',
            'recipient_name' => 'Contact sur place',
            'contact_phone' => '+243900000000',
            'country_code' => 'CD',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'commune' => 'Goma',
            'neighborhood' => 'Himbi',
            'address_line_1' => 'Avenue du Lac, parcelle 12',
            'address_line_2' => '',
            'landmark' => 'Près du marché',
            'access_instructions' => '',
            'postal_code' => '',
            'latitude' => '',
            'longitude' => '',
            'is_default' => '0',
            ...$overrides,
        ];
    }
}
