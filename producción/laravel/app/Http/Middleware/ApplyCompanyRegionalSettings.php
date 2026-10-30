<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\RegionalFormatter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyCompanyRegionalSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = Schema::hasTable('companies')
            ? Company::query()->where('singleton_key', 'primary')->first()
            : null;
        $regional = RegionalFormatter::fromCompany($company);

        App::setLocale($regional->language);
        View::share('regional', $regional);

        return $next($request);
    }
}
