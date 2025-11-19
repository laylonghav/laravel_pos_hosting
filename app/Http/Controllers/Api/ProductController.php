<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    use ApiResponseTrait;

    public function search(Request $req)
    {
        $query = $req->query("q");

        $category = Product::where("name", "like", "%" . $query . "%")->get();

        return response()->json([
            "Query" => $query,
            "data" => $category,
            "message" => "Get product successfully."
        ], 200);
    }

    public function index()
    {
        $products =  Product::with(["detail", "category", "brand"])->get();

        // return [
        //     "data" => $products,
        //     "message" => "Get Product successfully!"
        // ];

        return $this->sessuccApiRespone($products, "Get Product successfully.", 200);
    }



    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return [
                "message" => "Product is not found."
            ];
        }


        return [
            "data" => $product
        ];
    }

    public function store(Request $req)
    {
        // $input = [
        //     "name" => $req->input("name"),
        //     "description" => $req->input("description"),
        //     "category_id" => $req->input("category_id"),
        //     "brand_id" => $req->input("brand_id"),
        //     "qty" => $req->input("qty"),
        //     "status" => $req->input("status"),
        // ];

        $validate = $req->validate([
            "name" => "required|string|min:3|max:255",
            "description" => "required|string|min:3|max:255",
            "category_id" => "required|string|exists:categories,_id",
            "brand_id" => "required|string|exists:brands,_id",
            "qty" => "required|integer|min:0",
            "price" => "required|integer|min:0",
            "discount" => "required|integer|min:0",
            "status" => "required|boolean",
            "image" => "nullable|image|mimes:jpeg,jpg,png,gif|max:2048",
        ]);

        if ($req->hasFile('image')) {

            $uploaded = Cloudinary::uploadApi()->upload(
                $validate['image']->getRealPath(),
                ['folder' => config('cloudinary.upload_folder', 'img_pos')]
            );

            $validate['image_url'] = $uploaded['secure_url'];
            $validate['image_public_id'] = $uploaded['public_id'];
        }


        $validate["status"] = $req->status == "1" ? true : false;

        // Handle upload image

        // if ($req->hasFile("image")) {
        //     $validate["image"] = $req->file("image")->store("products", "public");
        // }

        $product = Product::create($validate);
        $product->load("detail");

        return [
            "data" => $product,
            "message" => "Created product successfully !",
        ];
    }


    public function update(Request $req, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return [
                "message" => "Product is not found."
            ];
        }


        // $input = [
        //     "name" => $req->input("name"),
        //     "description" => $req->input("description"),
        //     "category_id" => $req->input("category_id"),
        //     "brand_id" => $req->input("brand_id"),
        //     "qty" => $req->input("qty"),
        //     "status" => $req->input("status"),
        // ];


        $validate = $req->validate([
            "name" => "required|string|min:3|max:255",
            "description" => "required|string|min:3|max:255",
            "category_id" => "required|string|exists:categories,_id",
            "brand_id" => "required|string|exists:brands,_id",
            "qty" => "required|integer|min:0",
            "price" => "required|integer|min:0",
            "discount" => "required|integer|min:0",
            "status" => "required|boolean",
            "image" => "nullable|image|mimes:jpeg,jpg,png,gif|max:2048",
        ]);

        if ($req->hasFile('image')) {

            // Delete old image
            if (!empty($product->image_public_id)) {
                Cloudinary::uploadApi()->destroy($product->image_public_id);
            }

            // Upload new image
            $uploaded = Cloudinary::uploadApi()->upload(
                $req->file('image')->getRealPath(),
                ['folder' => config('cloudinary.upload_folder', 'img_pos')]
            );

            $validate['image_url'] = $uploaded['secure_url'];
            $validate['image_public_id'] = $uploaded['public_id'];
        }


        $validate["status"] = $req->status == "1" ? true : false;

        // Handle upload image

        // if ($req->hasFile("image")) {
        //     //check old image
        //     if ($product->image && Storage::disk("public")->exists($product->image)) {
        //         Storage::disk("public")->delete($product->image);
        //     }

        //     //update new image
        //     $validate["image"] = $req->file("image")->store("products", "public");
        // }

        $product->update($validate);
        $product->load("detail");

        return [
            "data" => $product,
            "message" => "Updated product successfully !",
        ];
    }


    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return [
                "message" => "Product is not found."
            ];
        }

        // Delete image from Cloudinary if it exists
        if (!empty($product->image_public_id)) {
            Cloudinary::uploadApi()->destroy($product->image_public_id);
        }


        // Remove image
        // if ($product->image && Storage::disk("public")->exists($product->image)) {
        //     Storage::disk("public")->delete($product->image);
        // }

        $product->delete();

        return [
            "data" => $product,
            "message" => "Deleted product successfully !",
        ];
    }
}
