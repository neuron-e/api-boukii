<?php

namespace App\Domain\Modules;

class ModulesRegistry
{
    /**
     * Retrieve the list of available modules.
     *
     * Each module contains:
     *  - slug: unique identifier
     *  - name: human readable name
     *  - deps: array of dependent module slugs
     *  - priority: core|high|medium|low
     *
     * @return array<int, array{slug:string,name:string,deps:array<int,string>,priority:string}>
     */
    public static function all(): array
    {
        return [
            ['slug' => 'auth', 'name' => 'Authentication', 'deps' => [], 'priority' => 'core'],
            ['slug' => 'schools', 'name' => 'Schools', 'deps' => [], 'priority' => 'core'],
            ['slug' => 'seasons', 'name' => 'Seasons', 'deps' => [], 'priority' => 'core'],
            ['slug' => 'users_roles', 'name' => 'Users & Roles', 'deps' => [], 'priority' => 'core'],
            ['slug' => 'feature_flags', 'name' => 'Feature Flags', 'deps' => [], 'priority' => 'core'],
            ['slug' => 'courses', 'name' => 'Courses', 'deps' => [], 'priority' => 'high'],
            ['slug' => 'clients', 'name' => 'Clients', 'deps' => [], 'priority' => 'high'],
            [
                'slug' => 'bookings',
                'name' => 'Bookings',
                'deps' => ['courses', 'clients', 'seasons'],
                'priority' => 'high',
            ],
            [
                'slug' => 'renting',
                'name' => 'Renting',
                'deps' => ['schools', 'auth'],
                'priority' => 'high',
            ],
            ['slug' => 'instructors', 'name' => 'Instructors', 'deps' => [], 'priority' => 'medium'],
            ['slug' => 'schedules', 'name' => 'Schedules', 'deps' => [], 'priority' => 'medium'],
            ['slug' => 'vouchers', 'name' => 'Vouchers', 'deps' => [], 'priority' => 'medium'],
            ['slug' => 'finance', 'name' => 'Finance', 'deps' => [], 'priority' => 'low'],
            ['slug' => 'analytics', 'name' => 'Analytics', 'deps' => [], 'priority' => 'low'],
            ['slug' => 'comms', 'name' => 'Communications', 'deps' => [], 'priority' => 'low'],
        ];
    }
}
