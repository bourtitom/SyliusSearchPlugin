# Testing

Use the requirements and guarded disposable setup in [DEVELOPMENT.md](DEVELOPMENT.md).
The generated test application is `tests/Application`.

## Commands

```bash
make test.all
```

This aggregates Composer validation, PHPStan, PHPMD, PHPUnit, JavaScript tests,
PHPSpec, PHP CS Fixer dry-run, YAML, schema, Twig and container validation.
Each is available separately as `make test.<name>`: `composer`, `phpstan`,
`phpmd`, `phpunit`, `javascript`, `phpspec`, `phpcs`, `yaml`, `schema`, `twig`,
`container`. PHP/container/template/schema checks require their installed
dependencies and, where applicable, the configured application/database.
`make test.javascript` uses Node's test runner without external services.

## Schema validation caveat

`make test.schema` first runs `app:search:validate-mappings` over **all mappings**.
It permits exactly one diagnosed upstream error: standalone
`Sylius\Component\Shipping\Model\ShipmentUnit#shipment` points through
`App\Entity\Shipping\Shipment#units` to `App\Entity\Order\OrderItemUnit`.
The exception applies only when `ShipmentUnit` is a mapped superclass with **no
concrete descendants** and the error matches exactly. It prints a warning;
every other mapping error fails the command. Native Doctrine database schema
validation then runs with `--skip-mapping`.

This is **not an unqualified all-mappings pass**. `make test.schema.upstream`
runs the unfiltered Doctrine validator and exposes the known failure. See
[`ValidateSearchMappingsCommand`](dist/src/Command/ValidateSearchMappingsCommand.php).

The harness also has a narrow [Gedmo TypeInfo fallback](dist/src/Compatibility/LogEntryTypeExtractor.php)
for the `Loggable|object` bound; API routes and validation remain enabled. It is
not shipped plugin functionality. Consumers on the same upstream combination
may encounter this error independently; see [the upgrade guide](UPGRADE-SYLIUS-2.md).

## Final-review verification checkpoint — 14 September 2026

These are independent QA results. The final audit found no remaining
high-confidence blocker and approved local owner review with the limitations
below. The owner subsequently approved creating the two PRs; merge and release
publication remain pending.

| Check | Completed evidence |
| --- | --- |
| Full `make install REBUILD_DATABASE=1` | passed the entire sequential harness: 44 migrations, 306 SQL queries, fixtures, all assets and reindex |
| Final `make test.all`, rerun after the Settings and Escape fixes | passed: Composer, PHPStan, PHPMD, container and remaining aggregate checks |
| PHPUnit | 19 tests, 60 assertions, including DTO/API-group contracts, indexing/subscriber failure handling, price bounds, currency labels and Settings |
| JavaScript | 5 tests, including synthetic constructor/focus/Escape coverage, races, short queries and failures |
| YAML / Twig / PHP style | 14 YAML files, 30 Twig templates, 161 PHP style files; zero errors |
| Mapping + database schema | all concrete mappings and DB synchronization passed; exact unused Shipping superclass warning retained as described above |
| Application + plugin builds | passed with Node 20.20.0 / Yarn 1.22.22 |
| Elasticsearch 7.16.3, ICU + phonetic | final DB/ES population: **87 products, 11 taxons** (not 21 taxons); temporary QA product absent and search queue empty |
| Separate Flex consumer | recipe 3.0 selected and container lint passed without `dist`; details in DEVELOPMENT.md |
| Sylius 2.0.18 / 2.1.16 | temporary dependency-probe dry-runs passed, **not runtime tests** |

The Escape fix prevents native `input[type=search]` handling from clearing the
query while closing suggestions. The JS tests, asset build, real-browser checks
and final complete suite all passed after that fix. Generated whitespace was also
corrected; the final repository diff check passed. Released migrations were not
changed; the 44 include the new test-only `dist/migrations/Version20260914091137.php`.

### Independent browser and admin QA

- **Chrome/Playwright desktop 1440×1000 and mobile 390×844:** search returned HTTP
  200 with one product; actual body width matched the viewport with no horizontal
  overflow and suggestions stayed in bounds. Real keyboard Escape hid the panel,
  set `aria-expanded=false` and preserved the query; blur/refocus reopened it,
  outside click hid it, and a short query cleared suggestions. No uncaught page
  errors. QA inspected temporary screenshots (not committed); only the dev
  toolbar overlay was noted. Earlier `Ethereal` checks also verified price,
  facets and custom short description.
- **Admin:** Settings index/edit returned 200 after the `tabler:search` icon fix.
  Attribute and option `searchWeight` changes `1 → 2 → 1` saved and reloaded;
  original flags were restored.
- **Channel Settings, UI-only:** disabling inheritance for Fashion Web Store,
  setting the first search limit to 15 and saving/reloading produced storefront
  “Show 15”. Add/Delete worked for all six product/taxon collections without
  LiveComponent 500 errors. Restoring inheritance restored “Show 9”; the final
  SQL check found only global rows. Native `LiveCollectionType` now supports the
  controls, inherited global values seed the form and saved overrides survive.
- **Price regressions:** six cases cover empty/open/reversed bounds, including
  the fixed minimum-only filter. Filter labels explicitly show indexed channel
  base currency, e.g. `USD ($)`; filter parameters stay in that currency while
  native product cards convert to the shopper's selected currency. The controller
  test with selected EUR/base USD passed.
- **Real asynchronous lifecycle:** a dedicated temporary product was created in
  admin, absent from ES before the search worker, then indexed after consumption.
  Admin rename updated the DB while ES retained the old name until the worker
  ran; search then found the new name. Admin deletion removed it from DB/ES and
  search returned zero. Its missing image rendered the local SVG fallback.
  Cleanup left 87 products/11 taxons and `async_search` empty. QA drained only
  that transport (22 previous messages plus test messages), not payment, order
  or mail queues.

### Remaining validation boundaries

Local QA does not prove a real Sylius 1 production-data migration, outer-transaction
rollback/commit behavior or runtime compatibility on other Sylius minors. The
best-effort publication gap and recovery procedure remain explicit in the
[upgrade guide](UPGRADE-SYLIUS-2.md#indexing-and-deployment-safety).

Local evidence does not imply a remote CI pass. Recipe CI pins the reviewed
companion commit from [recipe PR #20](https://github.com/monsieurbiz/symfony-recipes/pull/20)
on the owner's fork. The owner approved creating both PRs, but merge and release
publication remain pending. Independent QA/audit are complete; they are not a
transactional-durability guarantee.
