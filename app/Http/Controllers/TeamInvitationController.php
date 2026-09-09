<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Team\InviteTeamMember;
use App\Mail\TeamInvitationMail;
use App\Models\Location;
use App\Models\Service;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Team invitations for the active tenant.
 *
 * JSON endpoints rather than form posts: the team screen adds a colleague and
 * shows them in the list without a page change, which is what makes the
 * invitation feel like it was sent rather than queued behind a Continue button
 * the user has not pressed yet.
 */
class TeamInvitationController extends Controller
{
    /**
     * Invitations an inviter may send per hour.
     *
     * A compromised owner account is otherwise a free bulk mailer sending from
     * a domain with our reputation, and every one of those emails names a real
     * business. High enough that setting up a large salon in one sitting never
     * meets it.
     */
    private const SEND_LIMIT_PER_HOUR = 20;

    /** Resends are cheaper to abuse, so they are held tighter per invitation. */
    private const RESEND_LIMIT_PER_HOUR = 5;

    public function store(Request $request, InviteTeamMember $inviter): JsonResponse
    {
        $this->authorize('create', TeamInvitation::class);

        $tenant = $request->user()->tenant;
        $data = $this->validated($request);

        $this->rateLimit(
            'team-invite:'.$request->user()->id,
            self::SEND_LIMIT_PER_HOUR,
            'You have sent a lot of invitations in a short time. Try again in an hour.',
        );

        /**
         * Duplicate protection, per the spec.
         *
         * Returns 409 with the existing invitation attached so the screen can
         * offer Resend and Cancel against it, rather than a bare validation
         * error that leaves the user unable to act on what already exists.
         */
        if ($existing = $inviter->existingPendingInvitation($tenant, $data['email'])) {
            return response()->json([
                'message' => 'An invitation has already been sent to this email address.',
                'data' => $this->present($existing),
            ], 409);
        }

        /**
         * Someone already in this business cannot be invited into it again.
         *
         * Not covered by the pending-invitation check: they may have joined
         * long ago, or been added as the owner, in which case no invitation
         * row exists at all.
         */
        if ($this->alreadyAMember($request, $data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'This person is already part of your team.',
            ]);
        }

        $invitation = $inviter->create($tenant, $request->user(), $data);

        return response()->json(['data' => $this->present($invitation)], 201);
    }

    public function resend(Request $request, TeamInvitation $invitation, InviteTeamMember $inviter): JsonResponse
    {
        $this->authorize('resend', $invitation);

        if (! $invitation->isResendable()) {
            throw ValidationException::withMessages([
                'invitation' => 'Only pending or expired invitations can be sent again.',
            ]);
        }

        $this->rateLimit(
            'team-invite-resend:'.$invitation->id,
            self::RESEND_LIMIT_PER_HOUR,
            'This invitation has been resent several times already. Try again in an hour.',
        );

        $inviter->resend($invitation);

        return response()->json(['data' => $this->present($invitation->fresh())]);
    }

    public function revoke(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $this->authorize('revoke', $invitation);

        if ($invitation->effectiveStatus() === TeamInvitation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                // Revoking would leave someone signed in to a business the
                // list says they are not part of. Removing a member who has
                // already joined is a different action from cancelling an
                // invitation, and is not this endpoint.
                'invitation' => 'This person has already joined. Remove them from the team instead.',
            ]);
        }

        $invitation->revoke();

        return response()->json(['data' => $this->present($invitation->fresh())]);
    }

    /**
     * @return array{first_name: string, last_name: string, email: string, role: string, job_title: string|null, location_id: int|null, message: string|null, service_ids: array<int>|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(['administrator', 'manager', 'front-desk', 'service-provider'])],
            'job_title' => ['nullable', 'string', 'max:100'],
            /**
             * Scoped to the tenant's own locations.
             *
             * `exists:locations,id` alone would accept another business's
             * location id and quietly file the new member against it.
             */
            'location_id' => [
                'nullable',
                Rule::exists(Location::class, 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'message' => ['nullable', 'string', 'max:500'],
            'service_ids' => ['nullable', 'array'],
            /**
             * Scoped to the tenant's own services, for the same reason as
             * location_id: a bare exists rule would accept another business's
             * service id and make the new member bookable for it.
             */
            'service_ids.*' => [
                Rule::exists(Service::class, 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ], [
            'role.in' => 'Choose a role for this person. Only the account owner can hold the Owner role.',
        ]);

        $data['email'] = InviteTeamMember::normalizeEmail($data['email']);

        return $data;
    }

    private function alreadyAMember(Request $request, string $email): bool
    {
        return User::where('email', $email)
            ->where('tenant_id', $request->user()->tenant_id)
            ->exists();
    }

    private function rateLimit(string $key, int $perHour, string $message): void
    {
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw ValidationException::withMessages(['email' => $message])->status(429);
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * What the team list needs, and nothing more.
     *
     * Never the token or its hash: this response is read by a browser, and the
     * whole security model rests on the token existing only in the recipient's
     * inbox.
     *
     * @return array<string, mixed>
     */
    private function present(TeamInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'first_name' => $invitation->first_name,
            'last_name' => $invitation->last_name,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'role_label' => TeamInvitationMail::roleLabel($invitation->role),
            'job_title' => $invitation->job_title,
            'service_ids' => $invitation->services->pluck('id'),
            'status' => $invitation->effectiveStatus(),
            'status_label' => $invitation->statusLabel(),
            'can_resend' => $invitation->isResendable(),
            'sent_at' => $invitation->sent_at?->toIso8601String(),
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ];
    }
}
