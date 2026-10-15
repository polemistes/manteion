<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * /.well-known/assetlinks.json: tells Android that the Manteion app may
 * open www.manteion.no links itself (App Links), for the app signed with
 * the keys whose SHA-256 fingerprints are in ANDROID_APP_CERT_SHA256.
 * Without any, there is nothing to vouch for.
 */
class AndroidAssetLinksController extends Controller
{
    public const PACKAGE_NAME = 'no.manteion.app';

    public function __invoke(): JsonResponse
    {
        $fingerprints = array_values(array_filter(array_map(
            fn (string $fingerprint) => strtoupper(trim($fingerprint)),
            explode(',', (string) config('services.android.certificate_fingerprints')),
        )));

        abort_if($fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => self::PACKAGE_NAME,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }
}
