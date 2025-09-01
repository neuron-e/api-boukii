<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use App\V5\Models\Season;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class V5SeedTestUsers extends Command
{
    protected $signature = 'boukii:v5:seed-test-users
        {--multi_email=multi@test.local}
        {--single_email=single@test.local}
        {--password=secret123}
        {--schools_multi=2 : Número de schools para el usuario multi}
        {--season_span=2024-12-01,2025-03-31 : Rango por defecto de temporada (YYYY-MM-DD,YYYY-MM-DD)}';

    protected $description = 'Crea 2 usuarios de prueba V5: uno con múltiples escuelas y otro con una sola, con temporadas activas.';

    public function handle(): int
    {
        $multiEmail = (string)$this->option('multi_email');
        $singleEmail = (string)$this->option('single_email');
        $password = (string)$this->option('password');
        $schoolsForMulti = max(2, (int)$this->option('schools_multi'));
        [$defStart, $defEnd] = array_pad(explode(',', (string)$this->option('season_span'), 2), 2, null);

        // Asegurar rol superadmin
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        // Buscar schools existentes
        $schools = School::query()->where('active', 1)->take($schoolsForMulti + 1)->get();
        if ($schools->isEmpty()) {
            $this->error('No se encontraron schools activas en la BD.');
            return 1;
        }

        // Crear/actualizar usuarios
        $multi = User::firstOrCreate(
            ['email' => $multiEmail],
            [
                'first_name' => 'Multi',
                'last_name' => 'Tester',
                'password' => Hash::make($password),
                'type' => 'admin',
                'active' => 1,
            ]
        );
        if (!Hash::check($password, $multi->password)) {
            $multi->password = Hash::make($password);
            $multi->active = 1;
            $multi->save();
        }

        $single = User::firstOrCreate(
            ['email' => $singleEmail],
            [
                'first_name' => 'Single',
                'last_name' => 'Tester',
                'password' => Hash::make($password),
                'type' => 'admin',
                'active' => 1,
            ]
        );
        if (!Hash::check($password, $single->password)) {
            $single->password = Hash::make($password);
            $single->active = 1;
            $single->save();
        }

        // Asignar rol superadmin
        $multi->syncRoles([$role->name]);
        $single->syncRoles([$role->name]);

        // Vincular usuarios a schools (pivot school_users)
        $this->attachUserToSchoolsWithSeasons($multi, $schools->take($schoolsForMulti)->values(), $defStart, $defEnd);
        $this->attachUserToSchoolsWithSeasons($single, $schools->skip($schoolsForMulti)->take(1)->values(), $defStart, $defEnd);

        $this->info('Usuarios de prueba creados/actualizados:');
        $this->line(" - Multi: {$multiEmail} / {$password}");
        $this->line(" - Single: {$singleEmail} / {$password}");

        return 0;
    }

    private function attachUserToSchoolsWithSeasons(User $user, $schools, ?string $defStart, ?string $defEnd): void
    {
        foreach ($schools as $school) {
            // Pivot school_users
            $this->ensurePivotUserSchool($user->id, $school->id);

            // Temporadas: asegurar al menos 1 activa y 1 adicional inactiva
            $active = Season::where('school_id', $school->id)->where('is_active', true)->first();
            if (!$active) {
                $start = $defStart ?: Carbon::now()->startOfYear()->toDateString();
                $end = $defEnd ?: Carbon::now()->endOfYear()->toDateString();
                $active = Season::create([
                    'school_id' => $school->id,
                    'name' => 'Test Season ' . Carbon::parse($start)->format('Y'),
                    'start_date' => $start,
                    'end_date' => $end,
                    'is_active' => true,
                ]);
            }
            // Crear otra temporada no activa, si no hay
            $extra = Season::where('school_id', $school->id)->where('id', '!=', $active->id)->first();
            if (!$extra) {
                $y = Carbon::parse($active->start_date)->subYear();
                Season::create([
                    'school_id' => $school->id,
                    'name' => 'Prev Season ' . $y->format('Y'),
                    'start_date' => $y->copy()->startOfYear()->toDateString(),
                    'end_date' => $y->copy()->endOfYear()->toDateString(),
                    'is_active' => false,
                ]);
            }
        }
    }

    private function ensurePivotUserSchool(int $userId, int $schoolId): void
    {
        if (!Schema::hasTable('school_users')) {
            // Crear pivot mínimo si no existe
            Schema::create('school_users', function ($table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('school_id');
                $table->timestamps();
                $table->softDeletes();
                $table->primary(['user_id', 'school_id']);
            });
        }

        $exists = DB::table('school_users')
            ->where('user_id', $userId)
            ->where('school_id', $schoolId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            DB::table('school_users')->insert([
                'user_id' => $userId,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }
}

