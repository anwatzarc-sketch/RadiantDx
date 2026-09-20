<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Removes any attempt by a client to nominate who performed an action.
 *
 * Actor identity is resolved server-side from the session by
 * {@see \App\Services\AuthenticatedStaffResolver}. A request that names an
 * actor is either a mistake or an attack; either way the value must not
 * survive to validation, let alone reach a model.
 *
 * Stripping happens in one place rather than in each controller so that a
 * workflow added later cannot forget it. Fields are removed rather than
 * rejected: failing the request would tell an attacker which names are
 * interesting, and a legitimate client never sends these at all.
 *
 * The catalogue itself lives in {@see ClientActorFields}.
 */
trait IgnoresClientActorFields
{
    /** Strips the actor fields. Called from prepareForValidation(). */
    protected function ignoreClientActorFields(): void
    {
        foreach (ClientActorFields::ALL as $field) {
            $this->request->remove($field);
            $this->query->remove($field);
        }
    }
}
