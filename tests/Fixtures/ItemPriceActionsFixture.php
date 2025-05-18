<?php

namespace Chargebee\Cashier\Tests\Fixtures;

use Chargebee\Actions\Contracts\ItemPriceActionsInterface;
use Chargebee\Responses\ItemPriceResponse\CreateItemPriceResponse;
use Chargebee\Responses\ItemPriceResponse\DeleteItemPriceResponse;
use Chargebee\Responses\ItemPriceResponse\FindApplicableItemPricesItemPriceResponse;
use Chargebee\Responses\ItemPriceResponse\FindApplicableItemsItemPriceResponse;
use Chargebee\Responses\ItemPriceResponse\ListItemPriceResponse;
use Chargebee\Responses\ItemPriceResponse\RetrieveItemPriceResponse;
use Chargebee\Responses\ItemPriceResponse\UpdateItemPriceResponse;

class ItemPriceActionsFixture implements ItemPriceActionsInterface
{
    public function findApplicableItems(string $id, array $params = [], array $headers = []): FindApplicableItemsItemPriceResponse
    {
        return FindApplicableItemsItemPriceResponse::from([
            'item_price_id' => $id,
            'items' => [],
        ]);
    }

    public function retrieve(string $id, array $headers = []): RetrieveItemPriceResponse
    {
        return RetrieveItemPriceResponse::from([
            'item_price' => [
                'id' => 'abc',
                'item_id' => 'product_abc',
                'name' => 'Basic Plan',
                'currency_code' => 'USD',
                'free_quantity' => 0,
                'created_at' => time(),
                'deleted' => false,
                'pricing_model' => 'flat_fee',
            ],
        ]);
    }

    public function update(string $id, array $params, array $headers = []): UpdateItemPriceResponse
    {
        return UpdateItemPriceResponse::from([
            'item_price' => ['id' => $id, 'updated' => true, 'params' => $params],
        ]);
    }

    public function delete(string $id, array $headers = []): DeleteItemPriceResponse
    {
        return DeleteItemPriceResponse::from([
            'item_price' => ['id' => $id, 'deleted' => true],
        ]);
    }

    public function findApplicableItemPrices(string $id, array $params = [], array $headers = []): FindApplicableItemPricesItemPriceResponse
    {
        return FindApplicableItemPricesItemPriceResponse::from([
            'item_price_id' => $id,
            'applicable_prices' => [],
        ]);
    }

    public function all(array $params = [], array $headers = []): ListItemPriceResponse
    {
        return ListItemPriceResponse::from([
            'list' => [],
            'next_offset' => null,
        ]);
    }

    public function create(array $params, array $headers = []): CreateItemPriceResponse
    {
        return CreateItemPriceResponse::from([
            'item_price' => ['id' => 'sample_id', 'name' => 'Created Item Price', 'params' => $params],
        ]);
    }
}
