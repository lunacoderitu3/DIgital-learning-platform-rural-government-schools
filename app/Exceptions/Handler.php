<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    function render($request, Throwable $exception)
    {
        // A QR attendance refusal is a business outcome, not a crash. Return the
        // user-facing message and the branchable code instead of a 500.
        if ($exception instanceof QrAttendanceException) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'      => true,
                    'message'    => $exception->getMessage(),
                    'error_code' => $exception->errorCode,
                    'code'       => 400,
                ], 200);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($this->isHttpException($exception)) {
            switch ($exception->getCode()) {

                //not found
                case 404:
                    return \Response::view('errors.404',[], 404);
                    break;

                default:
                    return $this->renderHttpException($exception);
                    break;
            }
        } else {
            return parent::render($request, $exception);
        }
    }
    
}
