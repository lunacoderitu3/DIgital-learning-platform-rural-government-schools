<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //        $this->renderable(function (NotFoundHttpException $e, $request) {
        //            if ($request->is('api/*')) {
        //                return response()->json([
        //                    'message' => 'Record not found.'
        //                ], 404);
        //            }
        //        });
        //        $this->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        //            if ($request->is('api/*')) {
        //                return response()->json([
        //                    'message' => 'Not authenticated'
        //                ], 401);
        //            }
        //        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Schema::defaultStringLength(191);
        Schema::useNativeSchemaOperationsIfPossible();

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {

                // Bearer token authentication
                $openApi->components->securitySchemes['bearer'] =
                    SecurityScheme::http('bearer');

                // School code header
                $openApi->components->securitySchemes['schoolCode'] =
                    SecurityScheme::apiKey('header', 'school-code');

                // Both are required
                $openApi->security[] = new SecurityRequirement([
                    'bearer' => [],
                    'schoolCode' => [],
                ]);
            });

        //        $this->app['validator']->extend('unique_for_school', function ($attribute, $value, $parameters) {
        //            // Extract and validate the parameters from the rule syntax.
        //            [$table, $column] = $parameters;
        //            $ignoreID = $parameters[2] ?? null;
        //            $schoolID = $parameters[3] ?? null;
        //            // Create an instance of your CustomRule and call the passes method.
        //            return (new uniqueForSchool($table, $column, $ignoreID, $schoolID))->passes($attribute, $value);
        //        });

        //        $this->app['validator']->replacer('unique_for_school', function ($message, $attribute, $rule, $parameters) {
        //            return str_replace(':attribute', $attribute, $rule->message());
        //        });
    }
}
