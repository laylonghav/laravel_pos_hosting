<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function getSale()
    {

        // --- Total Sale This Month ---
        $startOfMonth = Carbon::now('UTC')->startOfMonth();
        $endOfMonth   = Carbon::now('UTC')->endOfMonth();

        $saleThisMonth = Order::raw(function ($collection) use ($startOfMonth, $endOfMonth) {
            return $collection->aggregate([
                ['$match' => [
                    'created_at' => [
                        '$gte' => new \MongoDB\BSON\UTCDateTime($startOfMonth),
                        '$lte' => new \MongoDB\BSON\UTCDateTime($endOfMonth)
                    ]
                ]],
                ['$group' => [
                    '_id' => null,
                    'total' => ['$sum' => ['$toDouble' => '$total_amount']],
                    'total_order' => ['$sum' => 1]
                ]]
            ]);
        });

        $totalSale = isset($saleThisMonth[0]) ? $saleThisMonth[0]->total : 0;
        $totalOrder = isset($saleThisMonth[0]) ? $saleThisMonth[0]->total_order : 0;

        // --- Sale Summary by Month (Current Year) ---
        $startOfYear = Carbon::now('UTC')->startOfYear();
        $endOfYear   = Carbon::now('UTC')->endOfYear();

        // $saleSummaryByMonths = Order::raw(function ($collection) use ($startOfYear, $endOfYear) {
        //     return $collection->aggregate([
        //         ['$match' => [
        //             'created_at' => [
        //                 '$gte' => new \MongoDB\BSON\UTCDateTime($startOfYear),
        //                 '$lte' => new \MongoDB\BSON\UTCDateTime($endOfYear)
        //             ]
        //         ]],
        //         ['$group' => [
        //             '_id' => ['$month' => '$created_at'],
        //             'total' => ['$sum' => ['$toDouble' => '$total_amount']]
        //         ]],
        //         ['$project' => [
        //             'title' => [
        //                 '$dateToString' => [
        //                     'format' => '%b',   // JAN FEB ...
        //                     'date' => [
        //                         '$dateFromParts' => [
        //                             'year' => '$_id.year',
        //                             'month' => '$_id.month'
        //                         ]
        //                     ]
        //                 ]
        //             ],
        //             // 'title' => '$_id', // month number 1-12
        //             'total' => 1,
        //             '_id' => 0
        //         ]],
        //         ['$sort' => ['title' => 1]]
        //     ]);
        // });

        $saleSummaryByMonths = Order::raw(function ($collection) use ($startOfYear, $endOfYear) {
            return $collection->aggregate([
                ['$match' => [
                    'created_at' => [

                        '$gte' => new \MongoDB\BSON\UTCDateTime($startOfYear),
                        '$lte' => new \MongoDB\BSON\UTCDateTime($endOfYear)
                    ]
                ]],
                ['$group' => [
                    '_id' => [
                        'month' => ['$month' => '$created_at'],
                        'year'  => ['$year' => '$created_at']
                    ],
                    'total' => ['$sum' => ['$toDouble' => '$total_amount']]
                ]],
                ['$project' => [
                    'title' => [
                        '$dateToString' => [
                            'format' => '%b',
                            'date' => [
                                '$dateFromParts' => [
                                    'year' => '$_id.year',
                                    'month' => '$_id.month',
                                    'day' => 1
                                ]
                            ]
                        ]
                    ],
                    'total' => 1,
                    '_id' => 0
                ]],
                ['$sort' => ['_id.month' => 1]]
            ]);
        });



        return response()->json([
            'sale_this_month' => [
                'total' => $totalSale,
                'total_order' => $totalOrder,
            ],
            'sale_summary_by_months' => $saleSummaryByMonths,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::with("orderDetail")->get();

        return response()->json([
            "data" => $orders,
            "message" => "Get order successfully."
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            "paid_amount" => "required|string",
            "total_amount" => "required|string",
            "payment_method" => "required|string",
            "detail" => "required|array",
            "detail.*.price" => "required",
            "detail.*.qty" => "required",
            "detail.*.discount" => "required",
            "detail.*.total" => "required",
            "detail.*.product_id" => "required",
        ]);

        // "ORD00001"

        $lastOrder = Order::orderBy("_id", "desc")->first();

        if ($lastOrder) {
            $lastNumber = substr($lastOrder->order_no, 3);
            $order_no = "ORD" . str_pad($lastNumber + 1, 5, "0", STR_PAD_LEFT);
        } else {
            $order_no = "ORD00001";
        }

        $order = Order::create([
            "order_no" => $order_no,
            "paid_amount" => $request->paid_amount,
            "total_amount" => $request->total_amount,
            "payment_method" => $request->payment_method,
        ]);

        if ($order) {
            foreach ($request->detail as $item) {
                OrderDetail::create([
                    "price" => $item["price"],
                    "qty" => $item["qty"],
                    "discount" => $item["discount"],
                    "total" => $item["total"],
                    "product_id" => $item["product_id"],
                    "order_id" => $order->_id,
                ]);

                $product = Product::find($item["product_id"]);

                $currnetQty = (int) $product->qty;
                $orderQty = (int) $item["qty"];

                $newQty = max(0, $currnetQty - $orderQty);
                $product->update(["qty" => $newQty]);
            }
        }

        return response()->json([
            "data" => $order->load("orderDetail"),
            "message" => "Created order successfully."
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found."
            ], 404);
        }

        return response()->json([
            "data" => $order->load("orderDetail"),
            "message" => "Get one order successfully."
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found."
            ], 404);
        }

        $order->update($request->only(["paid_amount", "total_amount", "payment_method", "order_no"]));

        return response()->json([
            "data" => $order->load("orderDetail"),
            "message" => "Updated order successfully."
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found."
            ], 404);
        }

        $order->delete();

        return response()->json([
            "data" => $order->load("orderDetail"),
            "message" => "Deleted order successfully."
        ], 200);
    }
}
