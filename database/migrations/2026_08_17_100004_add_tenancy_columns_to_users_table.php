<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('firm_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('firm_id')->constrained()->nullOnDelete();
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('job_role')->nullable()->after('phone');
            $table->string('status')->default('active')->after('job_role');
            $table->string('avatar_path')->nullable()->after('status');
            $table->boolean('is_platform_admin')->default(false)->after('avatar_path');
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->json('preferences')->nullable()->after('remember_token');
            $table->json('notification_preferences')->nullable()->after('preferences');
            $table->timestamp('last_login_at')->nullable()->after('notification_preferences');
            $table->date('joined_at')->nullable()->after('last_login_at');
            $table->timestamp('deactivated_at')->nullable()->after('joined_at');

            $table->index(['firm_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['firm_id', 'status']);
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('firm_id');
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'job_role',
                'status',
                'avatar_path',
                'is_platform_admin',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'preferences',
                'notification_preferences',
                'last_login_at',
                'joined_at',
                'deactivated_at',
            ]);
        });
    }
};
