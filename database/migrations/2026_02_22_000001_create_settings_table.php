<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->timestamps();
        });

        // Seed defaults
        $defaults = [
            // Company
            ['key' => 'company.name',     'value' => 'SmartERP Việt Nam', 'group' => 'company'],
            ['key' => 'company.tax_code', 'value' => '',                  'group' => 'company'],
            ['key' => 'company.address',  'value' => '',                  'group' => 'company'],
            ['key' => 'company.phone',    'value' => '',                  'group' => 'company'],
            ['key' => 'company.email',    'value' => '',                  'group' => 'company'],
            ['key' => 'company.website',  'value' => '',                  'group' => 'company'],
            ['key' => 'company.currency', 'value' => 'VND',               'group' => 'company'],

            // Security
            ['key' => 'security.min_password_length',  'value' => '8',     'group' => 'security'],
            ['key' => 'security.require_uppercase',    'value' => '1',     'group' => 'security'],
            ['key' => 'security.require_number',       'value' => '1',     'group' => 'security'],
            ['key' => 'security.require_special',      'value' => '0',     'group' => 'security'],
            ['key' => 'security.password_expiry_days', 'value' => '0',     'group' => 'security'],
            ['key' => 'security.jwt_ttl',              'value' => '60',    'group' => 'security'],
            ['key' => 'security.refresh_ttl_days',     'value' => '7',     'group' => 'security'],
            ['key' => 'security.allow_multiple_sessions', 'value' => '0',  'group' => 'security'],
            ['key' => 'security.max_login_attempts',   'value' => '5',     'group' => 'security'],
            ['key' => 'security.lockout_duration',     'value' => '15',    'group' => 'security'],

            // Mail
            ['key' => 'mail.driver',       'value' => 'smtp',      'group' => 'mail'],
            ['key' => 'mail.host',         'value' => '',          'group' => 'mail'],
            ['key' => 'mail.port',         'value' => '587',       'group' => 'mail'],
            ['key' => 'mail.encryption',   'value' => 'tls',       'group' => 'mail'],
            ['key' => 'mail.username',     'value' => '',          'group' => 'mail'],
            ['key' => 'mail.password',     'value' => '',          'group' => 'mail'],
            ['key' => 'mail.from_name',    'value' => 'SmartERP', 'group' => 'mail'],
            ['key' => 'mail.from_address', 'value' => '',          'group' => 'mail'],

            // Maintenance
            ['key' => 'maintenance.enabled', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance.message', 'value' => 'Hệ thống đang nâng cấp, vui lòng quay lại sau...', 'group' => 'maintenance'],
        ];

        foreach ($defaults as $row) {
            \DB::table('settings')->insertOrIgnore($row + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
