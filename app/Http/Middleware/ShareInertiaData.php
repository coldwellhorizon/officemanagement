<?php

namespace App\Http\Middleware;

use Inertia\Inertia;
use Laravel\Jetstream\Jetstream;
use Illuminate\Support\Facades\Session;

/**
 * Used by Jetstream to share data.
 * We needed to override this custom middleware in order to make
 * sure we pass the right data to the view.
 */
class ShareInertiaData
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        Inertia::share(array_filter([
            'jetstream' => function () use ($request) {
                return [
                    'flash' => $request->session()->get('flash', []),
                ];
            },
            'user' => function () use ($request) {
                if (! $request->user()) {
                    return;
                }

                return [];
            },
            'errorBags' => function () {
                return collect(optional(Session::get('errors'))->getBags() ?: [])->mapWithKeys(function ($bag, $key) {
                    return [$key => $bag->messages()];
                })->all();
            },
        ]));

        return $next($request);
    }
}