<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The stock room. Staff keep counts straight; admins own the catalog and its
 * pricing.
 */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->query('q', ''));

        $variants = ProductVariant::query()
            ->with('product')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%"))
                        ->orWhere('size', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')
            ->orderBy('product_variants.id')
            ->select('product_variants.*')
            ->paginate(25)
            ->withQueryString();

        return view('admin.inventory', [
            'variants' => $variants,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'summary' => [
                'total' => ProductVariant::count(),
                'low_stock' => ProductVariant::lowStock()->count(),
                'out_of_stock' => ProductVariant::where('stock', 0)->count(),
                'stock_value' => (float) ProductVariant::selectRaw('COALESCE(SUM(price * stock), 0) AS v')->value('v'),
            ],
            'sizes' => array_keys(config('void.sizes')),
            'canManage' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * Add a size to the catalog, creating the parent design if it is new.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = isset($data['product_id'])
            ? Product::findOrFail($data['product_id'])
            : Product::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'category' => $data['category'] ?? 'General',
                'description' => $data['description'] ?? null,
                'image' => str_replace(' ', '', $data['name']).'.png',
                'is_active' => true,
            ]);

        $exists = $product->variants()->where('size', $data['size'])->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors(['size' => $product->name.' already has a '.$data['size'].' variant. Edit that row instead.']);
        }

        $product->variants()->create([
            'size' => $data['size'],
            'sku' => ($data['sku'] ?? null) ?: $this->generateSku($product, $data['size']),
            'price' => $data['price'],
            'stock' => $data['stock'],
        ]);

        return redirect()
            ->route('admin.inventory')
            ->with('status', $product->name.' ('.$data['size'].') added to the catalog.');
    }

    public function update(UpdateVariantRequest $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validated();

        // Staff can correct the count; only an admin changes price or size.
        $variant->stock = $data['stock'];

        if ($request->user()->isAdmin()) {
            $variant->price = $data['price'] ?? $variant->price;

            if (! empty($data['size']) && $data['size'] !== $variant->size) {
                $taken = ProductVariant::where('product_id', $variant->product_id)
                    ->where('size', $data['size'])
                    ->whereKeyNot($variant->id)
                    ->exists();

                if ($taken) {
                    return back()->withErrors(['size' => 'That size already exists for this product.']);
                }

                $variant->size = $data['size'];
            }
        }

        $variant->save();

        return redirect()
            ->route('admin.inventory')
            ->with('status', $variant->label().' updated.');
    }

    public function destroy(Request $request, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('delete', $variant->product ?? new Product);

        $label = $variant->label();
        $product = $variant->product;

        $variant->delete();

        // A design with no sizes left is no longer sellable; retire it rather
        // than leaving an empty product on the storefront.
        if ($product && $product->variants()->count() === 0) {
            $product->update(['is_active' => false]);
        }

        return redirect()
            ->route('admin.inventory')
            ->with('status', $label.' removed from the catalog.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function generateSku(Product $product, string $size): string
    {
        $sizeCode = config('void.sizes')[$size] ?? Str::upper(Str::substr($size, 0, 2));

        return sprintf(
            'VD-%s%03d-%s',
            Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $product->name), 0, 2)),
            $product->id,
            $sizeCode
        );
    }
}
