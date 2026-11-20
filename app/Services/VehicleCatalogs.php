<?php

namespace App\Services;

use App\Models\VehicleOption;

/**
 * Catálogos del formulario de vehículo: categoría, marca, modelo, carrocería,
 * color, modalidad y combustible. Son selects con buscador (Select2 en modo
 * tags) donde se puede escribir un valor que no está: lo nuevo se guarda en
 * vehicle_options al guardar el vehículo y sale la próxima vez en todos los
 * formularios. Las listas BASE son el arranque; la tabla manda después.
 *
 * Las opciones se comparan sin distinguir mayúsculas ni espacios de más:
 * "negro  mate" y "Negro mate" son la misma opción.
 */
class VehicleCatalogs
{
    /** Columnas de vehicles que son catálogo (y el `type` de vehicle_options). */
    public const TYPES = ['class', 'brand', 'model', 'bodywork', 'color', 'type', 'fuel'];

    /** Largo máximo que se guarda de una opción (la columna más ancha de vehicles). */
    public const MAX_LENGTH = 100;

    /** @var array<string, list<string>> */
    public const BASE = [
        'class' => ['M1', 'M1-C3', 'M2', 'MICROBUS', 'M3.C3', 'M3.C1 OMNIBUS', 'M3-C3', 'M2-C3'],
        'brand' => [
            'Changan', 'Changhe', 'Chevrolet', 'County', 'DFSK', 'Faw', 'Hyundai', 'Jac',
            'Mercedes Benz', 'Mitsubishi', 'Nissan', 'Scama', 'Suzuki', 'Toyota', 'Volkswagen',
        ],
        'model' => [],
        'bodywork' => ['MULTIPROPOSITO', 'MICROBUS', 'Minibus'],
        'color' => [
            'Azul', 'Azul Acero', 'Azul Oscuro', 'Beige', 'Blanco', 'Blanco Perla', 'Dorado', 'Gris',
            'Gris Oscuro', 'Marron', 'Moca Arabe', 'Negro', 'Negro Atemporal', 'Oceanico', 'Plata',
            'Plata Diamond', 'Plata Metalizado', 'Plomo',
        ],
        'type' => ['Particular', 'Taxi Ejecutivo', 'Taxi Independiente', 'Transporte Personal', 'Transporte Turismo'],
        'fuel' => ['D2', 'GAS'],
    ];

    /**
     * Todos los catálogos de una vez: las BASE en su orden y después lo
     * guardado, por orden alfabético.
     *
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        $stored = VehicleOption::query()->orderBy('value')->get(['type', 'value'])->groupBy('type');

        $catalogs = [];
        foreach (self::TYPES as $type) {
            $catalogs[$type] = self::merge(self::BASE[$type], $stored->get($type)?->pluck('value')->all() ?? []);
        }

        return $catalogs;
    }

    /**
     * Catálogos para pintar el formulario: cada uno conserva el valor que ya
     * tiene el vehículo aunque no esté en la lista (histórico, consulta de placa).
     *
     * @param  array<string, mixed>  $current  valor actual por catálogo
     * @return array<string, list<string>>
     */
    public static function forValues(array $current): array
    {
        $catalogs = self::all();
        foreach ($catalogs as $type => $options) {
            $catalogs[$type] = self::withCurrent($options, $current[$type] ?? null);
        }

        return $catalogs;
    }

    /**
     * El valor tal como ya está en el catálogo si existe con otra caja
     * ("H-1 M/Bus" → "H-1 M/BUS"); si no existe, el valor limpio.
     */
    public static function canonical(string $type, ?string $value): string
    {
        self::assertType($type);

        return self::existing(self::all()[$type], $value) ?? self::clean($value);
    }

    /**
     * Guarda en el catálogo lo nuevo que traiga un vehículo para que salga la
     * próxima vez. Lo que ya existe (aunque en otra caja) o viene vacío no se toca.
     *
     * @param  array<string, mixed>  $vehicle  campos del vehículo (payload de guardado)
     */
    public static function rememberFrom(array $vehicle): void
    {
        $catalogs = self::all();

        foreach (self::TYPES as $type) {
            $value = mb_substr(self::clean($vehicle[$type] ?? null), 0, self::MAX_LENGTH);
            if ($value === '' || self::existing($catalogs[$type], $value) !== null) {
                continue;
            }

            VehicleOption::firstOrCreate(['type' => $type, 'value' => $value]);
        }
    }

    /**
     * Las opciones base en su orden y después las guardadas, sin repetir.
     *
     * @param  list<string>  $base
     * @param  list<string>  $stored
     * @return list<string>
     */
    public static function merge(array $base, array $stored): array
    {
        $seen = [];
        $options = [];
        foreach ([...$base, ...$stored] as $option) {
            $option = self::clean($option);
            $key = self::key($option);
            if ($option === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options[] = $option;
        }

        return $options;
    }

    /**
     * Las opciones con el valor actual garantizado, escrito tal cual está
     * guardado: el <select> compara exacto, así que "M3.C1 Omnibus" no se
     * mostraría con la opción "M3.C1 OMNIBUS". Si existe con otra caja ocupa
     * su lugar; si no existe va al inicio.
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    public static function withCurrent(array $options, mixed $current): array
    {
        $current = trim((string) $current);
        if ($current === '' || in_array($current, $options, true)) {
            return $options;
        }

        $match = self::existing($options, $current);
        if ($match === null) {
            return [$current, ...$options];
        }

        return array_map(fn (string $option) => $option === $match ? $current : $option, $options);
    }

    /**
     * La opción del catálogo que equivale al valor, o null si no está.
     *
     * @param  list<string>  $options
     */
    public static function existing(array $options, mixed $value): ?string
    {
        $key = self::key(self::clean($value));
        if ($key === '') {
            return null;
        }

        foreach ($options as $option) {
            if (self::key($option) === $key) {
                return $option;
            }
        }

        return null;
    }

    /** Sin espacios al inicio, al final ni repetidos en medio. */
    public static function clean(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private static function key(string $value): string
    {
        return mb_strtoupper($value);
    }

    private static function assertType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Catálogo de vehículo desconocido: {$type}");
        }
    }
}
