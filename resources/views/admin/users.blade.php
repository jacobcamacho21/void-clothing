@extends('layouts.admin')

@section('title', 'Accounts')
@section('heading', 'Staff Accounts')

@section('content')
<div class="content-card">
    <div class="toolbar">
        <h2>Accounts</h2>
        <div class="toolbar-controls">
            <form method="GET" action="{{ route('admin.users') }}" data-search-form>
                <div class="search-field">
                    <img src="{{ asset('images/admin/search.png') }}" alt="">
                    <label for="userSearch" class="visually-hidden">Search accounts</label>
                    <input type="search" id="userSearch" name="q" value="{{ $search }}" placeholder="Search username">
                </div>
            </form>
            <button type="button" class="btn-primary" data-modal-open="addUserModal">Add Account</button>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name ?? '—' }}</td>
                        <td>
                            {{ $user->username }}
                            @if ($user->id === auth()->id())
                                <span class="muted-cell">(you)</span>
                            @endif
                        </td>
                        <td>
                            <span class="status-badge {{ $user->isAdmin() ? 'status-approved' : '' }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td>
                            <span class="status-badge {{ $user->is_active ? 'status-completed' : 'status-rejected' }}">
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td>
                            <div class="action-cell">
                                <button type="button" class="icon-btn" title="Edit {{ $user->username }}"
                                        data-modal-open="editUserModal"
                                        data-action="{{ route('admin.users.update', $user) }}"
                                        data-field-name="{{ $user->name }}"
                                        data-field-username="{{ $user->username }}"
                                        data-field-role="{{ $user->role }}"
                                        data-field-isActive="{{ $user->is_active ? '1' : '0' }}">
                                    <img src="{{ asset('images/admin/edit.png') }}" alt="Edit">
                                </button>

                                @if ($user->id !== auth()->id())
                                    <form method="POST" class="inline-form"
                                          action="{{ route('admin.users.destroy', $user) }}"
                                          data-confirm="Delete the account {{ $user->username }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn" title="Delete {{ $user->username }}">
                                            <img src="{{ asset('images/admin/delete.png') }}" alt="Delete">
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">No accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">{{ $users->links() }}</div>
</div>

{{-- Add --}}
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
        <h3>Add Account</h3>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <label>Full name
                <input type="text" name="name" maxlength="100">
            </label>

            <label>Username
                <input type="text" name="username" maxlength="50" required autocomplete="off">
            </label>

            <label>Password
                <input type="password" name="password" minlength="8" required autocomplete="new-password">
            </label>
            <p class="form-hint">At least 8 characters.</p>

            <label>Role
                <select name="role" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit --}}
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <button type="button" class="close" data-modal-close aria-label="Close">&times;</button>
        <h3>Edit Account</h3>

        <form method="POST" action="">
            @csrf
            @method('PATCH')

            <label>Full name
                <input type="text" name="name" data-field="name" maxlength="100">
            </label>

            <label>Username
                <input type="text" name="username" data-field="username" maxlength="50" required>
            </label>

            <label>New password
                <input type="password" name="password" minlength="8" autocomplete="new-password">
            </label>
            <p class="form-hint">Leave blank to keep the current password.</p>

            <label>Role
                <select name="role" data-field="role" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </label>

            <label class="check-row">
                <input type="checkbox" name="is_active" value="1" data-field="isActive">
                Account is active
            </label>
            <p class="form-hint">A disabled account is signed out on its next request.</p>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
