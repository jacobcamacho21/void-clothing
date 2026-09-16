@extends('layouts.pos')

@section('title', 'Receipt '.$receipt->receipt_number)
@section('body-class', 'receipt-page')

@section('body')
    @include('pos.partials.receipt-document')

    <div class="receipt-actions">
        <a class="btn outl" href="{{ url()->previous() }}">Back</a>
        <a class="btn solid" href="{{ route('pos.receipt.print', $order) }}">Print</a>
    </div>
@endsection
