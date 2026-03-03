<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_categories')) {
            Schema::create('rental_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('icon')->nullable();
                $table->boolean('active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_subcategories')) {
            Schema::create('rental_subcategories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('category_id')->index();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->boolean('active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_items')) {
            Schema::create('rental_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('category_id')->index();
                $table->string('name');
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_variants')) {
            Schema::create('rental_variants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->unsignedBigInteger('subcategory_id')->nullable()->index();
                $table->string('name');
                $table->string('size_group')->nullable();
                $table->string('size_label')->nullable();
                $table->string('sku')->nullable()->index();
                $table->string('barcode')->nullable()->index();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_warehouses')) {
            Schema::create('rental_warehouses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('address')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_pickup_points')) {
            Schema::create('rental_pickup_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('address')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_units')) {
            Schema::create('rental_units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('variant_id')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->string('serial')->nullable()->index();
                $table->string('status')->default('available')->index();
                $table->string('condition')->default('excellent');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_pricing_rules')) {
            Schema::create('rental_pricing_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('item_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->string('period_type')->default('full_day')->index();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency', 8)->default('CHF');
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_policies')) {
            Schema::create('rental_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('default_deposit_mode')->default('none');
                $table->decimal('default_deposit_value', 10, 2)->default(0);
                $table->boolean('auto_assign_on_create')->default(false);
                $table->boolean('allow_overbooking')->default(false);
                $table->integer('grace_minutes')->default(30);
                $table->text('terms')->nullable();
                $table->longText('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('rental_reservations')) {
            Schema::create('rental_reservations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('booking_id')->nullable()->index();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->unsignedBigInteger('pickup_point_id')->nullable()->index();
                $table->unsignedBigInteger('return_point_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->string('reference')->nullable()->index();
                $table->string('status')->default('pending')->index();
                $table->string('currency', 8)->default('CHF');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('discount_total', 10, 2)->default(0);
                $table->decimal('tax_total', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->text('notes')->nullable();
                $table->longText('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('rental_reservation_lines')) {
            Schema::create('rental_reservation_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('rental_reservation_id')->index();
                $table->unsignedBigInteger('item_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->decimal('line_total', 10, 2)->default(0);
                $table->longText('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('rental_reservation_unit_assignments')) {
            Schema::create('rental_reservation_unit_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id');
                $table->unsignedBigInteger('rental_reservation_id');
                $table->unsignedBigInteger('rental_reservation_line_id')->nullable();
                $table->unsignedBigInteger('rental_unit_id')->nullable();
                $table->string('assignment_type')->default('assigned');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->string('condition_out')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('school_id', 'rrua_school_idx');
                $table->index('rental_reservation_id', 'rrua_res_idx');
                $table->index('rental_reservation_line_id', 'rrua_line_idx');
                $table->index('rental_unit_id', 'rrua_unit_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_reservation_unit_assignments');
        Schema::dropIfExists('rental_reservation_lines');
        Schema::dropIfExists('rental_reservations');
        Schema::dropIfExists('rental_policies');
        Schema::dropIfExists('rental_pricing_rules');
        Schema::dropIfExists('rental_units');
        Schema::dropIfExists('rental_pickup_points');
        Schema::dropIfExists('rental_warehouses');
        Schema::dropIfExists('rental_variants');
        Schema::dropIfExists('rental_items');
        Schema::dropIfExists('rental_subcategories');
        Schema::dropIfExists('rental_categories');
    }
};
