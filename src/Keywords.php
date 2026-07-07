<?php

namespace Rushing\SchemaForms;

/**
 * The JSON-Schema extension keywords THIS package owns.
 *
 * Ownership doctrine (the JSON-LD `@context` model): the base leaf
 * (`rushing/laravel-json-reference`) owns the small cross-engine set (`@id`,
 * `x-dereference`); every other package owns and guards its OWN keywords locally.
 * There is no central keyword list to curate — a keyword is legitimate because
 * some package declares it here, and drift is caught by each package asserting its
 * shipped schemas use only `base ∪ own` (see the KeywordOwnership test).
 *
 * Namespacing: engines prefix their private keywords `x-{prefix}-*` (composition
 * `swc`, knowledge `swk`). A genuinely cross-cutting term stays unprefixed (blank
 * namespace) so it reads the same everywhere — but it must still be DECLARED by its
 * owner, which is what this class does for `x-notify`. Notification routing is a
 * cross-app concern, not host-private, so unprefixed-but-declared is correct.
 */
class Keywords
{
    public const Notify = 'x-notify';

    /**
     * Every `x-` keyword this package owns.
     *
     * @return list<string>
     */
    public static function owned(): array
    {
        return [self::Notify];
    }
}
