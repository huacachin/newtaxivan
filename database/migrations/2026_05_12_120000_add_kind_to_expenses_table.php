<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('kind', ['Fijos', 'Otros', 'Planilla'])
                ->nullable()
                ->after('reason');
            $table->index('kind');
        });

        $conceptNames = DB::table('concepts')->pluck('name')->map(fn($n) => (string)$n)->all();

        $controladores = DB::table('users')
            ->join('model_has_roles', function ($j) {
                $j->on('users.id', '=', 'model_has_roles.model_id')
                  ->where('model_has_roles.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'controlador')
            ->where('users.status', 'active')
            ->get(['users.name', 'users.username']);

        $conceptSet      = array_flip($conceptNames);
        $usernameSet     = [];
        $nameToUsername  = [];
        foreach ($controladores as $c) {
            $username = (string)($c->username ?? '');
            $name     = (string)($c->name ?? '');
            if ($username !== '') {
                $usernameSet[$username] = true;
            }
            if ($name !== '' && $username !== '') {
                $nameToUsername[$name] = $username;
            }
        }

        DB::table('expenses')
            ->whereNull('kind')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($conceptSet, $usernameSet, $nameToUsername) {
                foreach ($rows as $row) {
                    $reason = (string)($row->reason ?? '');
                    $update = [];

                    if (isset($conceptSet[$reason])) {
                        $update['kind'] = 'Fijos';
                    } elseif (isset($usernameSet[$reason])) {
                        $update['kind'] = 'Planilla';
                    } elseif (isset($nameToUsername[$reason])) {
                        $update['kind']   = 'Planilla';
                        $update['reason'] = $nameToUsername[$reason];
                    } else {
                        $update['kind'] = 'Otros';
                    }
                    DB::table('expenses')->where('id', $row->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
