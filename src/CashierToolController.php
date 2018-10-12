<?php

    namespace Themsaid\CashierTool;

    use Braintree\Plan;
    use Braintree\Transaction;
    use Illuminate\Config\Repository;
    use Illuminate\Http\Request;
    use Illuminate\Routing\Controller;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Collection;
    use Laravel\Cashier\Invoice;

    class CashierToolController extends Controller
    {
        /**
         * The model used by Cashier.
         *
         * @var string
         */
        public $billableModel;


        /**
         * Create a new controller instance.
         *
         * @param \Illuminate\Config\Repository $config
         */
        public function __construct(Repository $config)
        {
            $this->middleware(function ($request, $next) use ($config) {
                \Braintree_Configuration::environment(config('services.braintree.environment'));
                \Braintree_Configuration::merchantId(config('services.braintree.merchant_id'));
                \Braintree_Configuration::publicKey(config('services.braintree.public_key'));
                \Braintree_Configuration::privateKey(config('services.braintree.private_key'));

                $this->billableModel = $config->get('services.braintree.model');

                return $next($request);
            });
        }

        /**
         * Return the user response.
         *
         * @param  int $billableId
         *
         * @return array
         * @throws \Braintree\Exception\NotFound
         */
        public function billable($billableId)
        {
            /** @var \Laravel\Cashier\Billable|\Eloquent $billable */
            $billable = (new $this->billableModel)->find($billableId);

            $subscriptions = $billable->subscriptions();

            if ($subscriptions->count() < 1) {
                return [
                    'subscriptions' => null,
                ];
            }

            if (request()->has('subscription_id')) {
                $subscription =$subscriptions
                    ->where('braintree_plan', request()->subscription_id)
                    ->first();
            }

            return response()->json([
                'user'          => $billable->toArray(),
                'cards'         => request('brief') ? [] : $this->formatCards($billable),
                'invoices'      => request('brief') ? [] : $this->formatInvoices(optional($billable->invoicesIncludingPending())->get()),
                'charges'       => request('brief') ? [] : $this->formatCharges($billable),
                'subscriptions' => request()->has('subscription_id') ? $this->formatSubscription($subscription) : $this->formatSubscriptions($subscriptions),
                'plans'         => request('brief') ? [] : $this->formatPlans(Plan::all()),
            ]);
        }

        /**
         * Cancel the given subscription.
         *
         * @param  \Illuminate\Http\Request $request
         * @param  int                      $billableId
         *
         * @return \Illuminate\Http\Response
         */
        public function cancelSubscription(Request $request, $billableId)
        {
            /** @var \Laravel\Cashier\Billable|\Eloquent $billable */
            $billable = (new $this->billableModel)->find($billableId);

            $subscription = $billable->subscriptions()
                ->where('braintree_plan', $request->subscription_id)
                ->first();

            if ($request->input('now')) {
                $subscription->cancelNow();
            } else {
                $subscription->cancel();
            }
        }

        /**
         * Update the given subscription.
         *
         * @param  \Illuminate\Http\Request $request
         * @param  int                      $billableId
         *
         * @return \Illuminate\Http\Response
         */
        public function updateSubscription(Request $request, $billableId)
        {
            /** @var \Laravel\Cashier\Billable|\Eloquent $billable */

            $billable = (new $this->billableModel)->find($billableId);

            $subscription = $billable->subscriptions()
                ->where('braintree_plan', $request->subscription_id)
                ->first();

            $subscription
                ->swap($request->input('plan'));
        }

        /**
         * Resume the given subscription.
         *
         * @param  \Illuminate\Http\Request $request
         * @param  int                      $billableId
         * @param  int                      $subscription_id
         *
         * @return \Illuminate\Http\Response
         */
        public function resumeSubscription(Request $request, $billableId)
        {
            /** @var \Laravel\Cashier\Billable|\Eloquent $billable */

            $billable = (new $this->billableModel)->find($billableId);

            $subscription = $billable->subscriptions()
                ->where('braintree_plan', $request->subscription_id)
                ->first();

            $subscription->resume();
        }

        /**
         * Refund the given charge. ( Don't think it's possible with BT)
         *
         * @param  \Illuminate\Http\Request $request
         * @param  int                      $billableId
         * @param  string                   $stripeChargeId
         *
         * @return \Illuminate\Http\Response
         */
        public function refundCharge(Request $request, $billableId, $stripeChargeId)
        {
            return '';
        }

        public function formatSubscriptions($subscriptions)
        {
            if (empty($subscriptions)) {
                return [];
            }

            return $subscriptions->get()->map(function ($subscription) {
                return $this->formatSubscription($subscription);
            });
        }

        /**
         * Format a a subscription object.
         *
         * @param  \Laravel\Cashier\Subscription $subscription
         *
         * @return array
         */
        public function formatSubscription($subscription)
        {
            $brainTreeSubscription = $subscription->asBraintreeSubscription();
            $planID = $brainTreeSubscription->planId;

            $plan = collect(Plan::all())
                ->where('id', $planID)
                ->first();

            return [
                'plan_amount'           => abs($plan->price),
                //braintree is always 1 month
                'plan_interval'         => $plan->billingDayOfMonth,
                'plan_frequency'        => $plan->billingFrequency,
                'plan_currency'         => $plan->currencyIsoCode,
                'plan'                  => $plan->description,
                'stripe_plan'           => $plan->id,
                'name'                  => $plan->name,
                'ended'                 => null,
                'cancelled'             => $subscription->cancelled(),
                'active'                => $subscription->active(),
                'on_trial'              => $subscription->onTrial(),
                'on_grace_period'       => $subscription->onGracePeriod(),
                'charges_automatically' => true,
                'created_at'            => Carbon::createFromTimestamp($brainTreeSubscription->createdAt->getTimestamp())->toDateString(),
                'ended_at'              => null,
                'next_billing_date'     => $brainTreeSubscription->nextBillingDate ?
                    Carbon::createFromTimestamp($brainTreeSubscription->nextBillingDate->getTimestamp())->toDateString() :
                    null,
                'days_until_due'        => 0,
                'cancel_at_period_end'  => 0,
                'canceled_at'           => null,
            ];
        }

        /**
         * Format the cards collection.
         *
         * @param  \Laravel\Cashier\Billable|\Eloquent $billable
         *
         * @return array
         * @throws \Braintree\Exception\NotFound
         */
        private function formatCards($billable)
        {
            $creditCards = \Braintree_Customer::find($billable->braintree_id)->creditCards;

            return collect($creditCards)->map(function ($card) {
                $expiryArray = explode('/', $card->expirationDate);

                return [
                    'id'         => $card->uniqueNumberIdentifier,
                    'is_default' => $card->default,
                    'name'       => $card->cardholderName,
                    'last4'      => $card->last4,
                    'country'    => optional($card->billingAddress)->countryCodeAlpha2,
                    'brand'      => $card->cardType,
                    'exp_month'  => $expiryArray[0],
                    'exp_year'   => $expiryArray[1],
                ];
            })->toArray();
        }

        /**
         * Format the invoices collection.
         *
         * @param  array|collection $invoices
         *
         * @return array
         */
        private function formatInvoices($invoices)
        {
            return collect($invoices)->map(function (Invoice $invoice) {
                $transaction = $transaction ;

                if (empty($transaction)) {
                    return;
                }

                return [
                    'id'           => $transaction->id,
                    'total'        => $invoice->total(),
                    //not sure what this one does ?
                    'attempted'    => $transaction->processorResponseCode,
                    'charge_id'    => $transaction->id,
                    'currency'     => $transaction->currencyIsoCode,
                    //these two should work, need testing
                    'period_start' => $invoice->period_start ? Carbon::createFromTimestamp($invoice->period_start)->toDateTimeString() : null,
                    'period_end'   => $invoice->period_end ? Carbon::createFromTimestamp($invoice->period_end)->toDateTimeString() : null,
                ];
            })->toArray();
        }

        /**
         * Format the charges collection.
         *
         * /** @var \Laravel\Cashier\Billable|\Eloquent $billable
         * @return array
         */
        private function formatCharges($billable)
        {
            $transactions = \Braintree_Transaction::search([
                \Braintree_TransactionSearch::customerId()->is($billable->braintree_id),
            ]);

            return collect($transactions)->map(function (Transaction $charge) {
                return [
                    'id'              => $charge->id,
                    'amount'          => $charge->amount,
                    //not sure how amount_refunded or captured is being captured by BT
                    'amount_refunded' => 0,
                    'captured'        => true,
                    'paid'            => $charge->processorResponseCode == 'Approved',
                    'status'          => $charge->status,
                    'currency'        => $charge->currencyIsoCode,
                    'dispute'         => $charge->disputes,
                    'failure_code'    => $charge->processorResponseCode,
                    'failure_message' => $charge->additionalProcessorResponse,
                    'created'         => $charge->createdAt ? Carbon::createFromTimestamp($charge->createdAt->getTimestamp())->toDateTimeString() : null,
                ];
            })->toArray();
        }

        /**
         * Format the plans collection.
         *
         * @param  array $charges
         *
         * @return array
         */
        private function formatPlans($plans)
        {
            return collect($plans)->map(function (Plan $plan) {
                return [
                    'id'             => $plan->id,
                    'price'          => $plan->price,
                    'plan_interval'  => $plan->billingDayOfMonth,
                    'plan_frequency' => $plan->billingFrequency,
                    'currency'       => $plan->currencyIsoCode,
                    'description'    => $plan->description,
                    'name'           => $plan->name,
                ];
            })->toArray();
        }
    }
