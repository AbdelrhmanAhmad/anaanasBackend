<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {



        $logFun = function (string $type, \Throwable $e): void {
            $log = \Illuminate\Support\Facades\Log::channel('exceptions');
            $request = request();
            $data =  [
                'url' => $request?->url(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'userId' => auth()->id() ?? null,
                'requestMethod' => $request?->method(),
                'requestData' => $request?->all(),
                'requestHeader' => $request?->header(),
                'requestUser' => $request?->user(),
                'requestIp' => $request?->ip(),
//                'Trace' => $e->getTrace(),
            ]  ;
            ;
            switch ($type) {
                case  "debug":      $log->debug( $e->getMessage() , $data); break;
//                    case  "info":      $log->info($data); break;
                case  "emergency":      $log->emergency( $e->getMessage() , $data); break;
//                    case  "log":      $log->log($data); break;
                case  "alert":      $log->alert( $e->getMessage() , $data); break;
                case  "critical":      $log->critical( $e->getMessage() , $data); break;
                case  "error":      $log->error( $e->getMessage() , $data); break;
                case  "warning":      $log->warning( $e->getMessage() , $data); break;
                case  "notice":      $log->notice( $e->getMessage() , $data); break;
                default:
                    $log->info( $e->getMessage() , $data);
                    break;
            }
        };
        // if (\request()->expectsJson()) {

        //     $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) use ($logFun) {
        //         $logFun("debug" ,$e );
        //         return returnInvalidate($e->validator);
        //     });

        //     $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request)use ($logFun) {
        //         $logFun("alert" ,$e );
        //         return UNAUTHORIZED();
        //     });
        //     $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) use ($logFun){
        //         $logFun("critical" ,$e );
        //         return ThrottleLimit($e->getHeaders());
        //     });
        //     $exceptions->render(function (NotFoundHttpException $e, Request $request)  use ($logFun){
        //         $logFun("warning" ,$e );
        //         return returnError(__('messages.request_not_found'), 404, 404);
        //     });

        //     $exceptions->render(function (\Illuminate\Database\QueryException $e) use ($logFun) {
        //         $logFun("error" ,$e );
        //         return returnError("حدث خطا ما", 404, 404);
        //     });
        //     $exceptions->render(function (Exception $e, Request $request)  use ($logFun){
        //         $logFun("error" ,$e );
        //         return returnError($e->getMessage());
        //     });


        // }





        $exceptions->report(function (\Throwable $e) use ($logFun): void {
            // if (config('app.env') === 'production') {
            //     try {
            //         \Illuminate\Support\Facades\Mail::to(['abdelrhmanmt1@gmail.com'])
            //             ->cc(['abdelrhmanmt1996@outlook.com'])
            //             ->send(new \App\Mail\ExceptionMail([
            //                 'message' => $e->getMessage(),
            //                 'File' => $e->getFile(),
            //                 'line' => $e->getLine(),
            //                 'url' => request()?->url(),
            //             ]));
            //     } catch (\Throwable $mailException) {
            //         \Illuminate\Support\Facades\Log::channel('exceptions')->error('Failed to send exception mail', [
            //             'original' => $e->getMessage(),
            //             'mail_error' => $mailException->getMessage(),
            //         ]);
            //     }
            // }
            $logFun('error', $e);
        });


        // Ensure API requests never redirect to a "login" route
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }
            return null;
        });
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Don't redirect API routes to login

        $middleware->api(append: [
            \App\Http\Middleware\SetLocaleMiddleware::class,
            //  VerifyRequestSignatureMiddleware::class,
            // AddResponseSignatureMiddleware::class,
        ]);



        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })->create();
