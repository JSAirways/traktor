@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <h2 class="mb-0">{{ __('admin.pending_registrations') }}</h2>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('admin.back_to_users') }}</a>
</div>

@if(session('error'))
    <x-ui.toast-notification type="error" message="{{ session('error') }}" />
@endif

@if($pendingUsers->count() === 0)
    <x-ui.toast-notification 
        type="info" 
        :autohide="false"
        icon="bi bi-info-circle"
        message="{{ __('admin.no_pending_registrations') }}"
    />
@else
    <div class="mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <input type="checkbox" id="selectAllPending" class="form-check-input" onchange="toggleSelectAll(this)">
                <label for="selectAllPending" class="form-check-label ms-2">{{ __('admin.select_all') }}</label>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()">
                    <i class="bi bi-check-circle me-1"></i>
                    {{ __('admin.approve_selected') }}
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="bulkReject()" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
                    <i class="bi bi-x-circle me-1"></i>
                    {{ __('admin.reject_selected') }}
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll(this)">
                    </th>
                    <th>{{ __('common.username') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('common.email') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('admin.how_heard_about') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('admin.registered') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('common.language') }}</th>
                    <th class="text-end">{{ __('admin.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingUsers as $user)
                    <tr class="align-middle">
                        <td>
                            <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="form-check-input user-checkbox">
                        </td>
                        <td class="align-middle">
                            <div class="d-flex flex-column">
                                <span class="fw-bold">{{ $user->username }}</span>
                                <span class="text-muted small d-md-none">{{ $user->email }}</span>
                            </div>
                        </td>
                        <td class="align-middle d-none d-md-table-cell">{{ $user->email }}</td>
                        <td class="align-middle d-none d-md-table-cell">
                            @if($user->how_heard_about)
                                <span title="{{ $user->how_heard_about }}">{{ \Illuminate\Support\Str::limit($user->how_heard_about, 60) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="align-middle d-none d-md-table-cell">
                            <small>{{ $user->created_at->format('Y-m-d H:i') }}</small>
                        </td>
                        <td class="align-middle d-none d-md-table-cell">
                            @if($user->locale)
                                <span class="badge bg-secondary">{{ strtoupper($user->locale) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <form method="POST" action="{{ route('admin.users.approve', $user) }}" class="d-inline mb-0">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" title="{{ __('admin.approve') }}">
                                        <i class="bi bi-check-circle"></i>
                                        <span class="d-none d-sm-inline ms-1">{{ __('admin.approve') }}</span>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#rejectModal{{ $user->id }}"
                                        title="{{ __('admin.reject') }}">
                                    <i class="bi bi-x-circle"></i>
                                    <span class="d-none d-sm-inline ms-1">{{ __('admin.reject') }}</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    
                    {{-- Reject Modal for individual user --}}
                    <div class="modal fade" id="rejectModal{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.users.reject', $user) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ __('admin.reject_user') }}: {{ $user->username }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" 
                                                      id="rejection_reason{{ $user->id }}" 
                                                      name="rejection_reason" 
                                                      style="height: 100px"
                                                      required 
                                                      placeholder=" "></textarea>
                                            <label for="rejection_reason{{ $user->id }}">{{ __('admin.rejection_reason_label') }} *</label>
                                            <div class="form-text">{{ __('admin.rejection_reason_help') }}</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                                        <button type="submit" class="btn btn-danger">{{ __('admin.reject_user') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Bulk Reject Modal --}}
    <div class="modal fade" id="bulkRejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.users.bulk-reject') }}" id="bulkRejectForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.reject_selected_users') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">{!! str_replace(':count', '<span id="selectedCount">0</span>', e(__('admin.you_are_about_to_reject'))) !!}</p>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" 
                                      id="bulk_rejection_reason" 
                                      name="rejection_reason" 
                                      style="height: 100px"
                                      required 
                                      placeholder=" "></textarea>
                            <label for="bulk_rejection_reason">{{ __('admin.rejection_reason_label') }} *</label>
                            <div class="form-text">{{ __('admin.rejection_reason_help_bulk') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('admin.reject_selected_users') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    @vite('resources/js/admin/users/pending.js')
    <script>
        // Pass translations and routes to JavaScript
        window.pendingUsersTranslations = {
            pleaseSelectAtLeastOneUser: '{{ __('admin.please_select_at_least_one_user') }}',
            confirmApproveUsers: '{{ __('admin.confirm_approve_users') }}'
        };
        window.pendingUsersRoutes = {
            bulkApprove: '{{ route("admin.users.bulk-approve") }}'
        };
    </script>
@endpush
@endsection

