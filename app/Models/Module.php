<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Modules\ModulesRegistry;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'version',
        'pricing',
        'features',
        'active',
        'mandatory',
        'sort_order',
    ];

    protected $casts = [
        'pricing' => 'array',
        'features' => 'array',
        'active' => 'boolean',
        'mandatory' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SchoolModuleSubscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active');
    }

    public function isCore(): bool
    {
        return $this->mandatory === true;
    }

    public function getDependencies(): array
    {
        // Por ahora retornar array vacío hasta que se implemente en BD
        return [];
    }

    public function hasDependencies(): bool
    {
        return !empty($this->getDependencies());
    }

    public static function getCoreModules(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('mandatory', true)->get();
    }

    public static function getContractableModules(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('mandatory', false)->get();
    }

    public static function syncFromRegistry(): void
    {
        foreach (ModulesRegistry::all() as $moduleData) {
            static::updateOrCreate(
                ['slug' => $moduleData['slug']],
                [
                    'name' => $moduleData['name'],
                    'description' => $moduleData['description'] ?? '',
                    'category' => self::getCategoryFromPriority($moduleData['priority']),
                    'version' => '1.0.0',
                    'pricing' => $moduleData['pricing'] ?? [],
                    'features' => $moduleData['features'] ?? [],
                    'active' => true,
                    'mandatory' => $moduleData['priority'] === 'core',
                    'sort_order' => self::getSortOrderFromPriority($moduleData['priority']),
                ]
            );
        }
    }
    
    private static function getCategoryFromPriority(string $priority): string
    {
        return match($priority) {
            'core' => 'sistema',
            'high' => 'gestion',
            'medium' => 'herramientas',
            'low' => 'extras',
            default => 'otros'
        };
    }
    
    private static function getSortOrderFromPriority(string $priority): int
    {
        return match($priority) {
            'core' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 5
        };
    }
}
