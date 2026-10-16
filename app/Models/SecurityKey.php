<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * Clave de seguridad de segundo nivel. DIRECTOR_ROLE se pide cada vez que un
 * cambio de rol entra o sale de Director (crear y editar usuario). Solo vive el
 * hash; se crea o cambia con el comando users:director-key.
 */
class SecurityKey extends Model
{
    use Auditable;

    /** Clave que protege dar o quitar el rol Director. */
    public const DIRECTOR_ROLE = 'director-role';

    /** Largo mínimo al establecerla. */
    public const MIN_LENGTH = 10;

    protected $auditModule = 'Clave de seguridad';

    protected $auditMask = ['hash'];

    protected $fillable = ['name', 'hash', 'updated_by'];

    protected $hidden = ['hash'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function isDefined(string $name): bool
    {
        return static::where('name', $name)->exists();
    }

    /**
     * true solo si la clave existe y el intento coincide.
     */
    public static function verify(string $name, string $attempt): bool
    {
        $hash = static::where('name', $name)->value('hash');

        return $attempt !== '' && is_string($hash) && Hash::check($attempt, $hash);
    }

    /**
     * Crea o reemplaza la clave guardando solo su hash.
     */
    public static function store(string $name, string $key, ?int $userId = null): self
    {
        return static::updateOrCreate(['name' => $name], ['hash' => Hash::make($key), 'updated_by' => $userId]);
    }

    /**
     * Clave aleatoria legible para dictar o anotar: grupos de cuatro sin 0/O ni 1/l/I,
     * p. ej. "Kq7m-Xw3t-Pz8v-Rh4n".
     */
    public static function generate(int $groups = 4): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $parts = [];
        for ($g = 0; $g < $groups; $g++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) {
                $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $parts[] = $part;
        }

        return implode('-', $parts);
    }
}
