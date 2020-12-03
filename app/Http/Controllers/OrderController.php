<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PayPal\Api\Amount;
use PayPal\Api\Authorization;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PHPUnit\Util\TestDox\ResultPrinter;

class OrderController extends Controller
{
    private $apiContext;

    public function __construct()
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(env('PAYPAL_CLIENT_ID'), env('PAYPAL_SECRET'))
        );
        $this->apiContext->setConfig(config('paypal.settings'));
    }

    public function create(Request $request)
    {
        //
        try {
            $this->validate($request, [
                'link' => 'required|url',
                'title' => 'required',
                'quantity' => 'required|integer|min:1',
                'seconds' => 'required|integer|in:30,60,90',
                'daily_limit' => 'required|integer|min:0',
                'gender' => 'required',
                'country' => 'required'
            ]);

            Order::create($request->all());

            return redirect()->route('my-video');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function do_finish_watch(Request $request, $order) {
        $order = Order::where('uid', $order)->firstOrFail();

        $user = Auth::user();
        $user->coin = $user->coin + $order->seconds;
        $user->save();
        Auth::user()->watches()->save($order);

        if ($request->ajax())
            return \response()->json([
                'status' => true,
                'data' => "Successfully saved"
            ]);
        else
            return back()->with([
                'success' => "Successfully saved"
            ]);
    }

    public function payment_success(Request $request) {
        $paymentId = $request->paymentId;
        $payment = Payment::get($paymentId, $this->apiContext);

        $execution = new PaymentExecution();
        $execution->setPayerId($request->PayerID);

        try {
            $payment->execute($execution, $this->apiContext);

            $transactions = $payment->getTransactions();
            $transaction = $transactions[0];
            $relatedResources = $transaction->getRelatedResources();
            $relatedResource = $relatedResources[0];
            $order = $relatedResource->getOrder();

            // ### Create Authorization Object
            // with Amount in it
            $authorization = new Authorization();
            $authorization->setAmount(new Amount(
            '{
                        "total": "2.00",
                        "currency": "USD"
                }'));

            $result = $order->authorize($authorization, $this->apiContext);

            dd($result);
        } catch (\Exception $e) {
            //todo: handle exception
        }
    }

    public function payment_cancel(Request $request) {

    }

    public function api_create(Request $request) {
        $response = array();
        try {
            $this->validate($request, [
                'link' => 'required|url',
                'quantity' => 'required|integer|min:1',
                'seconds' => 'required|integer|in:30,60,90',
                //'api-key' => 'required'
            ]);

            $order = new Order();
            $order->fill($request->all());
            $order->save();

            $response['status'] = true;
            $response['data'] = $order->id;
            return response()->json($response);
        } catch (ValidationException $e) {
            $response['status'] = false;
            $response['error'] = $e->errors();
            return response()->json($response, Response::HTTP_NOT_FOUND);
        }
    }

    public function watch(Request $request) {
        try {
            $this->validate($request, [
                'uid' => 'required'
            ]);

            $order = Order::where('uid', $request->uid)->firstOrFail();
            $order->status = config('constant.status.in_progress');
            $order->save();

            $user = Auth::user();
            $order->users()->attach($user);
            $user->coins = $user->coin + $order->seconds;
            $user->save();

            return response()->json([
                'status' => true,
                'data' => $order->remains,
                'message' => "Successfully recorded your watching seconds"
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'error' => $e->errors()
            ]);
        }
    }
}
