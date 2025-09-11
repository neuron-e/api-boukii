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
     *  - description: module description
     *  - pricing: pricing tiers configuration
     *  - features: available features per tier
     *  - limits: default limits per tier
     *
     * @return array<int, array{slug:string,name:string,deps:array<int,string>,priority:string,description?:string,pricing?:array,features?:array,limits?:array}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'auth',
                'name' => 'Authentication',
                'deps' => [],
                'priority' => 'core',
                'description' => 'Sistema de autenticación y autorización'
            ],
            [
                'slug' => 'schools',
                'name' => 'Schools',
                'deps' => [],
                'priority' => 'core',
                'description' => 'Gestión de escuelas multi-tenant'
            ],
            [
                'slug' => 'seasons',
                'name' => 'Seasons',
                'deps' => [],
                'priority' => 'core',
                'description' => 'Gestión de temporadas'
            ],
            [
                'slug' => 'users_roles',
                'name' => 'Users & Roles',
                'deps' => [],
                'priority' => 'core',
                'description' => 'Gestión de usuarios y roles'
            ],
            [
                'slug' => 'feature_flags',
                'name' => 'Feature Flags',
                'deps' => [],
                'priority' => 'core',
                'description' => 'Sistema de feature flags'
            ],
            [
                'slug' => 'courses',
                'name' => 'Courses',
                'deps' => [],
                'priority' => 'high',
                'description' => 'Gestión de cursos y clases',
                'pricing' => [
                    'free' => ['price' => 0, 'courses' => 10],
                    'basic' => ['price' => 29, 'courses' => 100],
                    'premium' => ['price' => 59, 'courses' => 500],
                    'enterprise' => ['price' => 149, 'courses' => -1]
                ],
                'features' => [
                    'free' => ['basic_scheduling', 'simple_booking'],
                    'basic' => ['advanced_scheduling', 'group_management', 'basic_reports'],
                    'premium' => ['recurring_events', 'waitlist', 'advanced_reports', 'integrations'],
                    'enterprise' => ['custom_fields', 'api_access', 'priority_support', 'custom_integrations']
                ]
            ],
            [
                'slug' => 'clients',
                'name' => 'Clients',
                'deps' => [],
                'priority' => 'high',
                'description' => 'Gestión de clientes y participantes',
                'pricing' => [
                    'free' => ['price' => 0, 'clients' => 50],
                    'basic' => ['price' => 19, 'clients' => 500],
                    'premium' => ['price' => 39, 'clients' => 2000],
                    'enterprise' => ['price' => 99, 'clients' => -1]
                ],
                'features' => [
                    'free' => ['basic_profile', 'simple_search'],
                    'basic' => ['custom_fields', 'advanced_search', 'export'],
                    'premium' => ['segmentation', 'communication_history', 'automated_messaging'],
                    'enterprise' => ['crm_integration', 'advanced_analytics', 'bulk_operations']
                ]
            ],
            [
                'slug' => 'bookings',
                'name' => 'Bookings',
                'deps' => ['courses', 'clients', 'seasons'],
                'priority' => 'high',
                'description' => 'Sistema de reservas y pagos',
                'pricing' => [
                    'free' => ['price' => 0, 'bookings_per_month' => 100],
                    'basic' => ['price' => 49, 'bookings_per_month' => 1000],
                    'premium' => ['price' => 99, 'bookings_per_month' => 5000],
                    'enterprise' => ['price' => 199, 'bookings_per_month' => -1]
                ],
                'features' => [
                    'free' => ['basic_booking', 'simple_payment'],
                    'basic' => ['online_payment', 'booking_management', 'basic_notifications'],
                    'premium' => ['recurring_bookings', 'payment_plans', 'advanced_notifications', 'cancellation_policies'],
                    'enterprise' => ['custom_pricing', 'advanced_reporting', 'api_integrations', 'white_label']
                ]
            ],
            [
                'slug' => 'renting',
                'name' => 'Renting',
                'deps' => ['schools', 'auth'],
                'priority' => 'high',
                'description' => 'Gestión de alquiler de equipamiento',
                'pricing' => [
                    'free' => ['price' => 0, 'items' => 20],
                    'basic' => ['price' => 25, 'items' => 100],
                    'premium' => ['price' => 49, 'items' => 500],
                    'enterprise' => ['price' => 99, 'items' => -1]
                ],
                'features' => [
                    'free' => ['basic_inventory', 'simple_rental'],
                    'basic' => ['damage_tracking', 'deposit_management', 'availability_calendar'],
                    'premium' => ['barcode_scanning', 'automated_reminders', 'maintenance_tracking'],
                    'enterprise' => ['advanced_analytics', 'multi_location', 'custom_integrations']
                ]
            ],
            [
                'slug' => 'instructors',
                'name' => 'Instructors',
                'deps' => [],
                'priority' => 'medium',
                'description' => 'Gestión de instructores y staff',
                'pricing' => [
                    'free' => ['price' => 0, 'instructors' => 5],
                    'basic' => ['price' => 19, 'instructors' => 25],
                    'premium' => ['price' => 39, 'instructors' => 100],
                    'enterprise' => ['price' => 79, 'instructors' => -1]
                ]
            ],
            [
                'slug' => 'schedules',
                'name' => 'Schedules',
                'deps' => [],
                'priority' => 'medium',
                'description' => 'Planificador y gestión de horarios'
            ],
            [
                'slug' => 'vouchers',
                'name' => 'Vouchers',
                'deps' => [],
                'priority' => 'medium',
                'description' => 'Sistema de vouchers y descuentos'
            ],
            [
                'slug' => 'finance',
                'name' => 'Finance',
                'deps' => [],
                'priority' => 'low',
                'description' => 'Módulo financiero y contabilidad'
            ],
            [
                'slug' => 'analytics',
                'name' => 'Analytics',
                'deps' => [],
                'priority' => 'low',
                'description' => 'Análisis y reportes avanzados',
                'pricing' => [
                    'basic' => ['price' => 29, 'reports' => 10],
                    'premium' => ['price' => 59, 'reports' => 50],
                    'enterprise' => ['price' => 119, 'reports' => -1]
                ]
            ],
            [
                'slug' => 'comms',
                'name' => 'Communications',
                'deps' => [],
                'priority' => 'low',
                'description' => 'Sistema de comunicaciones y marketing'
            ],
        ];
    }

    /**
     * Get modules filtered by priority
     */
    public static function byPriority(string $priority): array
    {
        return array_filter(self::all(), fn($module) => $module['priority'] === $priority);
    }

    /**
     * Get core modules (always available)
     */
    public static function getCoreModules(): array
    {
        return self::byPriority('core');
    }

    /**
     * Get contractable modules (can be subscribed to)
     */
    public static function getContractableModules(): array
    {
        return array_filter(self::all(), fn($module) => $module['priority'] !== 'core');
    }

    /**
     * Get module by slug
     */
    public static function getModule(string $slug): ?array
    {
        foreach (self::all() as $module) {
            if ($module['slug'] === $slug) {
                return $module;
            }
        }
        return null;
    }

    /**
     * Get module pricing tiers
     */
    public static function getModulePricing(string $slug): ?array
    {
        $module = self::getModule($slug);
        return $module['pricing'] ?? null;
    }

    /**
     * Get module features for a specific tier
     */
    public static function getModuleFeatures(string $slug, string $tier): array
    {
        $module = self::getModule($slug);
        return $module['features'][$tier] ?? [];
    }

    /**
     * Check if module has dependencies
     */
    public static function hasDependencies(string $slug): bool
    {
        $module = self::getModule($slug);
        return !empty($module['deps'] ?? []);
    }

    /**
     * Get module dependencies recursively
     */
    public static function getDependencies(string $slug, array &$resolved = []): array
    {
        $module = self::getModule($slug);
        if (!$module) {
            return [];
        }

        foreach ($module['deps'] as $dep) {
            if (!in_array($dep, $resolved)) {
                $resolved[] = $dep;
                self::getDependencies($dep, $resolved);
            }
        }

        return $resolved;
    }

    /**
     * Validate if a module can be activated (dependencies satisfied)
     */
    public static function canActivateModule(string $slug, array $activeModules): array
    {
        $dependencies = self::getDependencies($slug);
        $missing = array_diff($dependencies, $activeModules);
        
        return [
            'can_activate' => empty($missing),
            'missing_dependencies' => $missing
        ];
    }
}
