# Add custom values to your indexed entity

## Add custom value for a product

In our example we will add the `short_description` product field to the indexed content.

- [Add your elasticsearch config path in `monsieurbiz_sylius_search.elastically_configuration_paths`](../dist/config/packages/monsieurbiz_sylius_search_plugin.yaml#L9)
- [Extend the product mapping to add the field](../dist/config/search/elasticsearch/monsieurbiz_product_mapping.yaml)
- [Register the autowired, autoconfigured metadata listener/transformer](../dist/config/search/services.yaml)
- [Create DecorateProductMapperConfiguration class](../dist/src/Search/Automapper/DecorateProductMapperConfiguration.php)

Despite its historical name, `DecorateProductMapperConfiguration` is now an
additive `GenerateMapperEvent` listener, not a service decorator. It matches the
configured product source/target, adds `PropertyMetadataEvent` for
`short_description` at priority `-10`, and uses a native `PropertyTransformer`
whose `transform()` returns the product's short description. Do not use the old
Jane `MapperConfigurationInterface` or `forMember()` API.

After clearing your application cache and repopulating the index, you will have
the `item.short_description` variable available in your templates.

## Search on the custom value

With only the listener/transformer, you will not be able to search in the content of the new field.
You have to change parameters to define the fields to search for the search page and the instant search.

- [Add `short_description` in `monsieurbiz.search.product.search.fields_to_search`](../dist/config/search/config.yaml)
- [Add `short_description` in `monsieurbiz.search.product.instant.fields_to_search`](../dist/config/search/config.yaml)
