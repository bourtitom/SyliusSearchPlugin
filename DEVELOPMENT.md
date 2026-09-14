# Development

## Local application

Use PHP 8.3+ for the current Sylius 2.2.9 application, Composer, Symfony CLI,
Docker and Docker Compose. Asset builds were verified with Node **20.20.0** and
Yarn **1.22.22** (not Node 14).

`make help` lists the project commands. `make install REBUILD_DATABASE=1` runs
application setup, platform startup, Sylius setup and index population sequentially.
**Only run it with authorization against a disposable database**: it drops and
recreates that database, runs migrations, then loads fixtures. Confirm database
and Elasticsearch targets with the environment owner first. The guard does not
make the earlier setup steps read-only.

The generated consumer is `tests/Application`. `tests/setup-application.php` applies `dist/`,
excluding `.env*` and historical `dist/src/Migrations`. The repository setup and
recipe-endpoint scripts do not read or write `.env*`; provide environment values
through your normal local setup. Root and application `node_modules` are independent.

Useful non-resetting commands:

```bash
make up
make plugin.assets
make yarn.install
make sylius.assets
make sylius.cache.clear
make test.all
make stop
```

`make yarn.install` builds both application and plugin assets. `make es.reindex`
rebuilds indexes; use only against the intended test service. Do not use
`make reset` as a routine update: it removes the generated app and dependencies.
See [TESTING.md](TESTING.md) for verification scope and harness exceptions.

## Testing the unpublished recipe

The independent recipe is `monsieurbiz/sylius-search-plugin/3.0` on the external
recipes checkout's `upgrade-sylius-search-plugin-2.x` branch. It is not published.
Do not use the `dist` overlay to prove recipe installation.

1. From the plugin root, generate the endpoint with `make recipe.endpoint` if
   `symfony-recipes` is a sibling checkout. Otherwise run
   `php tests/recipe-endpoint.php /path/to/symfony-recipes/monsieurbiz/sylius-search-plugin/3.0`.
2. Serve the generated `tests/RecipeEndpoint` directory with Symfony CLI on an
   available port, binding only to loopback:
   `symfony server:start --dir=tests/RecipeEndpoint --listen-ip=127.0.0.1 --port=8002 --no-tls -d`.
   Replace `8002` with your available port and use that same port below. Flex
   requires HTTP(S); a `file://` endpoint does not work.
3. In a **throwaway Sylius 2 consumer** (for example `tests/RecipeApplication`),
   configure the plugin checkout as a Composer path repository and put the local
   endpoint first, preserving the Sylius, Monsieur Biz and default endpoints:

   ```bash
   composer config repositories.search '{"type":"path","url":"../.."}'
   composer config extra.symfony.allow-contrib true
   composer config --no-plugins --json extra.symfony.endpoint '["http://127.0.0.1:8002/index.json","https://api.github.com/repos/Sylius/SyliusRecipes/contents/index.json?ref=flex/main","https://api.github.com/repos/monsieurbiz/symfony-recipes/contents/index.json?ref=flex/master","flex://defaults"]'
   composer config secure-http false
   composer require monsieurbiz/sylius-search-plugin:dev-upgrade-2.x --no-scripts
   composer recipes:install monsieurbiz/sylius-search-plugin --force -v
   composer config --unset secure-http
   php bin/console lint:container
   ```

   The relative path above assumes `tests/RecipeApplication`. The temporary
   project-level `secure-http` exception is solely for this localhost endpoint;
   never set it globally or disable TLS verification. Restore it even if install
   fails, and remove the local endpoint when finished. `--force` is appropriate
   only for this disposable consumer.
4. Inspect `symfony.lock`: confirm recipe **3.0**, its artifact reference, modern
   Search imports and `AutoMapper\Symfony\Bundle\AutoMapperBundle`. If the old
   recipe was applied first, review/remove its stale Jane bundle entry and old
   Search wiring before linting. Do not copy `dist` to make the check pass.
5. Stop the endpoint with `symfony server:stop --dir=tests/RecipeEndpoint`.

The reported independent Flex install selected 3.0, artifact ref
`8a987622c292645b2c6095014f1654ac84ad440b`, and passed container lint without
`dist`; stale Jane registration from an initial old recipe was removed manually.
The generator hashes recipe content, so later changes produce a different ref.
This is local installation evidence, not publication or full consumer runtime QA.

The full local install and independent QA are recorded in [TESTING.md](TESTING.md).
Recipe CI pins the reviewed companion commit on `bourtitom/symfony-recipes`
while [recipe PR #20](https://github.com/monsieurbiz/symfony-recipes/pull/20)
awaits upstream review. Switch that reference to upstream after the recipe is
merged. Local checks do not imply a remote CI pass. The owner authorized the two
PRs after independent QA/audit; merging and release publication remain separate.
