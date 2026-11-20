<?php

namespace Tests\Unit;

use App\Services\Factiliza;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Consulta de placa en Factiliza para el alta de vehículo: trae marca, modelo
 * y color, no gasta consultas en lo que no es una placa y explica cada falla
 * (sin salir a la red: todo va contra Http::fake).
 */
class FactilizaTest extends TestCase
{
    private const ENDPOINT = 'api.factiliza.com/v1/placa/info/*';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.factiliza.token' => 'token-de-prueba', 'services.factiliza.base' => 'https://api.factiliza.com/v1']);
    }

    /** @param  array<string, mixed>  $data */
    private function fakePlate(array $data): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true, 'message' => 'Exito', 'data' => $data])]);
    }

    public function test_returns_brand_model_and_color_of_the_plate(): void
    {
        $this->fakePlate(['placa' => 'ABC123', 'marca' => 'HYUNDAI', 'modelo' => 'H-1 M/BUS', 'color' => 'PLATA METALIZADO', 'motor' => 'D4BH123']);

        $result = (new Factiliza)->plate(' abc123 ');

        $this->assertSame(
            ['ok' => true, 'data' => ['brand' => 'Hyundai', 'model' => 'H-1 M/Bus', 'color' => 'Plata Metalizado'], 'error' => null],
            $result,
        );
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.factiliza.com/v1/placa/info/ABC123'
            && $request->hasHeader('Authorization', 'Bearer token-de-prueba'));
    }

    public function test_a_field_the_api_does_not_send_comes_back_empty(): void
    {
        $this->fakePlate(['placa' => 'ABC123', 'marca' => 'JAC']);

        $result = (new Factiliza)->plate('ABC123');

        $this->assertSame(['brand' => 'Jac', 'model' => '', 'color' => ''], $result['data']);
    }

    public function test_rejects_a_dni_without_spending_a_query(): void
    {
        $this->fakePlate(['marca' => 'JAC']);

        $result = (new Factiliza)->plate('45678912');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('solo tiene números', $result['error']);
        Http::assertNothingSent();
    }

    public function test_rejects_text_that_is_not_a_plate_without_spending_a_query(): void
    {
        $this->fakePlate(['marca' => 'JAC']);

        $result = (new Factiliza)->plate('PLACA NUEVA 2026');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no parece una placa', $result['error']);
        Http::assertNothingSent();
    }

    public function test_asks_for_the_token_when_it_is_not_configured(): void
    {
        config(['services.factiliza.token' => null]);
        $this->fakePlate(['marca' => 'JAC']);

        $result = (new Factiliza)->plate('ABC123');

        $this->assertSame('Falta FACTILIZA_TOKEN en el .env.', $result['error']);
        Http::assertNothingSent();
    }

    public function test_returns_the_api_message_when_the_plate_has_no_data(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => false, 'message' => 'No se encontraron resultados.'], 404)]);

        $result = (new Factiliza)->plate('ZZZ999');

        $this->assertSame(['ok' => false, 'data' => null, 'error' => 'No se encontraron resultados.'], $result);
    }

    public function test_falls_back_to_a_generic_message_when_the_api_gives_none(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => true, 'data' => null])]);

        $result = (new Factiliza)->plate('ZZZ999');

        $this->assertSame('No se encontraron datos para esa placa.', $result['error']);
    }

    public function test_reports_an_invalid_token_on_401(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => false, 'message' => 'Unauthorized'], 401)]);

        $result = (new Factiliza)->plate('ABC123');

        $this->assertSame('El token de consulta no es válido o expiró.', $result['error']);
    }

    public function test_reports_the_service_is_down_after_retrying_a_failed_connection(): void
    {
        Sleep::fake();
        Http::fake([self::ENDPOINT => Http::failedConnection()]);

        $result = (new Factiliza)->plate('ABC123');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no respondió', $result['error']);
        Http::assertSentCount(2);
    }

    public function test_the_same_plate_is_only_queried_once(): void
    {
        $this->fakePlate(['placa' => 'ABC123', 'marca' => 'JAC', 'modelo' => 'REFINE', 'color' => 'NEGRO']);
        $api = new Factiliza;

        $first = $api->plate('ABC123');
        $second = $api->plate('abc123');

        $this->assertSame($first, $second);
        $this->assertSame('Refine', $second['data']['model']);
        Http::assertSentCount(1);
    }

    public function test_a_failed_query_is_not_cached(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['success' => false, 'message' => 'Servicio no disponible.'], 503)
            ->push(['success' => true, 'data' => ['marca' => 'JAC']])]);
        $api = new Factiliza;

        $failed = $api->plate('ABC123');
        $retried = $api->plate('ABC123');

        $this->assertFalse($failed['ok']);
        $this->assertSame('Jac', $retried['data']['brand']);
    }
}
