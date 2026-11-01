<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity logs now cover every account (buyers, sellers, couriers, sorting centers and admins),
 * not only admins. "admin_id" / "admin_name" keep their names but hold whoever did the action.
 * Written so it can run again safely if a database already has some of these parts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_logs', 'user_role')) {
            Schema::table('activity_logs', fn (Blueprint $t) => $t->string('user_role', 30)->nullable()->after('admin_name'));
        }

        // Older rows: fill in the role of whoever did them.
        \Illuminate\Support\Facades\DB::table('activity_logs')->whereNull('user_role')->whereNotNull('admin_id')
            ->update(['user_role' => \Illuminate\Support\Facades\DB::raw(
                '(SELECT users.role FROM users WHERE users.id = activity_logs.admin_id)'
            )]);

        $existing = collect(Schema::getIndexes('activity_logs'))->pluck('name')->all();
        Schema::table('activity_logs', function (Blueprint $table) use ($existing) {
            foreach ([
                'activity_logs_created_at_index'           => ['created_at'],
                'activity_logs_user_role_created_at_index' => ['user_role', 'created_at'],
                'activity_logs_action_index'               => ['action'],
                'activity_logs_ip_address_index'           => ['ip_address'],
            ] as $name => $columns) {
                if (! in_array($name, $existing, true)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('activity_logs'))->pluck('name')->all();
        Schema::table('activity_logs', function (Blueprint $table) use ($existing) {
            foreach (['activity_logs_created_at_index', 'activity_logs_user_role_created_at_index',
                      'activity_logs_action_index', 'activity_logs_ip_address_index'] as $name) {
                if (in_array($name, $existing, true)) {
                    $table->dropIndex($name);
                }
            }
        });
        if (Schema::hasColumn('activity_logs', 'user_role')) {
            Schema::table('activity_logs', fn (Blueprint $t) => $t->dropColumn('user_role'));
        }
    }
};
