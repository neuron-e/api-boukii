<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NewsletterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first user for the seeding
        $firstUser = DB::table('users')->first();
        if (!$firstUser) {
            $this->command->warn('No users found. Skipping newsletter seeding.');
            return;
        }

        $newsletters = [
            [
                'subject' => 'Bienvenida a la temporada de esquí 2025',
                'content' => '<h2>¡Bienvenidos a la nueva temporada!</h2><p>Estamos emocionados de dar inicio a la temporada de esquí 2025. Este año tenemos muchas novedades preparadas para vosotros.</p><p><strong>Novedades de esta temporada:</strong></p><ul><li>Nuevas pistas de esquí</li><li>Equipamiento renovado</li><li>Cursos especializados</li><li>Ofertas especiales para familias</li></ul><p>¡Os esperamos en las pistas!</p>',
                'recipients_config' => json_encode(['type' => 'all']),
                'status' => 'sent',
                'sent_at' => Carbon::now()->subDays(15),
                'created_at' => Carbon::now()->subDays(15),
                'updated_at' => Carbon::now()->subDays(15)
            ],
            [
                'subject' => 'Oferta especial: 20% descuento en cursos grupales',
                'content' => '<h2>¡Oferta limitada!</h2><p>Durante este mes, aprovecha un <strong>20% de descuento</strong> en todos nuestros cursos grupales.</p><p><strong>¿Qué incluye la oferta?</strong></p><ul><li>Cursos para principiantes</li><li>Cursos intermedios</li><li>Cursos avanzados</li><li>Clases de snowboard</li></ul><p>Válido hasta fin de mes. ¡No te lo pierdas!</p>',
                'recipients_config' => json_encode(['type' => 'active']),
                'status' => 'sent',
                'sent_at' => Carbon::now()->subDays(8),
                'created_at' => Carbon::now()->subDays(8),
                'updated_at' => Carbon::now()->subDays(8)
            ],
            [
                'subject' => 'Condiciones de nieve - Actualización semanal',
                'content' => '<h2>Reporte semanal de condiciones</h2><p>Las condiciones de nieve están excelentes esta semana:</p><p><strong>Estado de las pistas:</strong></p><ul><li><strong>Pistas verdes:</strong> Abiertas todas (15/15)</li><li><strong>Pistas azules:</strong> Abiertas 18/20</li><li><strong>Pistas rojas:</strong> Abiertas 12/15</li><li><strong>Pistas negras:</strong> Abiertas 5/8</li></ul><p><strong>Condiciones meteorológicas:</strong> Cielo despejado, -5°C, viento suave</p><p>¡Perfectas condiciones para esquiar!</p>',
                'recipients_config' => json_encode(['type' => 'all']),
                'status' => 'sent',
                'sent_at' => Carbon::now()->subDays(3),
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3)
            ],
            [
                'subject' => 'Curso de esquí nocturno - Nuevas fechas disponibles',
                'content' => '<h2>Esquí bajo las estrellas</h2><p>¡Ahora también puedes esquiar de noche! Hemos añadido nuevas fechas para nuestros cursos de esquí nocturno.</p><p><strong>Horarios disponibles:</strong></p><ul><li>Lunes y miércoles: 19:00 - 21:00</li><li>Viernes: 18:00 - 20:00</li><li>Sábados: 19:30 - 21:30</li></ul><p><strong>¿Qué incluye?</strong></p><ul><li>Equipo completo</li><li>Monitor especializado</li><li>Iluminación de pistas</li><li>Chocolate caliente al final</li></ul><p>Plazas limitadas. ¡Reserva ya!</p>',
                'recipients_config' => json_encode(['type' => 'vip']),
                'status' => 'sent',
                'sent_at' => Carbon::now()->subDays(1),
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1)
            ],
            [
                'subject' => 'BORRADOR: Evento especial fin de temporada',
                'content' => '<h2>Fiesta de clausura de temporada</h2><p>Estamos preparando algo especial para cerrar la temporada...</p><p><em>Este es un borrador que aún estamos trabajando.</em></p>',
                'recipients_config' => json_encode(['type' => 'all']),
                'status' => 'draft',
                'sent_at' => null,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subHours(6)
            ]
        ];

        foreach ($newsletters as $newsletter) {
            $newsletter['school_id'] = $firstUser->id;
            $newsletter['user_id'] = $firstUser->id;
            $newsletter['total_recipients'] = 100; // Default value
            DB::table('newsletters')->insert($newsletter);
        }
    }
}