 **_This package is not fully tested yet, it is available on composer._** 

# Laravel Nova / Laravel Cashier / Braintree
This package is based on [nova-cashier-manager](https://github.com/themsaid/nova-cashier-manager), I kept his namespace for all PHP classes.

This package adds several components to your Laravel Nova Admin panel to help you with managing customer subscriptions, it works hand
in hand with [Laravel Cashier](https://github.com/laravel/cashier).

## Difference 

1. This package uses a more generic term `billable` in place of `user` in variable names and route names 
2. Some features / Data will not display properly, due to the braintree feature missing
3. `charges` are `transactions`
4. BrainTree Libraries has fully replaced the Stripe Library.
5. It support multiple subscriptions !

## Todos
1. I need more people to test this ... My use case is very limited 
2. Cancel Subscription still do not work (out of some mysterious reasons)
3. Maybe a better UI for multiple subscription cards 

## How it works
This package intends to function as much we can with the BrainTree Library, and Laravel Cashier. Some features that was meant for Stripe will be lost (e.g. Refund)

This package adds a section in the billable resource details view with some information about the subscription:

<img src="https://github.com/themsaid/nova-cashier-tool/blob/master/resource-tool.jpg?raw=true">

If you want to display more details and be able to manage the subscription you may click the "Manage" link which will lead you
to a screen with full management capabilities.

<img src="https://github.com/themsaid/nova-cashier-tool/blob/master/billable-screen.jpg?raw=true">

Currently this package works only with laravel cashier for Braintree, Stripe has been taken out.

## Installation and usage

You may require this package using composer: (not yet on composer, will figure out what to do)

```
composer require "shuyi/nova-bt-cashier-manager"
```

Next up, you must register the tool with Nova in the tools method of the NovaServiceProvider:

```
// in app/Providers/NovaServiceProvder.php

// ...

public function tools()
{
   return [
        // ...
        new \Themsaid\CashierTool\CashierTool(),
    ];
}
```

Now in your billable resource, let's say User, add the following to the `fields()` method:

```
CashierResourceTool::make()->onlyOnDetail()
```


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
