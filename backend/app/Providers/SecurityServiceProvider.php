<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\BruteForceProtection;
use App\Http\Middleware\RequestSigning;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateUserAgent;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCustomValidators();
        $this->registerBladeDirectives();
    }

    private function registerCustomValidators(): void
    {
        Validator::extend('no_scripts', function ($attribute, $value) {
            if (!is_string($value)) {
                return true;
            }
            return !preg_match('/<script[\s>]/i', $value);
        }, 'The :attribute may not contain scripts.');

        Validator::extend('no_sql', function ($attribute, $value) {
            if (!is_string($value)) {
                return true;
            }
            return !preg_match('/(\bUNION\b.*\bSELECT\b|\bSELECT\b.*\bFROM\b|\bDROP\b.*\bTABLE\b|\bDELETE\b.*\bFROM\b)/i', $value);
        }, 'The :attribute contains invalid characters.');

        Validator::extend('safe_string', function ($attribute, $value) {
            if (!is_string($value)) {
                return true;
            }
            $blocked = [
                '<script', 'javascript:', 'onerror=', 'onclick=',
                '<?php', '<?=', '<%', '<\$',
                '../../', '..\\..\\',
            ];
            foreach ($blocked as $pattern) {
                if (stripos($value, $pattern) !== false) {
                    return false;
                }
            }
            return true;
        }, 'The :attribute contains unsafe content.');
    }

    private function registerBladeDirectives(): void
    {
        Blade::directive('sanitize', function ($expression) {
            return "<?php echo e(strip_tags($expression)); ?>";
        });

        Blade::directive('csrf', function () {
            return "<?php echo csrf_field(); ?>";
        });
    }
}
