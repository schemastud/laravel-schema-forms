<?php

namespace Splicewire\SchemaForms;

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
 * Namespacing: each engine prefixes its private keywords `x-{prefix}-*` (composition
 * `swc`, knowledge `swk`, schema-forms `swf`). Notification routing is schema-forms' OWN
 * submission/outbox machinery — the `NotifyIntent` shape and delivery are this package's,
 * not a term every engine must mean identically — so per the tier doctrine (splicewire-app
 * composition-dialect-gaps issue 04) it takes this engine's `swf` prefix rather than sitting
 * in the unprefixed base commons. (An earlier revision argued the opposite — that notification
 * is cross-cutting — and left it unprefixed; that was reconsidered under the rule of thumb:
 * unprefixed = must mean the same thing in every engine/host; owned-by-one-engine takes a prefix.)
 */
class Keywords
{
    public const Notify = 'x-swf-notify';

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
