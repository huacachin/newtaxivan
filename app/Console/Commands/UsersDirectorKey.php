<?php

namespace App\Console\Commands;

use App\Models\SecurityKey;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crea, cambia o comprueba la clave de seguridad que protege dar o quitar el rol
 * Director. Solo se guarda el hash.
 *
 *   users:director-key --generate     genera una clave aleatoria y la MUESTRA una sola vez
 *   users:director-key                la pide por teclado (sin eco) dos veces
 *   users:director-key --stdin        la lee de la primera línea de la entrada estándar
 *   users:director-key --verify       comprueba una clave (teclado o --stdin) sin cambiarla
 *   --by=Guilmer                      usuario que queda como "actualizado por"
 */
class UsersDirectorKey extends Command
{
    protected $signature = 'users:director-key
        {--generate : Genera una clave aleatoria y la muestra una sola vez}
        {--stdin : Lee la clave de la primera línea de la entrada estándar}
        {--verify : Comprueba una clave en vez de cambiarla}
        {--by= : Usuario (username) que la establece}';

    protected $description = 'Crea, cambia o comprueba la clave de seguridad para dar o quitar el rol Director';

    public function handle(): int
    {
        if ($this->option('verify')) {
            return $this->verifyKey();
        }

        $userId = $this->resolveUserId();
        if ($userId === false) {
            return self::FAILURE;
        }

        $generated = (bool) $this->option('generate');
        $key = $generated ? SecurityKey::generate() : $this->readKey(confirm: true);
        if ($key === null) {
            return self::FAILURE;
        }
        if (mb_strlen($key) < SecurityKey::MIN_LENGTH) {
            $this->error('La clave debe tener al menos '.SecurityKey::MIN_LENGTH.' caracteres.');

            return self::FAILURE;
        }

        $existed = SecurityKey::isDefined(SecurityKey::DIRECTOR_ROLE);
        SecurityKey::store(SecurityKey::DIRECTOR_ROLE, $key, $userId);

        $this->info($existed ? '✓ Clave de seguridad para el rol Director REEMPLAZADA.' : '✓ Clave de seguridad para el rol Director creada.');
        if ($generated) {
            $this->newLine();
            $this->line('   Clave (se muestra UNA sola vez):');
            $this->newLine();
            $this->line("      {$key}");
            $this->newLine();
            $this->warn('   No queda guardada en claro en ningún lado: si se pierde, hay que generar otra.');
        }

        return self::SUCCESS;
    }

    private function verifyKey(): int
    {
        if (! SecurityKey::isDefined(SecurityKey::DIRECTOR_ROLE)) {
            $this->error('No hay clave de seguridad configurada.');

            return self::FAILURE;
        }
        $key = $this->readKey(confirm: false);
        if ($key === null) {
            return self::FAILURE;
        }
        if (SecurityKey::verify(SecurityKey::DIRECTOR_ROLE, $key)) {
            $this->info('✓ La clave es correcta.');

            return self::SUCCESS;
        }
        $this->error('✗ Clave incorrecta.');

        return self::FAILURE;
    }

    /**
     * La clave desde --stdin (primera línea) o por teclado sin eco.
     */
    private function readKey(bool $confirm): ?string
    {
        if ($this->option('stdin')) {
            $line = fgets(STDIN);
            $key = is_string($line) ? trim($line) : '';
            if ($key === '') {
                $this->error('No llegó ninguna clave por la entrada estándar.');

                return null;
            }

            return $key;
        }

        $key = trim((string) $this->secret('Clave de seguridad'));
        if ($key === '') {
            $this->error('La clave no puede estar vacía.');

            return null;
        }
        if ($confirm && trim((string) $this->secret('Repite la clave')) !== $key) {
            $this->error('Las claves no coinciden.');

            return null;
        }

        return $key;
    }

    /**
     * Id del usuario de --by (null si no se dio; false si no existe).
     */
    private function resolveUserId(): int|false|null
    {
        $by = $this->option('by');
        if ($by === null || $by === '') {
            return null;
        }
        $id = User::where('username', $by)->value('id');
        if ($id === null) {
            $this->error("No existe el usuario {$by}.");

            return false;
        }

        return (int) $id;
    }
}
