@extends('layouts.pos')

@section('title', 'Receipt '.$receipt->receipt_number)
@section('body-class', 'receipt-page')

@section('body')
    @include('pos.partials.receipt-document')

    <div class="receipt-actions">
        <button type="button" class="btn outl" onclick="window.close()">Close</button>
        <button type="button" class="btn solid" onclick="window.print()">Print again</button>
    </div>
@endsection

@push('scripts')
<script>
    // Opened from the register specifically to print, so send it straight to
    // the printer once the layout has settled.
    window.addEventListener('load', function () {
        window.setTimeout(function () { window.print(); }, 250);
    });
</script>
@endpush
