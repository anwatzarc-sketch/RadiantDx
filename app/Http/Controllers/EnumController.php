<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Enums\EnumRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the shared controlled vocabularies to the browser.
 *
 * This exists so a vocabulary has exactly one definition. Without it every
 * Blade view that needs a speciality list grows its own copy of that list, and
 * they drift.
 *
 * The name in the URL is resolved through {@see EnumRegistry}'s allow-list, so
 * it is never used to construct a class name. An unpublished name is a plain
 * 404.
 *
 * These are vocabularies, not records: nothing here is patient identifying or
 * facility specific. It still sits behind `auth` because an unauthenticated
 * visitor has no form to populate.
 */
class EnumController extends Controller
{
    /** Every published vocabulary name. */
    public function index(): JsonResponse
    {
        return response()->json([
            'enums' => EnumRegistry::names(),
        ]);
    }

    /**
     * One vocabulary.
     *
     * `?parent=` narrows a dependent vocabulary to one parent, which is what
     * the dependent SubSpeciality select uses.
     *
     * `?active=0` includes retired values, for screens that must resolve a
     * historical record rather than offer a choice.
     */
    public function show(Request $request, string $enumName): JsonResponse
    {
        $catalogue = EnumRegistry::catalogueFor($enumName);

        if ($catalogue === null) {
            throw new NotFoundHttpException("Unknown enum [{$enumName}].");
        }

        $includeInactive = $request->boolean('include_inactive');
        $parent = $request->query('parent');

        $values = array_values(array_filter(
            $catalogue,
            static function (array $entry) use ($includeInactive, $parent): bool {
                if (! $includeInactive && ! $entry['active']) {
                    return false;
                }

                return ! (is_string($parent) && $parent !== '' && $entry['parent'] !== $parent);
            },
        ));

        return response()->json([
            'name' => $enumName,
            'values' => $values,
        ], 200, [
            // Vocabularies change only on deploy, but they are per-user only in
            // the sense that they need a session; keep them off shared caches.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
