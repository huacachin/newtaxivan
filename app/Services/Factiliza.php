<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta de placas contra api.factiliza.com (el mismo servicio y token que
 * usa prestamoh). Token Bearer desde config('services.factiliza.token').
 *
 * Contrato de respuesta: ['ok' => bool, 'data' => array|null, 'error' => string|null].
 */
class Factiliza
{
    /** Respuestas cacheadas 24 h: la misma placa consultada dos veces no gasta crédito. */
    private const CACHE_TTL = 86400;

    private const TIMEOUT = 10;

    /**
     * Marca, modelo y color de una placa, con los nombres de columna de vehicles.
     *
     * @return array{ok: bool, data: array{brand: string, model: string, color: string}|null, error: string|null}
     */
    public function plate(string $plate): array
    {
        $plate = strtoupper(trim($plate));
        if ($plate === '') {
            return $this->error('Escribe la placa que quieres consultar.');
        }

        // Una placa tiene 5 a 7 letras y números con al menos una letra; lo que
        // no lo parece (p. ej. un DNI) se avisa sin gastar la consulta.
        $compact = preg_replace('/[\s-]+/', '', $plate);
        if (! preg_match('/^(?=.*[A-Z])[A-Z0-9]{5,7}$/', $compact)) {
            return $this->error(ctype_digit($compact)
                ? "«{$plate}» no es una placa: solo tiene números (¿es un DNI?). Escribe la placa del vehículo, por ejemplo ABC123."
                : "«{$plate}» no parece una placa. Escribe la placa del vehículo, por ejemplo ABC123.");
        }

        $result = $this->query('placa', $plate);
        if (! $result['ok']) {
            return $result;
        }

        return [
            'ok' => true,
            'data' => [
                'brand' => $this->clean($result['data']['marca'] ?? ''),
                'model' => $this->clean($result['data']['modelo'] ?? ''),
                'color' => $this->clean($result['data']['color'] ?? ''),
            ],
            'error' => null,
        ];
    }

    /**
     * El `data` crudo de la API para un recurso (placa) y su valor.
     *
     * @return array{ok: bool, data: array<string, mixed>|null, error: string|null}
     */
    private function query(string $resource, string $value): array
    {
        $token = (string) config('services.factiliza.token');
        if ($token === '') {
            return $this->error('Falta FACTILIZA_TOKEN en el .env.');
        }

        $cacheKey = "factiliza:{$resource}:{$value}";
        if ($hit = Cache::get($cacheKey)) {
            return ['ok' => true, 'data' => $hit, 'error' => null];
        }

        try {
            $base = rtrim((string) config('services.factiliza.base'), '/');
            // Factiliza se cuelga a ratos (sin respuesta en 10 s): un reintento
            // si no responde; las fallas sueltas pasan al segundo intento.
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(self::TIMEOUT)
                ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->get("{$base}/{$resource}/info/".rawurlencode($value));
        } catch (\Throwable $e) {
            Log::warning('Factiliza: excepción de red', ['recurso' => $resource, 'error' => $e->getMessage()]);

            return $this->error('Factiliza (el servicio de consulta) no respondió: está lento o caído en este momento. Vuelve a intentar en unos minutos.');
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true || empty($json['data'])) {
            $message = is_array($json) ? (string) ($json['message'] ?? '') : '';
            if ($response->status() === 401) {
                $message = 'El token de consulta no es válido o expiró.';
            }
            Log::warning('Factiliza: consulta sin datos', [
                'recurso' => $resource, 'http' => $response->status(), 'mensaje' => $message,
            ]);

            return $this->error($message !== '' ? $message : 'No se encontraron datos para esa placa.');
        }

        Cache::put($cacheKey, $json['data'], self::CACHE_TTL);

        return ['ok' => true, 'data' => $json['data'], 'error' => null];
    }

    /** @return array{ok: false, data: null, error: string} */
    private function error(string $message): array
    {
        return ['ok' => false, 'data' => null, 'error' => $message];
    }

    /** Texto de la API → repara encoding y pasa a Title Case ("HYUNDAI" → "Hyundai"). */
    private function clean(mixed $value): string
    {
        $value = trim($this->repair((string) $value));

        return $value === '' ? '' : mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * La API entrega algunos textos con doble codificación: "PUÑA" llega como
     * "PUÃ‘A" (bytes UTF-8 leídos como latin1). Se revierte solo cuando se
     * detecta el patrón, para no dañar texto ya correcto.
     */
    private function repair(string $value): string
    {
        if ($value === '' || ! preg_match('/Ã[\x80-\xBF\x{0080}-\x{009F}]/u', $value)) {
            return $value;
        }

        $repaired = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');

        return ($repaired !== false && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8'))
            ? $repaired
            : $value;
    }
}
