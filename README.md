[![Banner of Sylius Search plugin](docs/images/banner.jpg)](https://monsieurbiz.com/agence-web-experte-sylius)

<h1 align="center">Search</h1>

[![Search Plugin license](https://img.shields.io/github/license/monsieurbiz/SyliusSearchPlugin?public)](https://github.com/monsieurbiz/SyliusSearchPlugin/blob/master/LICENSE.txt)
[![Tests Status](https://img.shields.io/github/actions/workflow/status/monsieurbiz/SyliusSearchPlugin/tests.yaml?branch=master&logo=github)](https://github.com/monsieurbiz/SyliusCmsPagePlugin/actions?query=workflow%3ATests)
[![Recipe Status](https://img.shields.io/github/actions/workflow/status/monsieurbiz/SyliusSearchPlugin/recipe.yaml?branch=master&label=recipes&logo=github)](https://github.com/monsieurbiz/SyliusCmsPagePlugin/actions?query=workflow%3ASecurity)
[![Security Status](https://img.shields.io/github/actions/workflow/status/monsieurbiz/SyliusSearchPlugin/security.yaml?branch=master&label=security&logo=github)](https://github.com/monsieurbiz/SyliusCmsPagePlugin/actions?query=workflow%3ASecurity)


A search plugin for Sylius using [Elastically](https://github.com/jolicode/elastically) and [Jane](https://github.com/janephp/janephp).

## Compatibility

This branch prepares **Search 3.0 for Sylius 2**; it is not a published release.

| Scope | Compatibility / evidence |
| --- | --- |
| Composer constraints | Sylius `~2.0`, PHP `^8.2`, AutoMapper `~9.5.1` |
| Current local runtime | Sylius 2.2.9 (requires PHP 8.3+), Symfony 7.4, ORM 3.7.1 |
| Dependency-only probes | Sylius 2.0.18 and 2.1.16 dry-runs passed; not full runtime verification |

See [the migration guide](UPGRADE-SYLIUS-2.md) and [verification status](TESTING.md).

## Installation

For a preview, with the local checkout configured as a Composer path repository:

```bash
composer require monsieurbiz/sylius-search-plugin:dev-upgrade-2.x
```

After release, use `monsieurbiz/sylius-search-plugin:^3.0` instead. The 3.0
recipe is **not published**: follow the manual wiring below even with Flex.
Do not use the old Search 2.0 recipe as Sylius 2 wiring. Local recipe testing is
documented in [DEVELOPMENT.md](DEVELOPMENT.md#testing-the-unpublished-recipe).

<details>
<summary>Manual installation (required until the 3.0 recipe is published)</summary>
<p>

Change your `config/bundles.php` file to add this line for the plugin declaration:
```php
<?php

return [
    //..
    MonsieurBiz\SyliusSearchPlugin\MonsieurBizSyliusSearchPlugin::class => ['all' => true],
    AutoMapper\Symfony\Bundle\AutoMapperBundle::class => ['all' => true],
];
```

Create the config file in `config/packages/monsieurbiz_sylius_search_plugin.yaml`:

```yaml
imports:
  - { resource: "@MonsieurBizSyliusSearchPlugin/config/config.yaml" }
```

Create the route config file in `config/routes/monsieurbiz_sylius_search_plugin.yaml`:

```yaml
monsieurbiz_search_plugin:
  resource: "@MonsieurBizSyliusSearchPlugin/config/routing.yaml"
```

Install the bundle assets:

```shell
php bin/console assets:install public
```

The imported configuration registers Twig Hooks; do not copy legacy full-form
overrides. Register/configure the Settings dependency as well (its 2.0.4 package
still uses `Resources/config`; see the migration guide).

Provide these deployment environment variables, adapting the transport and
Elasticsearch URL to your infrastructure:

```
MONSIEURBIZ_SEARCHPLUGIN_MESSENGER_TRANSPORT_DSN=doctrine://default
MONSIEURBIZ_SEARCHPLUGIN_ES_URL=http://localhost:9200/
```

</p>
</details>

1. Install Elasticsearch 💪. See [Infrastructure](#infrastructure) below.

2. Your `ProductAttribute` and `ProductOption` entities need to implement the `MonsieurBiz\SyliusSearchPlugin\Entity\Product\SearchableInterface` interface and use the `MonsieurBiz\SyliusSearchPlugin\Model\Product\SearchableTrait` trait. Example with the `ProductAttribute`:

```diff
namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
+use MonsieurBiz\SyliusSearchPlugin\Entity\Product\SearchableInterface;
+use MonsieurBiz\SyliusSearchPlugin\Model\Product\SearchableTrait;
use Sylius\Component\Attribute\Model\AttributeTranslationInterface;
use Sylius\Component\Product\Model\ProductAttribute as BaseProductAttribute;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_attribute')]
-class ProductAttribute extends BaseProductAttribute
+class ProductAttribute extends BaseProductAttribute implements SearchableInterface
{
+    use SearchableTrait;

    protected function createTranslation(): AttributeTranslationInterface
    {
        return new ProductAttributeTranslation();
    }
}
```

3. Generate and review an application migration with `php bin/console doctrine:migrations:diff`, then apply it with `php bin/console doctrine:migrations:migrate` after backing up existing data. Do not copy the test application's migration delta into a consumer.

4. Set up Messenger transports (`php bin/console messenger:setup-transports`), then populate (`php bin/console monsieurbiz:search:populate`). Run a worker for `async_search` for subsequent asynchronous reindexing.

## Documentation

[Documentation is available in the *docs* folder.](docs/index.md)

## Infrastructure

The plugin was developed for Elasticsearch 7.16.x versions. You need to have analysis-icu and analysis-phonetic elasticsearch plugin installed.

## Other information

### Jane

We are using [Jane](https://github.com/janephp/janephp) to create a DTO (Data-transfer object).  
Generated classes are on `generated` folder.  
Jane configuration and JSON Schema are in `config/jane`. Jane generates DTOs and normalizers; runtime mapping uses JoliCode AutoMapper 9.

To rebuild generated class during plugin development, we are using : 

```bash
symfony php vendor/bin/jane generate --config-file=config/jane/jane-configuration.php
```

### Elastically

The [Elastically](https://github.com/jolicode/elastically) client is configured in `config/services.yaml`.
Customize its environment variables or override services in your application.
Analyzers and YAML mappings are in `config/elasticsearch`.
