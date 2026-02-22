<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    // ── GET /api/settings ────────────────────────────────────────
    public function index(): JsonResponse
    {
        $raw  = Setting::allAsArray();
        $data = [];

        foreach ($raw as $key => $value) {
            // Convert booleans stored as "0"/"1"
            if (in_array($key, [
                'security.require_uppercase',
                'security.require_number',
                'security.require_special',
                'security.allow_multiple_sessions',
                'maintenance.enabled',
            ])) {
                $value = (bool)(int)$value;
            } elseif (in_array($key, [
                'security.min_password_length',
                'security.password_expiry_days',
                'security.jwt_ttl',
                'security.refresh_ttl_days',
                'security.max_login_attempts',
                'security.lockout_duration',
                'mail.port',
            ])) {
                $value = (int)$value;
            }

            // Flatten: "company.name" → data['company']['name']
            [$group, $field] = explode('.', $key, 2);
            $data[$group][$field] = $value;
        }

        return response()->json($data);
    }

    // ── POST /api/settings ──────────────────────────────────────
    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'group'  => 'required|string|in:company,security,mail,maintenance',
            'values' => 'required|array',
        ]);

        $group = $payload['group'];

        foreach ($payload['values'] as $field => $value) {
            // Never save raw passwords if empty (already set)
            if ($group === 'mail' && $field === 'password' && $value === '') {
                continue;
            }
            Setting::set("{$group}.{$field}", $value, $group);
        }

        return response()->json(['message' => 'Đã lưu thành công']);
    }

    // ── GET /api/settings/status ─────────────────────────────────
    public function systemStatus(): JsonResponse
    {
        $statuses = [];

        // Database
        try {
            DB::select('SELECT 1');
            $driver = config('database.default');
            $statuses[] = ['label' => 'Database', 'value' => strtoupper($driver) . ' — Kết nối OK', 'ok' => true];
        } catch (\Throwable $e) {
            $statuses[] = ['label' => 'Database', 'value' => 'Lỗi kết nối: ' . $e->getMessage(), 'ok' => false];
        }

        // Cache
        try {
            $key = 'smarterp_health_' . time();
            Cache::put($key, 'ok', 5);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);
            $driver = config('cache.default');
            $statuses[] = ['label' => 'Cache', 'value' => ucfirst($driver) . ($ok ? ' — Hoạt động' : ' — Lỗi đọc'), 'ok' => $ok];
        } catch (\Throwable) {
            $statuses[] = ['label' => 'Cache', 'value' => 'Lỗi cache', 'ok' => false];
        }

        // Queue
        $queueDriver = config('queue.default');
        $statuses[] = ['label' => 'Queue', 'value' => ucfirst($queueDriver), 'ok' => true];

        // Email
        $mailDriver = config('mail.default');
        $statuses[] = ['label' => 'Email', 'value' => strtoupper($mailDriver), 'ok' => true];

        // Storage
        try {
            Storage::put('_health.txt', 'ok');
            Storage::delete('_health.txt');
            $statuses[] = ['label' => 'Storage', 'value' => 'Local disk — Ghi được', 'ok' => true];
        } catch (\Throwable) {
            $statuses[] = ['label' => 'Storage', 'value' => 'Lỗi quyền ghi', 'ok' => false];
        }

        // Debug mode
        $debug = config('app.debug');
        $statuses[] = ['label' => 'Debug Mode', 'value' => $debug ? 'BẬT (không dùng production)' : 'TẮT', 'ok' => !$debug];

        // Maintenance
        $maintenance = file_exists(storage_path('framework/maintenance.php'));
        $statuses[] = ['label' => 'Maintenance', 'value' => $maintenance ? 'Đang bật' : 'Bình thường', 'ok' => !$maintenance];

        return response()->json($statuses);
    }

    // ── POST /api/settings/clear-cache ───────────────────────────
    public function clearCache(): JsonResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            return response()->json(['message' => 'Đã xóa toàn bộ cache!']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // ── POST /api/settings/optimize-db ───────────────────────────
    public function optimizeDb(): JsonResponse
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $dbKey  = 'Tables_in_' . config('database.connections.' . config('database.default') . '.database');
            $count  = 0;
            foreach ($tables as $row) {
                $table = $row->$dbKey ?? array_values((array)$row)[0];
                DB::statement("ANALYZE TABLE `{$table}`");
                $count++;
            }
            return response()->json(['message' => "Đã tối ưu {$count} bảng thành công!"]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Lỗi tối ưu DB: ' . $e->getMessage()], 500);
        }
    }

    // ── POST /api/settings/test-email ────────────────────────────
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Apply current DB mail settings to runtime config
        $this->applyMailConfig();

        try {
            Mail::raw(
                "✅ Email test từ SmartERP\n\nNếu bạn nhận được email này, cấu hình SMTP đang hoạt động đúng.\n\n— Hệ thống SmartERP",
                function ($m) use ($request) {
                    $m->to($request->email)
                        ->subject('[SmartERP] Email test — ' . now()->format('d/m/Y H:i'));
                }
            );
            return response()->json(['message' => 'Email test đã gửi thành công tới ' . $request->email]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gửi email thất bại: ' . $e->getMessage()], 422);
        }
    }

    // ── POST /api/settings/maintenance ───────────────────────────
    public function toggleMaintenance(Request $request): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);
        $enabled = $request->boolean('enabled');

        try {
            $maintenanceFile = storage_path('framework/maintenance.php');
            $publicJsonFile  = public_path('maintenance.json');

            if ($enabled) {
                $msg = Setting::get('maintenance.message', 'Hệ thống đang bảo trì...');

                // 1. Write Laravel maintenance file (blocks PHP requests with 503)
                $payload = [
                    'except'  => [],
                    'message' => $msg,
                    'retry'   => 60,
                    'refresh' => null,
                    'secret'  => null,
                    'status'  => 503,
                    'template'=> null,
                ];
                file_put_contents(
                    $maintenanceFile,
                    '<?php return ' . var_export($payload, true) . ';'
                );

                // 2. Write public/maintenance.json (frontend reads this static file)
                file_put_contents($publicJsonFile, json_encode([
                    'enabled' => true,
                    'message' => $msg,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                Setting::set('maintenance.enabled', '1', 'maintenance');
                return response()->json(['message' => 'Đã bật chế độ bảo trì!']);
            } else {
                // Remove both files
                if (file_exists($maintenanceFile)) unlink($maintenanceFile);
                if (file_exists($publicJsonFile))  unlink($publicJsonFile);

                Setting::set('maintenance.enabled', '0', 'maintenance');
                return response()->json(['message' => 'Đã tắt chế độ bảo trì!']);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // ── Private: apply DB mail config at runtime ─────────────────
    private function applyMailConfig(): void
    {
        $m = Setting::group('mail');
        $getValue = fn($k) => $m["mail.{$k}"] ?? null;

        Config::set('mail.default', $getValue('driver') ?: 'smtp');
        Config::set('mail.mailers.smtp.host',       $getValue('host'));
        Config::set('mail.mailers.smtp.port',       (int)($getValue('port') ?: 587));
        Config::set('mail.mailers.smtp.encryption', $getValue('encryption') ?: 'tls');
        Config::set('mail.mailers.smtp.username',   $getValue('username'));
        Config::set('mail.mailers.smtp.password',   $getValue('password'));
        Config::set('mail.from.address',            $getValue('from_address') ?: 'no-reply@smarterp.com');
        Config::set('mail.from.name',               $getValue('from_name')    ?: 'SmartERP');
    }
}
