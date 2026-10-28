<?php
/**
 * Lightweight compatibility implementation of Zen Cart's shoppingCart class.
 *
 * This shim provides the minimal surface area required by the REST webhook
 * bootstrap so that webhook requests can be processed in environments where the
 * full Zen Cart storefront stack isn't available (e.g. stand-alone webhook
 * endpoints).
 */

if (class_exists('shoppingCart')) {
    return;
}

class shoppingCart
{
    /**
     * In-memory representation of cart contents keyed by product identifier.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $contents = [];

    /**
     * Cached total value of items in the cart.
     */
    protected $total = 0.0;

    /**
     * Cached total weight of the cart's contents.
     */
    protected $weight = 0.0;

    /**
     * Denotes the nature of cart contents (mirrors core behaviour).
     */
    protected $content_type = 'virtual';

    public function __construct()
    {
        $this->reset(false);
    }

    /**
     * Reset the cart to an empty state.
     */
    public function reset($reset_database = false): void
    {
        $this->contents = [];
        $this->total = 0.0;
        $this->weight = 0.0;
        $this->content_type = 'virtual';
    }

    /**
     * Compatibility placeholder that mirrors the storefront signature.
     */
    public function restore_contents(): void
    {
        // No-op in compatibility shim.
    }

    /**
     * Return the products currently stored in the cart.
     */
    public function get_products(): array
    {
        return $this->contents;
    }

    /**
     * Report how many line-items the cart contains.
     */
    public function count_contents(): int
    {
        return count($this->contents);
    }

    /**
     * Retrieve the cached cart total.
     */
    public function show_total(): float
    {
        return $this->total;
    }

    /**
     * Retrieve the cached cart weight.
     */
    public function show_weight(): float
    {
        return $this->weight;
    }

    /**
     * Determine whether a product is present in the cart.
     */
    public function in_cart(string $product_id): bool
    {
        return isset($this->contents[$product_id]);
    }

    /**
     * Add or update a product within the cart.
     */
    public function add_cart($product_id, $quantity = 1, $attributes = [], $notify = true): void
    {
        $this->contents[$product_id] = [
            'id' => $product_id,
            'quantity' => (int) $quantity,
            'attributes' => $attributes,
        ];

        $this->recalculateTotals();
    }

    /**
     * Return the configured content type for the cart.
     */
    public function get_content_type(): string
    {
        return $this->content_type;
    }

    /**
     * Allow external code to override the cart's content classification.
     */
    public function set_content_type(string $content_type): void
    {
        $this->content_type = $content_type;
    }

    /**
     * Recalculate the derived totals for the cart.
     */
    protected function recalculateTotals(): void
    {
        $this->total = 0.0;
        $this->weight = 0.0;

        foreach ($this->contents as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $this->total += 0.0 * $quantity;
            $this->weight += 0.0 * $quantity;
        }
    }
}
