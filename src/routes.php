<?php

Route::get('/billable/{id}', 'Themsaid\CashierTool\CashierToolController@billable');
Route::post('/billable/{id}/cancel', 'Themsaid\CashierTool\CashierToolController@cancelSubscription');
Route::post('/billable/{id}/resume', 'Themsaid\CashierTool\CashierToolController@resumeSubscription');
Route::post('/billable/{id}/update', 'Themsaid\CashierTool\CashierToolController@updateSubscription');
Route::post('/billable/{id}/refund/{chargeId}', 'Themsaid\CashierTool\CashierToolController@refundCharge');