<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesPinToggle;
use App\Http\Controllers\Concerns\HandlesProfilePicture;
use App\Http\Requests\Admin\ConvertChildToParentRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\DeviceChildVisibility;
use App\Models\User;
use App\Services\PinService;
use App\Services\ProfilePictureService;
use App\Services\UserApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use HandlesPinToggle, HandlesProfilePicture;

    public function __construct(
        protected PinService $pinService,
        protected UserApprovalService $approvalService,
        protected ProfilePictureService $profilePictureService
    ) {
    }

    public function index(Request $request)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $status = $request->get('status', 'all');
        
        // Only fetch parent users (where parent_id IS NULL)
        $query = User::whereNull('parent_id')
            ->with([
                'children',
                'deviceRegistrations' // Eager load device registrations for enabled devices count
            ]); // Eager load children for expandable view
        
        if ($status === 'pending') {
            $query->pending();
        } elseif ($status === 'approved') {
            $query->approved();
        } elseif ($status === 'rejected') {
            $query->rejected();
        }
        
        $users = $query->orderBy('username')->paginate(15)->withQueryString();
        
        return view('admin.users.index', compact('users', 'status'));
    }
    
    /**
     * Show pending registrations for approval.
     */
    public function pendingRegistrations()
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $pendingUsers = User::pending()->orderBy('created_at')->get();
        return view('admin.users.pending', compact('pendingUsers'));
    }
    
    /**
     * Approve a user account.
     * Can approve both pending and rejected users.
     */
    public function approve(User $user, Request $request)
    {
        // Allow approving pending or rejected users
        if (!$user->isPending() && !$user->isRejected()) {
            return back()->with('error', __('messages.user_not_pending'));
        }
        
        $this->approvalService->approve($user, auth()->user());
        
        return back()->with('success', __('messages.user_approved', ['username' => $user->username]));
    }
    
    /**
     * Reject a user account.
     */
    public function reject(User $user, Request $request)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);
        
        if (!$user->isPending()) {
            return back()->with('error', __('messages.user_not_pending'));
        }
        
        $this->approvalService->reject($user, $request->rejection_reason, auth()->user());
        
        return back()->with('success', __('messages.user_rejected', ['username' => $user->username]));
    }

    /**
     * Approve a user account from email link (signed URL).
     * Can approve both pending and rejected users.
     */
    public function approveFromEmail(User $user, Request $request)
    {
        // Validate signed URL (middleware handles this, but double-check)
        if (!$request->hasValidSignature()) {
            abort(403, __('admin.invalid_signature'));
        }

        // Allow approving pending or rejected users
        if (!$user->isPending() && !$user->isRejected()) {
            return redirect()->route('welcome')
                ->with('error', __('messages.user_not_pending'));
        }

        // Get first admin user for tracking (or use system user)
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            // If no admin exists, approve without admin tracking
            $user->update([
                'account_status' => 'approved',
                'approved_at' => now(),
            ]);
            event(new \App\Events\UserApproved($user, $user)); // Use user as admin placeholder
        } else {
            $this->approvalService->approve($user, $admin);
        }

        return redirect()->route('welcome')
            ->with('success', __('messages.user_approved', ['username' => $user->username]));
    }

    /**
     * Reject a user account from email link (signed URL).
     */
    public function rejectFromEmail(User $user, Request $request)
    {
        // Validate signed URL (middleware handles this, but double-check)
        if (!$request->hasValidSignature()) {
            abort(403, __('admin.invalid_signature'));
        }

        if (!$user->isPending()) {
            return redirect()->route('welcome')
                ->with('error', __('messages.user_not_pending'));
        }

        // Default rejection reason if not provided
        $reason = $request->input('reason', __('admin.account_rejected_default'));

        // Get first admin user for tracking (or use system user)
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            // If no admin exists, reject without admin tracking
            $user->update([
                'account_status' => 'rejected',
                'rejection_reason' => $reason,
            ]);
            event(new \App\Events\UserRejected($user, $reason, $user)); // Use user as admin placeholder
        } else {
            $this->approvalService->reject($user, $reason, $admin);
        }

        return redirect()->route('welcome')
            ->with('success', __('messages.user_rejected', ['username' => $user->username]));
    }
    
    /**
     * Bulk approve users.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        
        $count = $this->approvalService->bulkApprove($request->user_ids, auth()->user());
        
        return back()->with('success', __('messages.users_approved', ['count' => $count]));
    }
    
    /**
     * Bulk reject users.
     */
    public function bulkReject(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'rejection_reason' => 'required|string|max:1000',
        ]);
        
        $count = $this->approvalService->bulkReject($request->user_ids, $request->rejection_reason, auth()->user());
        
        return back()->with('success', __('messages.users_rejected', ['count' => $count]));
    }

    public function create()
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        // Get all profile pictures from profile-pictures/cats folder
        $catGifs = $this->profilePictureService->getPicturesByCategory('cats');
        
        return view('admin.users.create', compact('catGifs'));
    }

    public function store(Request $request)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        // Normalize email to lowercase before validation (emails are case-insensitive)
        if ($request->has('email') && $request->input('email')) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }
        
        // Determine if this is a child account
        $isChild = $request->has('parent_id') && $request->parent_id;
        
        // Build validation rules
        $rules = [
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,user',
            'cat_gif' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:users,id',
            'is_viewable' => 'nullable|boolean',
            'account_status' => 'nullable|in:pending,approved,rejected',
        ];
        
        // Email validation: required and unique for parents, nullable for children
        if ($isChild) {
            $rules['email'] = 'nullable|string|email|max:255';
            // For children, username is the display name and must be unique per parent
            $rules['username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->where(function ($query) use ($request) {
                    return $query->where('parent_id', $request->parent_id);
                }),
            ];
        } else {
            $rules['email'] = 'required|string|email|max:255|unique:users';
        }
        
        $validated = $request->validate($rules);

        // Validate parent_id if provided
        if ($isChild) {
            $parent = User::findOrFail($validated['parent_id']);
            // Ensure parent is not a child itself
            if ($parent->parent_id) {
                return back()->withErrors(['parent_id' => __('admin.user_already_child')]);
            }
            // Ensure parent is not an admin creating child of admin
            if ($parent->isAdmin()) {
                return back()->withErrors(['parent_id' => __('admin.cannot_create_child_for_admin')]);
            }
        }

        $profileData = $this->mapProfilePicture($validated['cat_gif'] ?? null);

        // Slug will be auto-generated by the model observer
        User::create([
            'email' => $isChild ? null : $validated['email'], // NULL for children, required for parents
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_viewable' => $validated['is_viewable'] ?? true,
            'account_status' => $validated['account_status'] ?? 'approved',
        ] + $profileData);

        return redirect()->route('admin.users.index')
            ->with('success', __('messages.user_created'));
    }

    public function show(User $user)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);
        
        // Get all profile pictures from profile-pictures/cats folder
        $catGifs = $this->profilePictureService->getPicturesByCategory('cats');
        
        $isSelfEdit = auth()->user()->id === $user->id;
        
        // Get current PIN for display (for both self-edit and admin edit)
        $currentPin = $user->getViewPin();
        
        return view('admin.users.edit', compact('user', 'catGifs', 'isSelfEdit', 'currentPin'));
    }
    
    public function editProfile()
    {
        $user = auth()->user();
        $isSelfEdit = true;
        
        // Get all profile pictures from profile-pictures/cats folder
        $catGifs = $this->profilePictureService->getPicturesByCategory('cats');
        
        // Get current PIN for display (same as children edit)
        $currentPin = $user->getViewPin();
        
        return view('admin.users.edit', compact('user', 'catGifs', 'isSelfEdit', 'currentPin'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        
        // Normalize email to lowercase before validation (emails are case-insensitive)
        if ($request->has('email') && $request->input('email')) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }
        
        $isSelfEdit = auth()->user()->id === $user->id;
        $isAdmin = auth()->user()->isAdmin();
        
        // Determine if this is/will be a child account
        $isChild = ($request->has('parent_id') && $request->parent_id) || $user->parent_id;
        $newParentId = $request->parent_id ?? $user->parent_id;
        
        // Build validation rules
        $rules = [
            'username' => ['required', 'string', 'max:255'],
            'password' => 'nullable|string|min:8',
            'cat_gif' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:users,id',
            'is_viewable' => 'nullable|boolean',
            'account_status' => 'nullable|in:pending,approved,rejected',
        ];
        
        // Add PIN validation rules if user is a parent account (or will be)
        $isParent = !$isChild;
        if ($isParent) {
            $usePin = $this->getPinToggleState($request);
            $rules = $this->addPinValidationRules($rules, $usePin);
        }
        
        // Email validation: required and unique for parents, nullable for children
        if ($isChild) {
            $rules['email'] = ['nullable', 'string', 'email', 'max:255'];
            // For children, username is the display name and must be unique per parent
            $rules['username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')
                    ->where(function ($query) use ($newParentId) {
                        return $query->where('parent_id', $newParentId);
                    })
                    ->ignore($user->id),
            ];
        } else {
            $rules['email'] = ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)];
        }
        
        // Only admins can change roles (and can't change their own role)
        if ($isAdmin && !$isSelfEdit) {
            $rules['role'] = 'required|in:admin,user';
        }
        
        // Validate parent_id if provided
        if ($isAdmin && !$isSelfEdit && $request->has('parent_id') && $request->parent_id) {
            $parent = User::find($request->parent_id);
            if ($parent) {
                // Ensure parent is not a child itself
                if ($parent->parent_id) {
                    return back()->withErrors(['parent_id' => __('admin.user_already_child')]);
                }
                // Ensure parent is not an admin
                if ($parent->isAdmin()) {
                    return back()->withErrors(['parent_id' => __('admin.cannot_create_child_for_admin')]);
                }
                // Prevent users from being their own parent
                if ($parent->id === $user->id) {
                    return back()->withErrors(['parent_id' => __('admin.user_cannot_be_own_parent')]);
                }
            }
        }

        $validated = $request->validate($rules);

        $profileData = $this->mapProfilePicture($validated['cat_gif'] ?? null);

        // Slug will be auto-generated by the model observer
        $updateData = [
            'username' => $validated['username'],
        ] + $profileData;
        
        // Email: set to NULL for children, required value for parents
        if ($isChild) {
            $updateData['email'] = null; // Children don't have emails
        } else {
            $updateData['email'] = $validated['email']; // Required for parents
        }
        
        // Only admins can change roles, and they can't change their own role
        if ($isAdmin && !$isSelfEdit && isset($validated['role'])) {
            $updateData['role'] = $validated['role'];
        }
        
        // Only admins can change parent_id, is_viewable, and account_status
        if ($isAdmin && !$isSelfEdit) {
            if (isset($validated['parent_id'])) {
                $updateData['parent_id'] = $validated['parent_id'];
            }
            if (isset($validated['is_viewable'])) {
                $updateData['is_viewable'] = $validated['is_viewable'];
            }
            if (isset($validated['account_status'])) {
                $updateData['account_status'] = $validated['account_status'];
            }
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);
        
        // Handle PIN update for parent accounts (both self-edit and admin edit)
        if ($isParent) {
            $usePin = $this->getPinToggleState($request);
            $this->handlePinUpdate($user, $usePin, $validated['pin'] ?? null);
        }

        // Redirect based on context
        if ($isSelfEdit) {
            return redirect()->route('admin.dashboard')
                ->with('success', __('messages.profile_updated'));
        }

        return redirect()->route('admin.users.index')
            ->with('success', __('messages.user_updated'));
    }
    
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();
        $usePin = $this->getPinToggleState($request);
        $useAdminPin = $this->getAdminPinToggleState($request);

        // Map cat_gif to profile_picture with category
        $profileData = $this->mapProfilePicture($validated['cat_gif'] ?? null);
        
        // Slug will be auto-generated by the model observer
        $updateData = [
            'email' => $validated['email'],
            'username' => $validated['username'],
        ] + $profileData;

        // Only allow parents (users without parent_id) to set appears_in_profile_selection
        // Checkbox is inverted: checked = hide (false), unchecked = show (true)
        if ($user->parent_id === null) {
            $updateData['appears_in_profile_selection'] = !($request->has('appears_in_profile_selection') && $request->appears_in_profile_selection == '1');
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Handle PIN update
        $this->handlePinUpdate($user, $usePin, $validated['pin'] ?? null);
        $this->handleNamedPinUpdate($user, $useAdminPin, $validated['admin_pin'] ?? null, 'admin_pin');

        return redirect()->route('admin.dashboard')
            ->with('success', __('admin.profile_updated_successfully'));
    }

    /**
     * Convert a child account to an independent parent account.
     */
    public function convertToParent(ConvertChildToParentRequest $request, User $user)
    {
        // Verify user is a child account
        if ($user->parent_id === null) {
            return redirect()->route('admin.users.edit', $user)
                ->withErrors(['error' => __('admin.cannot_convert_parent_account')]);
        }

        $validated = $request->validated();

        // Start database transaction
        \DB::transaction(function () use ($user, $validated) {
            // Update user to become a parent
            $user->update([
                'parent_id' => null, // Remove parent relationship
                'email' => $validated['email'], // Set required email
                'password' => Hash::make($validated['password']), // Set new password
            ]);

            // Remove all DeviceChildVisibility records for this user
            // (they're no longer a child, so they shouldn't appear in child visibility settings)
            DeviceChildVisibility::where('child_user_id', $user->id)->delete();
        });

        return redirect()->route('admin.users.edit', $user)
            ->with('success', __('admin.child_converted_to_parent', ['name' => $user->username]));
    }

    public function destroy(User $user)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $this->authorize('delete', $user);
        
        $user->delete();
        return redirect()->route('admin.users.index')
            ->with('success', __('messages.user_deleted'));
    }
}
