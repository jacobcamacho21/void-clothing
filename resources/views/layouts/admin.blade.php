<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title', 'Dashboard') &mdash; VOID</title>
    <link rel="stylesheet" href="{{ asset('css/index_design.css') }}?v={{ filemtime(public_path('css/index_design.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/void-sidebar.css') }}?v={{ filemtime(public_path('css/void-sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    @stack('head')
</head>
<body class="admin-page">
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
@endphp

@include("partials.sidebar")

<div class="main-content">
    <div class="header-bar">
        <h1 class="page-title">@yield('heading', 'Dashboard')</h1>
        @hasSection('header-actions')
            <div class="header-actions">@yield('header-actions')</div>
        @endif
    </div>

    <div class="content-body">
        @if (session('status'))
            <p class="status-success" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div class="status-error" role="alert">
                @foreach ($errors->all() as $error)
                    <p style="margin:0">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @yield('content')
    </div>
</div>

{{-- The staff side gets the storefront's page-to-page transition. The rail
     is deliberately left out of it: only .content-body leaves and arrives,
     so navigating never moves the navigation. --}}
<script src="{{ asset('js/transition.js') }}"></script>
<script src="{{ asset('js/modal-motion.js') }}"></script>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
@stack('scripts')
</body>
</html>
