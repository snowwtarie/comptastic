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
        return new SettingsResource($request->user()->settings);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $request->user()->settings()->update($request->validated());

        return new SettingsResource($request->user()->settings->fresh());
    }
}
