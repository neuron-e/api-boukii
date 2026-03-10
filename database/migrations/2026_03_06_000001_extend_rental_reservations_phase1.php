<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // rental_reservations — Phase 1 new fields
        if (Schema::hasTable('rental_reservations')) {
            Schema::table('rental_reservations', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_reservations', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('meta');
                }
                if (!Schema::hasColumn('rental_reservations', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('rental_reservations', 'deposit_amount')) {
                    $table->decimal('deposit_amount', 10, 2)->default(0)->after('cancellation_reason');
                }
                if (!Schema::hasColumn('rental_reservations', 'deposit_status')) {
                    $table->enum('deposit_status', ['none', 'held', 'released', 'forfeited'])->default('none')->after('deposit_amount');
                }
                if (!Schema::hasColumn('rental_reservations', 'damage_total')) {
                    $table->decimal('damage_total', 10, 2)->default(0)->after('deposit_status');
                }
                if (!Schema::hasColumn('rental_reservations', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->after('damage_total');
                }
            });
        }

        // rental_reservation_lines — Phase 1 new fields
        if (Schema::hasTable('rental_reservation_lines')) {
            Schema::table('rental_reservation_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_reservation_lines', 'returned_quantity')) {
                    $table->integer('returned_quantity')->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('rental_reservation_lines', 'damage_notes')) {
                    $table->text('damage_notes')->nullable()->after('returned_quantity');
                }
            });
        }

        // rental_units — blocked_until for overbooking prevention
        if (Schema::hasTable('rental_units')) {
            Schema::table('rental_units', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_units', 'blocked_until')) {
                    $table->timestamp('blocked_until')->nullable()->after('notes');
                }
            });
        }

        // rental_policies — enabled flag for feature flag migration
        if (Schema::hasTable('rental_policies')) {
            Schema::table('rental_policies', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_policies', 'enabled')) {
                    $table->boolean('enabled')->default(false)->after('school_id');
                }
            });
        }

        // rental_events — new audit table
        if (!Schema::hasTable('rental_events')) {
            Schema::create('rental_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('rental_reservation_id')->index();
                $table->string('event_type', 50)->index();
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['rental_reservation_id', 'created_at'], 're_res_date_idx');
            });
        }

        // Index for performance
        if (Schema::hasTable('rental_reservations')) {
            try {
                Schema::table('rental_reservations', function (Blueprint $table) {
                    $table->index(['school_id', 'start_date', 'status'], 'rr_school_date_status_idx');
                });
            } catch (\Throwable $e) {
                // Index may already exist — ignore
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_events');

        if (Schema::hasTable('rental_reservations')) {
            Schema::table('rental_reservations', function (Blueprint $table) {
                foreach (['cancelled_at', 'cancellation_reason', 'deposit_amount', 'deposit_status', 'damage_total', 'payment_id'] as $col) {
                    if (Schema::hasColumn('rental_reservations', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('rental_reservation_lines')) {
            Schema::table('rental_reservation_lines', function (Blueprint $table) {
                foreach (['returned_quantity', 'damage_notes'] as $col) {
                    if (Schema::hasColumn('rental_reservation_lines', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('rental_units')) {
            Schema::table('rental_units', function (Blueprint $table) {
                if (Schema::hasColumn('rental_units', 'blocked_until')) {
                    $table->dropColumn('blocked_until');
                }
            });
        }

        if (Schema::hasTable('rental_policies')) {
            Schema::table('rental_policies', function (Blueprint $table) {
                if (Schema::hasColumn('rental_policies', 'enabled')) {
                    $table->dropColumn('enabled');
                }
            });
        }
    }
};
