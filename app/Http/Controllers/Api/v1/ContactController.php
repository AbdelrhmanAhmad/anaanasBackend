<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ContactFormGuardService;
use App\Services\ContactRequestService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(
        Request $request,
        ContactRequestService $contactRequestService,
        ContactFormGuardService $contactFormGuard,
    ) {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        if ($contactFormGuard->isSuspicious($request)) {
            return response()->json([
                'success' => true,
                'message' => __('contact.submitted'),
            ], 201);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $contactRequest = $contactRequestService->submit(
            $request,
            $request->user(),
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => __('contact.submitted'),
            'data' => [
                'id' => $contactRequest->id,
            ],
        ], 201);
    }
}
