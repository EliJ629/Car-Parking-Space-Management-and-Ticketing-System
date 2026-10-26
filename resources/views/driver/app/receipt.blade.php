@extends('layouts.driver-app')

@section('title', 'Receipt '.$payment->receipt_number.' - Bayline')
@section('tab', 'account')
@section('heading', 'Receipt')
@section('back', route('driver.history'))

@section('content')
<p class="sub" style="margin:0 0 12px">This is the same receipt the booth prints.</p>
@include('partials.receipt-slip', ['payment' => $payment, 'session' => $session, 'slipId' => 'receipt-slip'])
<div class="actions"><button class="btn ghost" type="button" data-print>Print or save</button></div>
@endsection
