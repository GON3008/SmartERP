<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 100 orders with order items
        Order::factory(100)->create()->each(function ($order) {
            $numItems = rand(1, 5);
            $totalAmount = 0;

            for ($i = 0; $i < $numItems; $i++) {
                $product = Product::inRandomOrder()->first();
                $quantity = rand(1, 20);
                $price = $product->price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);

                $totalAmount += ($quantity * $price);
            }

            // Update order total amount
            $order->update(['total_amount' => $totalAmount]);
        });
    }
}
