<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Team\InviteTeamMember;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Support\InputCase;
use Illuminate\Support\Facades\DB;

/**
 * Add someone to the business, and optionally invite them in.
 *
 * The staff record and the invitation are created together in one
 * transaction. They are one act to the person doing it, and a staff row saved
 * without the invitation it promised is a colleague who never hears from you —
 * visible only when they ask why they have no login.
 */
class CreateStaffMember
{
    public function __construct(private InviteTeamMember $inviter) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Tenant $tenant, User $actor, array $data): Staff
    {
        $data = InputCase::apply($data, [
            'first_name', 'middle_name', 'last_name', 'preferred_name',
            'job_title', 'bio', 'emergency_contact_name', 'emergency_contact_relationship',
        ]);

        $role = Role::query()->findOrFail($data['role_id']);

        [$staff, $shouldInvite] = DB::transaction(function () use ($tenant, $actor, $data, $role) {
            $staff = Staff::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'preferred_name' => $data['preferred_name'] ?? null,
                'pronouns' => $data['pronouns'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'employee_ref' => $data['employee_ref'] ?? null,
                'bio' => $data['bio'] ?? null,
                'avatar_path' => $data['avatar_path'] ?? null,

                'email' => $data['email'],
                'work_email' => $data['work_email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'phone_type' => $data['phone_type'] ?? null,
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,

                // Both are written: `role` keeps the string every existing
                // caller reads, `role_id` is what permissions resolve through.
                'role' => $role->key,
                'role_id' => $role->id,

                'location_id' => $data['location_id'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'provider_type' => $data['provider_type'] ?? null,
                'specialities' => $data['specialities'] ?? null,
                'provides_services' => ($data['provider_type'] ?? null) !== 'front-desk',

                'is_active' => ($data['account_status'] ?? 'active') === 'active',
                'membership_status' => $data['account_status'] ?? 'active',
                'login_enabled' => (bool) ($data['login_enabled'] ?? false),
                'invite_status' => 'not-sent',
            ]);

            if (! empty($data['service_ids'])) {
                $staff->services()->sync($data['service_ids']);
            }

            AuditLog::record('staff.created', $actor, $staff, [], [
                'name' => $staff->displayName(),
                'role' => $role->key,
                'location_id' => $staff->location_id,
            ], $staff->displayName());

            /**
             * Whether to invite is decided inside the transaction but acted on
             * outside it: the invitation queues an email, and a job dispatched
             * from inside a transaction can run before the commit and find
             * nothing to send.
             */
            $shouldInvite = ($data['login_enabled'] ?? false) && ($data['send_invitation'] ?? false);

            return [$staff, $shouldInvite];
        });

        if ($shouldInvite) {
            $this->invite($tenant, $actor, $staff, $data['invitation_message'] ?? null);
        }

        return $staff->fresh();
    }

    private function invite(Tenant $tenant, User $actor, Staff $staff, ?string $message): void
    {
        // An address already inside the business needs no invitation, and
        // sending one would offer a second way into an account that exists.
        if (User::where('email', $staff->email)->where('tenant_id', $tenant->getTenantKey())->exists()) {
            return;
        }

        if ($this->inviter->existingPendingInvitation($tenant, $staff->email)) {
            return;
        }

        $invitation = $this->inviter->create($tenant, $actor, [
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'email' => $staff->email,
            'role' => $staff->role,
            'job_title' => $staff->job_title,
            'location_id' => $staff->location_id,
            'message' => $message,
            'service_ids' => $staff->services()->pluck('services.id')->all(),
        ]);

        // Bind the two, so acceptance completes this record rather than
        // creating a second person with the same name.
        $invitation->forceFill(['staff_id' => $staff->id])->save();
        $staff->forceFill(['invite_status' => 'sent'])->save();

        AuditLog::record('staff.invitation_sent', $actor, $staff, [], [
            'email' => $staff->email,
        ], $staff->displayName());
    }
}
