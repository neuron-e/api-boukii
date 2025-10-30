<?php

namespace Tests\APIs;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\ApiTestTrait;
use App\Models\DiscountCode;

class DiscountCodeApiTest extends TestCase
{
    use ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    /**
     * @test
     */
    public function test_create_discount_code()
    {
        $discountCode = DiscountCode::factory()->make()->toArray();

        $this->response = $this->json(
            'POST',
            '/api/discount-codes', $discountCode
        );

        $this->assertApiResponse($discountCode);
    }

    /**
     * @test
     */
    public function test_read_discount_code()
    {
        $discountCode = DiscountCode::factory()->create();

        $this->response = $this->json(
            'GET',
            '/api/discount-codes/'.$discountCode->id
        );

        $this->assertApiResponse($discountCode->toArray());
    }

    /**
     * @test
     */
    public function test_update_discount_code()
    {
        $discountCode = DiscountCode::factory()->create();
        $editedDiscountCode = DiscountCode::factory()->make()->toArray();

        $this->response = $this->json(
            'PUT',
            '/api/discount-codes/'.$discountCode->id,
            $editedDiscountCode
        );

        $this->assertApiResponse($editedDiscountCode);
    }

    /**
     * @test
     */
    public function test_delete_discount_code()
    {
        $discountCode = DiscountCode::factory()->create();

        $this->response = $this->json(
            'DELETE',
             '/api/discount-codes/'.$discountCode->id
         );

        $this->assertApiSuccess();
        $this->response = $this->json(
            'GET',
            '/api/discount-codes/'.$discountCode->id
        );

        $this->response->assertStatus(404);
    }

    /**
     * @test
     * Test: El campo 'name' es opcional al crear un codigo de descuento
     */
    public function test_name_is_optional_when_creating_discount_code()
    {
        $discountCode = DiscountCode::factory()->make()->toArray();
        unset($discountCode['name']);

        $this->response = $this->json(
            'POST',
            '/api/discount-codes',
            $discountCode
        );

        $this->assertApiSuccess();
        $this->assertDatabaseHas('discounts_codes', [
            'code' => $discountCode['code'],
            'name' => null
        ]);
    }

    /**
     * @test
     * Test: El campo 'name' puede actualizarse
     */
    public function test_can_update_discount_code_name()
    {
        $discountCode = DiscountCode::factory()->create(['name' => 'Original Name']);

        $updateData = array_merge($discountCode->toArray(), [
            'name' => 'Updated Name'
        ]);

        $this->response = $this->json(
            'PUT',
            '/api/discount-codes/' . $discountCode->id,
            $updateData
        );

        $this->assertApiSuccess();
        $this->assertDatabaseHas('discounts_codes', [
            'id' => $discountCode->id,
            'name' => 'Updated Name'
        ]);
    }

    /**
     * @test
     * Test: Se puede buscar códigos de descuento por nombre
     */
    public function test_can_search_discount_codes_by_name()
    {
        DiscountCode::factory()->create(['name' => 'Black Friday 2025', 'code' => 'BF2025']);
        DiscountCode::factory()->create(['name' => 'Summer Sale', 'code' => 'SUMMER']);
        DiscountCode::factory()->create(['name' => 'Winter Discount', 'code' => 'WINTER']);

        $this->response = $this->json(
            'GET',
            '/api/discount-codes?search=Black'
        );

        $this->response->assertStatus(200);
        $this->response->assertJsonFragment(['name' => 'Black Friday 2025']);
        $this->response->assertJsonMissing(['name' => 'Summer Sale']);
    }

    /**
     * @test
     * Test: El nombre no puede exceder 255 caracteres
     */
    public function test_name_cannot_exceed_max_length()
    {
        $longName = str_repeat('a', 256);
        $discountCode = DiscountCode::factory()->make(['name' => $longName])->toArray();

        $this->response = $this->json(
            'POST',
            '/api/discount-codes',
            $discountCode
        );

        $this->response->assertStatus(422);
        $this->response->assertJsonValidationErrors(['name']);
    }

    /**
     * @test
     * Test: El API retorna el campo 'name' en las respuestas
     */
    public function test_api_returns_name_field()
    {
        $discountCode = DiscountCode::factory()->create(['name' => 'Test Discount Name']);

        $this->response = $this->json(
            'GET',
            '/api/discount-codes/' . $discountCode->id
        );

        $this->response->assertStatus(200);
        $this->response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'code',
                'name',
                'description',
                'discount_type',
                'discount_value'
            ]
        ]);
        $this->response->assertJsonFragment(['name' => 'Test Discount Name']);
    }

    /**
     * @test
     * Test: Se puede crear un código con nombre descriptivo
     */
    public function test_can_create_discount_code_with_descriptive_name()
    {
        $data = [
            'code' => 'TESTCODE',
            'name' => 'Descuento de Bienvenida 2025',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'school_id' => 1,
            'applicable_to' => 'all',
            'active' => true
        ];

        $this->response = $this->json(
            'POST',
            '/api/discount-codes',
            $data
        );

        $this->assertApiSuccess();
        $this->assertDatabaseHas('discounts_codes', [
            'code' => 'TESTCODE',
            'name' => 'Descuento de Bienvenida 2025'
        ]);
    }
}


