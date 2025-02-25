<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * Store a new product.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string',
            'price'        => 'required|numeric',
            'stock'        => 'required|integer',
            'sku'          => 'required|string|unique:items',
            'category_id'  => 'required|string',
            'size'         => 'nullable|string',
            'description'  => 'nullable|string',
            'images'       => 'nullable|array',
            'images.*'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image_urls'   => 'nullable|array',
            'image_urls.*' => 'nullable|url'
        ]);

        $imagePaths = [];

        // Save images uploaded from the PC
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $imagePaths[] = asset("storage/$path");
            }
        }

        // Add image URLs sent as text
        if (!empty($data['image_urls'])) {
            $imagePaths = array_merge($imagePaths, $data['image_urls']);
        }

        // Create the product in the database
        $item = Item::create(array_merge($data, ['images' => json_encode($imagePaths)]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Producto creado exitosamente.',
            'data'    => $item
        ], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $data = $request->validate([
            'title'        => 'sometimes|string',
            'price'        => 'sometimes|numeric',
            'stock'        => 'sometimes|integer',
            'sku'          => 'sometimes|string|unique:items,sku,' . $id,
            'category_id'  => 'sometimes|string',
            'size'         => 'nullable|string',
            'description'  => 'nullable|string',
            'images'       => 'nullable|array',
            'images.*'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image_urls'   => 'nullable|array',
            'image_urls.*' => 'nullable|url'
        ]);

        $imagePaths = json_decode($item->images, true) ?? [];

        // Save new images if they are sent
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $imagePaths[] = asset("storage/$path");
            }
        }

        // Add new URLs if they are sent
        if (!empty($data['image_urls'])) {
            $imagePaths = array_merge($imagePaths, $data['image_urls']);
        }

        $item->update(array_merge($data, ['images' => json_encode($imagePaths)]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Producto actualizado exitosamente.',
            'data'    => $item
        ]);
    }
}
