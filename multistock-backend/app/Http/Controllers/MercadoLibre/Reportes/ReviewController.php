<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Review;  // Review model

class ReviewController extends Controller
{
    public function getReviews($product_id)
    {
        // Get reviews for the product
        $reviews = Review::where('product_id', $product_id)->get();

        // Calculate the average rating
        $rating_average = $reviews->avg('rating');

        // Return the reviews and the average rating
        return response()->json([
            'reviews' => $reviews,
            'rating_average' => $rating_average
        ]);
    }
}
