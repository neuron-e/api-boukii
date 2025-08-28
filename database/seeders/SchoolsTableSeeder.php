<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('schools')->insert([
            [
                'id' => 1,
                'name' => 'Escuela Ejemplo 1',
                'description' => 'Descripción de la escuela 1',
                'contact_email' => 'contacto@escuela1.com',
                'contact_phone' => '123456789',
                'contact_telephone' => '987654321',
                'contact_address' => 'Calle Ejemplo 1',
                'contact_cp' => '28001',
                'contact_city' => 'Ciudad 1',
                'contact_province' => 'Provincia 1',
                'contact_country' => 'País 1',
                'fiscal_name' => 'Fiscal Escuela 1',
                'fiscal_id' => 'ID Fiscal 1',
                'fiscal_address' => 'Dirección Fiscal 1',
                'fiscal_cp' => '28002',
                'fiscal_city' => 'Ciudad Fiscal 1',
                'fiscal_province' => 'Provincia Fiscal 1',
                'fiscal_country' => 'País Fiscal 1',
                'iban' => 'IBAN12345',
                'logo' => 'logo1.jpg',
                'slug' => 'escuela-ejemplo-1',
                'cancellation_insurance_percent' => 10.00,
                'payrexx_instance' => null,
                'payrexx_key' => null,
                'payment_provider' => 'payyo',
                'payyo_instance' => encrypt('test_instance'),
                'payyo_key' => encrypt('test_key'),
                'conditions_url' => '',
                'bookings_comission_cash' => 5.00,
                'bookings_comission_boukii_pay' => 5.00,
                'bookings_comission_other' => 5.00,
                'school_rate' => 0.00,
                'has_ski' => 1,
                'has_snowboard' => 1,
                'has_telemark' => 0,
                'has_rando' => 0,
                'inscription' => 0,
                'type' => 'tipo1',
                'active' => 1,
                'settings' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null
            ],
            // Puedes añadir más registros aquí
        ]);


    }
}
