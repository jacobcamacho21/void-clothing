@php
    /**
     * The one staff navigation rail, shared by the register and the back
     * office so the sidebar never changes between pages.
     *
     * $posMode is set only by the register, where Orders and Held Sales open
     * the register's own modals instead of navigating away. Everywhere else
     * they are ordinary links. Both render as the same .nav-link, so the
     * design is identical either way.
     *
     * $pendingCount, $heldCount and $terminal are supplied for every page by
     * the view composer in AppServiceProvider.
     */
    /** @var \App\Models\User $navUser */
    $navUser = auth()->user();
    $posMode = $posMode ?? false;
@endphp

<nav class="pos-sidebar" aria-label="Main">
    <a href="{{ route('pos.register') }}" class="pos-brand">
        <img src="{{ asset('images/site/Void_Logo_W.png') }}" class="pos-logo" alt="VOID">
        <span class="sub">RETAIL SYSTEM</span>
    </a>

    <div class="nav-scroll">
    <div class="nav-group">
        <div class="nav-label" id="nav-ops">Operations</div>

        <a class="nav-link @if (request()->routeIs('admin.dashboard')) active @endif"
           href="{{ route('admin.dashboard') }}"
           @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
            Dashboard
        </a>

        <a class="nav-link @if (request()->routeIs('pos.*')) active @endif"
           href="{{ route('pos.register') }}"
           @if (request()->routeIs('pos.*')) aria-current="page" @endif>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h8"/></svg>
            Register
        </a>

        @if ($posMode)
            <button type="button" class="nav-link" data-open-tracking>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                Orders
                <span class="badge" id="pendingBadge" @if ($pendingCount < 1) hidden @endif>{{ $pendingCount }}</span>
            </button>
            <button type="button" class="nav-link" data-open-held>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9v6M15 9v6"/></svg>
                Held Sales
                <span class="badge hollow" id="heldBadge">{{ $heldCount }}</span>
            </button>
        @else
            <a class="nav-link @if (request()->routeIs('admin.orders*')) active @endif"
               href="{{ route('admin.orders') }}"
               @if (request()->routeIs('admin.orders*')) aria-current="page" @endif>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                Orders
                <span class="badge" @if ($pendingCount < 1) hidden @endif>{{ $pendingCount }}</span>
            </a>
            {{-- Held sales are worked at the register, so this hands over to it. --}}
            <a class="nav-link" href="{{ route('pos.register') }}">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9v6M15 9v6"/></svg>
                Held Sales
                <span class="badge hollow">{{ $heldCount }}</span>
            </a>
        @endif
    </div>

    <div class="nav-group">
        <div class="nav-label">Catalog</div>

        <a class="nav-link @if (request()->routeIs('admin.inventory*')) active @endif"
           href="{{ route('admin.inventory') }}"
           @if (request()->routeIs('admin.inventory*')) aria-current="page" @endif>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 7l-8-4-8 4 8 4 8-4z"/><path d="M4 12l8 4 8-4M4 17l8 4 8-4"/></svg>
            Inventory
        </a>

        @if ($navUser->isAdmin())
            <a class="nav-link @if (request()->routeIs('admin.customers*')) active @endif"
               href="{{ route('admin.customers') }}"
               @if (request()->routeIs('admin.customers*')) aria-current="page" @endif>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                Customers
            </a>
        @endif
    </div>

    @if ($navUser->isAdmin())
        <div class="nav-group">
            <div class="nav-label">Account</div>

            <a class="nav-link @if (request()->routeIs('admin.users*')) active @endif"
               href="{{ route('admin.users') }}"
               @if (request()->routeIs('admin.users*')) aria-current="page" @endif>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/></svg>
                Staff Accounts
            </a>
        </div>
    @endif

    </div>{{-- /.nav-scroll --}}

    <div class="side-foot">
        <p class="who">
            Signed in as
            <b>{{ $navUser->displayName() }}</b>
            {{ ucfirst($navUser->role) }} &middot; {{ $terminal }}
        </p>
        <form method="POST" action="{{ route('staff.logout') }}">
            @csrf
            <button type="submit" class="logout">Logout</button>
        </form>
    </div>
</nav>
