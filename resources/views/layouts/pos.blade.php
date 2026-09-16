<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'Register') &mdash; VOID</title>
    <link rel="stylesheet" href="{{ asset('css/void-sidebar.css') }}?v={{ filemtime(public_path('css/void-sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v={{ filemtime(public_path('css/pos.css')) }}">
    @stack('head')
</head>
<body class="@yield('body-class', 'pos-body')">
@yield('body')

{{-- Same page transition as the back office and the storefront, so leaving
     the register for the stock room looks like one application, not two.
     Only the workspace moves; the rail stays put. --}}
<script src="{{ asset('js/transition.js') }}"></script>
@stack('scripts')
</body>
</html>
