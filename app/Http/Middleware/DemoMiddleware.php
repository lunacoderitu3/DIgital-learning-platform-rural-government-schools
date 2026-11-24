<?php

namespace App\Http\Middleware;

use App\Services\CachingService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class DemoMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $school_database_name = Session::get('school_database_name');
        if ($school_database_name) {
            DB::setDefaultConnection('school');
            Config::set('database.connections.school.database', $school_database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');
        } else {
            DB::purge('school');
            DB::connection('mysql')->reconnect();
            DB::setDefaultConnection('mysql');
        }

        if (Auth::check() && Auth::user()->language) {
            app()->setLocale(Auth::user()->language);
        } elseif (Session::has('locale')) {
            app()->setLocale(Session::get('locale'));
        }
        $exclude_uri = array(
            '/login',
            '/api/student/login',
            '/api/parent/login',
            '/api/teacher/login',
            '/contact',
            '/api/student/submit-online-exam-answers',
            '/students/generate-id-card',
            '/certificate',
            '/certificate/staff-certificate',
            '/staff/generate-id-card',
            '/session-year/set-session-year',
            '/route-vehicle/user/attendance-report',
            '/api/certificate/assign',
            '/api/certificate/generate'
        );
        $excludeEmails = [
            "info@crestwoodacademy.com",
            "alex.johnson@elementary.org",
            "jamie.smith@gmail.com",
            "thor@gmail.com",
            "2024-2571",
            "subhamsharma5961@gmail.com"
        ];

        // Demo school mode
        $cache = app(CachingService::class);
        $systemSettings = $cache->getSystemSettings();
        $demoSchoolMode = $systemSettings['demo_school_mode'] ?? 0;
        if ((Auth::user() && Auth::user()->school && Auth::user()->school->domain == 'demo') && !$request->isMethod('get') && !in_array($request->getRequestUri(), $exclude_uri) && $demoSchoolMode) {
            $message = trans("this_is_not_allowed_in_the_demo_version");

            $routeName = $request->route()?->getName();
            $excludeRoutes = [
                'certificate-template.store',
                'certificate-template.update',
            ];
            if ($routeName && in_array($routeName, $excludeRoutes)) {
                return redirect()->route('certificate-template.index')->with('error', $message);
            }

            // If API / AJAX request
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => true,
                    'message' => $message,
                    'code'    => 112
                ], 403);
            }

            // Normal web request → redirect back
            return redirect()->back()->with('error', $message);
        }

        if (env('DEMO_MODE') && !$request->isMethod('get') && Auth::user() && !in_array(Auth::user()->email, $excludeEmails) && !in_array($request->getRequestUri(), $exclude_uri)) {
            $excluded_ips = ['103.30.227.53', '103.30.227.54', '103.30.226.49']; // replace with the IPs you want to exclude
            $test_school_panel = ['jamie.smith@gmail.com', 'thor@gmail.com', '2024-2571', 'subhamsharma5961@gmail.com'];  // Add testing school user email
            if (!in_array($request->ip(), $excluded_ips)) {
                // return response()->json(array(
                //     'error'   => true,
                //     'message' => "this_is_not_allowed_in_the_demo_version",
                //     'code'    => 112
                // ));

                $routeName = $request->route()?->getName();
                $excludeRoutes = [
                    'certificate-template.store',
                    'certificate-template.update',
                ];
                $message = trans("this_is_not_allowed_in_the_demo_version");
                if ($routeName && in_array($routeName, $excludeRoutes)) {
                    return redirect()->route('certificate-template.index')->with('error', $message);
                }



                // If API / AJAX request
                if ($request->expectsJson()) {
                    return response()->json([
                        'error'   => true,
                        'message' => $message,
                        'code'    => 112
                    ], 403);
                }

                // Normal web request → redirect back
                return redirect()->back()->with('error', $message);
            }
        }
        return $next($request);
    }
}
