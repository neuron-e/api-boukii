<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_brands')) {
            Schema::create('rental_brands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('slug', 140)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['school_id', 'name']);
            });
        }

        if (!Schema::hasTable('rental_models')) {
            Schema::create('rental_models', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable()->index();
                $table->unsignedBigInteger('brand_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('slug', 140)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['school_id', 'brand_id', 'name']);
            });
        }

        if (Schema::hasTable('rental_items')) {
            Schema::table('rental_items', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_items', 'brand_id')) {
                    $table->unsignedBigInteger('brand_id')->nullable()->after('model');
                    $table->index('brand_id', 'rental_items_brand_id_index');
                }
                if (!Schema::hasColumn('rental_items', 'model_id')) {
                    $table->unsignedBigInteger('model_id')->nullable()->after('brand_id');
                    $table->index('model_id', 'rental_items_model_id_index');
                }
            });
        }

        $this->backfillBrandModelLinks();
    }

    private function backfillBrandModelLinks(): void
    {
        if (!Schema::hasTable('rental_items') || !Schema::hasTable('rental_brands') || !Schema::hasTable('rental_models')) {
            return;
        }

        $items = DB::table('rental_items')
            ->select('id', 'school_id', 'brand', 'model', 'brand_id', 'model_id')
            ->when(Schema::hasColumn('rental_items', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $schoolId = isset($item->school_id) ? (int) $item->school_id : null;
            $legacyBrand = trim((string) ($item->brand ?? ''));
            $legacyModel = trim((string) ($item->model ?? ''));
            $brandId = (int) ($item->brand_id ?? 0);
            $modelId = (int) ($item->model_id ?? 0);

            if ($brandId <= 0 && $legacyBrand !== '') {
                $brandId = $this->resolveBrandId($schoolId, $legacyBrand);
            }

            if ($modelId <= 0 && $legacyModel !== '') {
                $modelId = $this->resolveModelId($schoolId, $brandId > 0 ? $brandId : null, $legacyModel);
            }

            $payload = [];
            if ($brandId > 0 && Schema::hasColumn('rental_items', 'brand_id')) {
                $payload['brand_id'] = $brandId;
            }
            if ($modelId > 0 && Schema::hasColumn('rental_items', 'model_id')) {
                $payload['model_id'] = $modelId;
            }
            if (!empty($payload) && Schema::hasColumn('rental_items', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            if (!empty($payload)) {
                DB::table('rental_items')->where('id', (int) $item->id)->update($payload);
            }
        }
    }

    private function resolveBrandId(?int $schoolId, string $name): int
    {
        $query = DB::table('rental_brands')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if (Schema::hasColumn('rental_brands', 'school_id')) {
            if ($schoolId && $schoolId > 0) {
                $query->where('school_id', $schoolId);
            } else {
                $query->whereNull('school_id');
            }
        }

        $existing = $query->first();
        if ($existing) {
            if (Schema::hasColumn('rental_brands', 'deleted_at') && !empty($existing->deleted_at)) {
                DB::table('rental_brands')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
            }
            return (int) $existing->id;
        }

        $payload = [
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'active' => true,
        ];
        if (Schema::hasColumn('rental_brands', 'school_id')) {
            $payload['school_id'] = ($schoolId && $schoolId > 0) ? $schoolId : null;
        }
        if (Schema::hasColumn('rental_brands', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_brands', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        return (int) DB::table('rental_brands')->insertGetId($payload);
    }

    private function resolveModelId(?int $schoolId, ?int $brandId, string $name): int
    {
        $query = DB::table('rental_models')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if (Schema::hasColumn('rental_models', 'brand_id')) {
            if ($brandId && $brandId > 0) {
                $query->where('brand_id', $brandId);
            } else {
                $query->whereNull('brand_id');
            }
        }
        if (Schema::hasColumn('rental_models', 'school_id')) {
            if ($schoolId && $schoolId > 0) {
                $query->where('school_id', $schoolId);
            } else {
                $query->whereNull('school_id');
            }
        }

        $existing = $query->first();
        if ($existing) {
            if (Schema::hasColumn('rental_models', 'deleted_at') && !empty($existing->deleted_at)) {
                DB::table('rental_models')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
            }
            return (int) $existing->id;
        }

        $payload = [
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'active' => true,
        ];
        if (Schema::hasColumn('rental_models', 'brand_id')) {
            $payload['brand_id'] = ($brandId && $brandId > 0) ? $brandId : null;
        }
        if (Schema::hasColumn('rental_models', 'school_id')) {
            $payload['school_id'] = ($schoolId && $schoolId > 0) ? $schoolId : null;
        }
        if (Schema::hasColumn('rental_models', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_models', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        return (int) DB::table('rental_models')->insertGetId($payload);
    }

    public function down(): void
    {
        if (Schema::hasTable('rental_items')) {
            Schema::table('rental_items', function (Blueprint $table) {
                if (Schema::hasColumn('rental_items', 'model_id')) {
                    $table->dropIndex('rental_items_model_id_index');
                    $table->dropColumn('model_id');
                }
                if (Schema::hasColumn('rental_items', 'brand_id')) {
                    $table->dropIndex('rental_items_brand_id_index');
                    $table->dropColumn('brand_id');
                }
            });
        }

        Schema::dropIfExists('rental_models');
        Schema::dropIfExists('rental_brands');
    }
};

