<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class PlatformMaintenanceWebController extends Controller
{
    public function runMigrations(): RedirectResponse
    {
        if (! config('salesapp.allow_web_migrations', true)) {
            abort(403, 'Web migrations are disabled. Set SALESAPP_ALLOW_WEB_MIGRATIONS=true in .env if appropriate.');
        }

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['migrate' => $e->getMessage()]);
        }

        $output = Artisan::output();
        $log = strlen($output) > 12000 ? substr($output, 0, 12000)."\n… (truncated)" : $output;

        if ($exitCode !== 0) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['migrate' => 'The migrate command exited with code '.$exitCode.'.'])
                ->with('migration_log', $log);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Database migrations completed successfully.')
            ->with('migration_log', $log);
    }
}
