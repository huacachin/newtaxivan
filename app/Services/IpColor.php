<?php

namespace App\Services;

/**
 * Colores para distinguir IPs a primera vista en Monitoreo: a cada IP
 * distinta de la página le toca un tono diferente, repartidos con el ángulo
 * áureo para que queden lo más separados posible. Se asignan en el orden en
 * que llegan (quien llama pasa las IPs por primera aparición, la más antigua
 * primero): así, cuando aparece una IP nueva, las de antes conservan su color.
 */
final class IpColor
{
    private const GOLDEN_ANGLE = 137.508;

    private const START_HUE = 210.0;

    /**
     * @param  iterable<int, string|null>  $ips
     * @return array<string, array{bg: string, border: string, text: string}>
     */
    public static function palette(iterable $ips): array
    {
        $palette = [];
        $index = 0;
        foreach ($ips as $ip) {
            $ip = trim((string) $ip);
            if ($ip === '' || isset($palette[$ip])) {
                continue;
            }

            $hue = fmod(self::START_HUE + $index * self::GOLDEN_ANGLE, 360);
            // A partir de la segunda vuelta se oscurece un poco para seguir distinguiendo.
            $round = intdiv($index, 8);
            $palette[$ip] = [
                'bg' => sprintf('hsl(%d, 85%%, %d%%)', $hue, max(78, 92 - $round * 6)),
                'border' => sprintf('hsl(%d, 60%%, %d%%)', $hue, max(50, 70 - $round * 8)),
                'text' => sprintf('hsl(%d, 70%%, %d%%)', $hue, max(18, 28 - $round * 4)),
            ];
            $index++;
        }

        return $palette;
    }

    /**
     * Variables CSS para el chip de una IP (gris si la IP no tiene color).
     *
     * @param  array{bg: string, border: string, text: string}|null  $color
     */
    public static function style(?array $color): string
    {
        $color ??= ['bg' => '#f1f3f6', 'border' => '#d5d9df', 'text' => '#6b7280'];

        return "--ip-bg:{$color['bg']};--ip-bd:{$color['border']};--ip-tx:{$color['text']}";
    }
}
