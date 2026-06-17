---
name: solid-clean-code
description: "Apply when writing, reviewing, refactoring, or finalizing any code change to validate it is clean and SOLID-aligned before it is considered acceptable. Use as an acceptance gate: run changed files through the checklist and only accept code that passes the MUST items. Covers Single Responsibility, Open/Closed, Liskov, Interface Segregation, Dependency Inversion, plus clean-code rules (naming, small functions, DRY, dead code, single source of truth, magic values, guard clauses) for the Laravel backend and the React admin. Pair with laravel-best-practices for framework specifics."
license: MIT
metadata:
  author: zaina
---

# SOLID & Clean Code — Acceptance Gate

Use this to **validate** a change before calling it done. A change is **acceptable**
only when every **MUST** in the checklist passes. SHOULD items are strong recommendations.

> **Consistency first.** Match the patterns the surrounding code already uses. If a file
> already follows a convention, follow it — don't introduce a second way just to satisfy a
> rule here. These are defaults for new code and a lens for refactors, not license to churn.

---

## SOLID — what to enforce, the smell, the fix

### S — Single Responsibility
A class/method/component has one reason to change.

- **Smell:** a controller action that validates, computes prices, mutates several tables,
  charges payment, and sends SMS in one method.
- **Fix:** controllers stay thin — delegate business logic to a Service/Action
  (e.g. `App\Service\BookingService`), one filter per class
  (`App\Filters\Worker\*`), one Form Request per action.
- **Backend rule of thumb:** controller methods under ~10–15 lines; extract beyond that.

### O — Open/Closed
Open for extension, closed for modification.

- **Good example in this repo:** the filter pipeline. Adding a filter = a new
  `app/Filters/<Domain>/<Name>Filter.php` added to the `->through([...])` array; the base
  `Filter` and the controller loop never change. See the `query-pipeline-filters` skill.
- **Smell:** editing a growing `if/elseif` (or `match`) block every time a new variant
  appears (payment methods, notification channels, filters).
- **Fix:** strategy/pipeline classes resolved by key; add a class, don't edit the switch.

### L — Liskov Substitution
A subtype must honor its base's contract.

- **Smell:** a subclass that throws on a method the base supports, or strengthens
  preconditions (a `Filter` subclass that needs extra setup the base doesn't).
- **Fix:** every `Filter` subclass works anywhere the base is used — same `handle()`
  contract, only `key()`/`apply()` differ. Don't narrow return types or add required state.

### I — Interface Segregation
Many small, focused contracts over one fat one.

- **Good example:** separate `StoreWorkerRequest` / `UpdateWorkerRequest` instead of one
  request with conditional rules; focused API Resources per model.
- **Smell:** an interface (or base class) with methods half the implementers leave empty;
  a "god" service every controller depends on for unrelated reasons.
- **Fix:** split by client need; depend only on what you use.

### D — Dependency Inversion
Depend on abstractions; inject dependencies.

- **Good example:** `MediaService`/`BookingService` injected via the constructor.
- **Smell:** `app(Foo::class)` / `resolve()` / `new Foo()` for a service inside a class;
  a class hard-wired to a concrete external client (payment gateway, SMS) it can't fake.
- **Fix:** constructor (or method) injection; code to an interface at system boundaries
  (gateways, channels, external APIs) and bind it in a service provider.

---

## Clean code — non-negotiables

- **Meaningful names.** Intent-revealing: `isRegisteredForDiscounts`, not `flag`/`tmp`.
  Booleans read as predicates. No abbreviations that aren't domain terms.
- **Small functions, one level of abstraction.** Extract a private method or Action when a
  method does several things or nests deeply.
- **Guard clauses over nesting.** Return/throw early; avoid `else` after a `return`.
- **DRY — extract duplication.** Identical logic in two places is a defect waiting to
  diverge. *Real example:* the worker conflict-check + `lockForUpdate` block is duplicated
  across `BookingService::createBooking` and `rescheduleBooking` — that belongs in one
  private method. Extract on the second occurrence.
- **Single source of truth.** Don't store the same fact two ways. *Real example:* a booking's
  worker exists both as `bookings.worker_id` and the `booking_assignments` pivot — pick one.
- **No dead or commented-out code.** Delete it (git remembers). Commented-out guards are
  dangerous — e.g. an ownership check left commented becomes an IDOR. If code is disabled,
  it must be removed or gated, never left as a comment.
- **No magic values.** Status strings (`'Upcoming'`, `'active'`, `'cascade_hide'`) and
  numeric thresholds become enums/constants/config, not literals scattered across files.
- **Consistent contracts.** One response envelope, one validation-error shape, one naming
  scheme across siblings. (This repo: the `ApiResponseTrait` envelope + `params` validation
  shape — match it everywhere.)
- **Comments explain *why*, not *what*.** Prefer self-documenting code; comment only
  non-obvious intent or config.
- **Handle errors deliberately.** No empty `catch`; no stub methods that silently no-op
  (e.g. an empty `destroy()` that returns 200 while deleting nothing).

## React admin (clean code)

- **Component SRP:** a component renders; move data-fetching/derivation into hooks
  (`useQuery`) or small helpers. Split a component that does fetching + filtering +
  table + modal into composable pieces when it grows.
- **No duplicated logic across components:** extract shared logic into a custom hook.
- **Derived state, not duplicated state:** compute from source (e.g. `activeFilterCount`)
  instead of storing a copy you must keep in sync.
- **i18n, not hardcoded strings:** user-facing text via translation keys (both `ar` + `en`).
- **Stable keys, no inline mega-handlers:** extract complex handlers; keys from stable ids.

---

## Acceptance checklist (the gate)

A change is **acceptable** only if all MUST items hold for every changed file.

**MUST**
- [ ] Each class/method/component has a single, nameable responsibility.
- [ ] No new `if/elseif`/`switch` that a new variant would force you to edit — use
      strategy/pipeline classes where variants are expected.
- [ ] Dependencies are injected (no `app()`/`new` for services inside classes); boundaries
      depend on interfaces.
- [ ] No copy-pasted logic — shared logic is extracted (DRY).
- [ ] No dead code, no commented-out code, no empty `catch`/no-op stubs.
- [ ] No security/correctness guard left disabled or commented out.
- [ ] No magic strings/numbers for statuses, types, or thresholds — use enums/constants/config.
- [ ] Names reveal intent; functions are small with guard clauses.
- [ ] Matches sibling conventions (response envelope, validation shape, naming).
- [ ] Backend: thin controllers (logic in Services/Actions/filters); typed params + return
      types; `$fillable`/`$guarded` set; eager-load to avoid N+1.
- [ ] Frontend: user-facing text via i18n (ar + en); component does one thing.
- [ ] Covered by a test that would fail if the behavior regressed.

**SHOULD**
- [ ] Methods ≤ ~15 lines; classes cohesive.
- [ ] Value objects/DTOs or typed arrays instead of loose array shapes across boundaries.
- [ ] Single source of truth for every fact (no denormalized duplicates without reason).
- [ ] Pure/derived state on the frontend instead of duplicated state.

If a MUST fails: fix it, or explicitly flag it as a known, accepted exception with a reason —
never silently pass.

## How to apply

1. List the changed files (the diff). For each, walk the SOLID section, then the clean-code
   non-negotiables.
2. Run the MUST checklist; treat any failure as blocking until fixed or explicitly accepted.
3. For Laravel specifics (N+1, validation, routing, caching), defer to
   `laravel-best-practices`. For filterable listings, use `query-pipeline-filters`.
4. Prefer fixing a smell at its second occurrence (Rule of Three for premature abstraction —
   don't over-engineer a one-off).
