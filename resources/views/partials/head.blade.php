{{--
    The part of <head> every area of the system shares.

    Each layout adds its own <title> and area stylesheet after this, so the
    token layer is always loaded first and always loaded — the storefront,
    the auth pages, the back office and the register all resolve their
    colours, radii and spacing from the same place.
--}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/png" href="{{ asset('images/site/Void_Logo_W.png') }}">
<link rel="stylesheet" href="{{ asset('css/void-tokens.css') }}?v={{ filemtime(public_path('css/void-tokens.css')) }}">
