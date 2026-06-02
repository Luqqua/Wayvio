<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();
        $this->warnIfLegalProviderStammdatenMissing();
        Validator::extend('isunique', function ($attribute, $value, $parameters, $validator) {
            $value = strtolower($value);
            $query = DB::table($parameters[0])->whereRaw("LOWER({$attribute}) = ?", [$value]);

            if (isset($parameters[1])) {
                $query->where($parameters[1], '!=', $parameters[2]);
            }

            return $query->count() === 0;
        });
        Validator::extend('exturl', function ($attribute, $value, $parameters, $validator) {
            $allowed_schemes = ['http', 'https', 'mailto', 'tel'];
            return in_array(parse_url($value, PHP_URL_SCHEME), $allowed_schemes, true);
        });
        View::addNamespace('blocks', base_path('blocks'));
    }

    private function warnIfLegalProviderStammdatenMissing(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $missing = array_filter([
            'LEGAL_PROVIDER_NAME'    => trim((string) config('legal.provider.name', '')),
            'LEGAL_PROVIDER_ADDRESS' => trim((string) config('legal.provider.address', '')),
            'LEGAL_PROVIDER_EMAIL'   => trim((string) config('legal.provider.email', '')),
        ], fn(string $v) => $v === '');

        if (!empty($missing)) {
            Log::warning('DSGVO: Betreiberstammdaten unvollständig. Bitte folgende ENV-Variablen setzen: ' . implode(', ', array_keys($missing)) . '. Ohne diese Werte enthalten Impressum und Datenschutzerklärung ungültige Platzhalter.');
        }
    }
}
