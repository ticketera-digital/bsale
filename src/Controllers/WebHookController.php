<?php

namespace ticketeradigital\bsale\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ticketeradigital\bsale\Events\WebhookHit;

class WebHookController extends Controller
{
    public function __invoke(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:post,put,delete',
            'topic' => 'required|string',
            'resource' => 'required|string',
            'resourceId' => 'string',
            'cpnId' => 'integer',
            'send' => 'nullable|numeric',
        ]);

        Log::debug('Bsale WebhookController hit', $request->only(['topic', 'resourceId']));

        if ($validator->fails()) {
            Log::critical('Webhook validation failed', $request->all());

            return response()->json($validator->errors(), 422);
        }

        $others = $request->except(['action', 'topic', 'resourceId', 'resource']);

        $webHooksEnabled = config('bsale.enableWebHooks');
        WebhookHit::dispatchIf(
            $webHooksEnabled,
            $request->action,
            $request->topic,
            $request->resourceId,
            $request->resource,
            $others
        );

        return response()->json(['success' => $webHooksEnabled]);

    }
}
