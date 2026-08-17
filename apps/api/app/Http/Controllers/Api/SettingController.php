<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(Request $request)
    {
        $settings = $request->user()->settings()->firstOrCreate([], [
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);

        // firstOrCreate() marks the model as "recently created", which makes
        // Laravel's resource response default to a 201 status. Reading
        // settings should always be a 200, whether or not a default row
        // had to be created on the fly.
        $settings->wasRecentlyCreated = false;

        return new SettingsResource($settings);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $settings = $request->user()->settings()->updateOrCreate([], $request->validated());

        // Same reasoning as above: updateOrCreate() can create the row, but
        // PUT /api/settings should consistently respond with 200.
        $settings->wasRecentlyCreated = false;

        return new SettingsResource($settings);
    }
}
