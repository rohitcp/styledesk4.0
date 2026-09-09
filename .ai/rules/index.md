# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/{Support/ActivityStream.php,Http/Controllers/ActivityController.php},resources/js/components/ActivityFeed.vue,resources/views/activity/index.blade.php | .ai/rules/activity.md |
| app/{Models/BackofficeAdmin.php,Http/Controllers/Backoffice/PasswordResetController.php},config/auth.php | .ai/rules/backoffice.md |
| app/{Http/Controllers/ClientMembershipController.php,Support/ClientMemberships.php},resources/views/clients/partials/_membership.blade.php | .ai/rules/clients-partials.md |
| app/Http/Controllers/Backoffice/ClientController.php,resources/views/backoffice/**,resources/views/components/backoffice/** | .ai/rules/components-backoffice.md |
| resources/js/components/{BookingBuilder.vue,MultiSelect.vue} | .ai/rules/components.md |
| app/{Services/MembershipImageSync.php,Http/Controllers/MembershipImageController.php},resources/js/components/BookingBuilder.vue | .ai/rules/controllers-js-components.md |
| app/{Support/StaffUtilization.php,Support/ResourceUtilization.php,Http/Controllers/StaffUtilizationController.php},resources/js/{bubble-board.js,components/StaffUtilization.vue,components/ResourceUtilization.vue} | .ai/rules/controllers-js.md |
| app/{Models/StaffShift.php,Support/SchedulePeriod.php,Http/Controllers/**}, app/{Models/Booking.php,Models/BookingPayment.php,Support/BookingTotals.php,Http/Controllers/BookingController.php}, app/{Models/Booking.php,Models/BookingLead.php,Support/BookingAvailability.php,Http/Controllers/BookingController.php,Http/Controllers/BookingLeadController.php}, app/{Models/BookingPaymentLink.php,Http/Controllers/PaymentLinkController.php,Http/Controllers/BookingController.php}, app/{Models/BookingStatusChange.php,Models/ReasonCode.php,Support/BookingStatusHistory.php,Http/Controllers/BookingStatusController.php}, app/{Models/ClientMembership.php,Models/MembershipCredit.php,Models/MembershipPayment.php,Support/MembershipPurchase.php,Http/Controllers/MembershipSaleController.php} | .ai/rules/controllers.md |
| resources/js/components/{MultiSelect.vue,SingleSelect.vue},resources/css/styledesk.css | .ai/rules/css.md |
| ** | .ai/rules/general.md |
| app/Http/Controllers/BookingQuoteController.php,resources/js/components/{BookingBuilder.vue,PaymentPanel.vue} | .ai/rules/http-controllers-js-components.md |
| app/Models/Client.php,database/seeders/**,app/Http/Controllers/ClientController.php | .ai/rules/http-controllers.md |
| app/{Support/ResourceAvailability.php,Support/ResourceAllocator.php,Http/Controllers/ResourceAvailabilityController.php},resources/js/components/ResourceAvailability.vue, app/{Support/ActivityStream.php,Http/Controllers/ActivityController.php},resources/js/components/ActivityPanel.vue | .ai/rules/js-components.md |
| resources/js/{listing-filters.js,data-grid.js} | .ai/rules/js.md |
| lang/** | .ai/rules/lang.md |
| app/{Models/MembershipSettings.php,Http/Controllers/Settings/MembershipSettingsController.php},config/membership.php,resources/views/settings/membership/** | .ai/rules/membership.md |
| app/{Models/Tenant.php,Http/Middleware/EnsureBusinessIsActive.php,Providers/FortifyServiceProvider.php,Http/Controllers/OnboardingController.php} | .ai/rules/middleware-controllers.md |
| app/{Models/BackofficeAdmin.php,Models/BackofficeAuditLog.php,Models/BackofficeLoginCode.php,Http/Controllers/Backoffice/**,Http/Middleware/AuthenticateBackoffice.php,Support/BackofficeVerification.php},routes/backoffice.php,config/backoffice.php | .ai/rules/middleware.md |
| app/{Models/ClientActivity.php,Support/ClientActivityLog.php}, app/{Models/ClientPaymentMethod.php,Payments/VaultsCards.php,Payments/StripeGateway.php,Payments/PaymentGatewayManager.php} | .ai/rules/models.md |
| app/{Models/LoyaltySettings.php,Models/ClientLoyaltyPoint.php,Support/LoyaltyPoints.php,Http/Controllers/ClientLoyaltyController.php,Http/Controllers/Settings/LoyaltySettingsController.php},config/loyalty.php,resources/views/settings/loyalty/**,resources/views/clients/partials/_rewards.blade.php | .ai/rules/partials.md |
| app/{Models/BookingReview.php,Models/ReviewSettings.php,Support/ReviewRequests.php,Jobs/SendReviewRequest.php,Http/Controllers/ReviewController.php,Http/Controllers/Settings/ReviewSettingsController.php},config/reviews.php,resources/views/reviews/** | .ai/rules/reviews.md |
| resources/views/settings/index.blade.php,resources/css/styledesk.css | .ai/rules/settings-css.md |
| resources/views/settings/**/show.blade.php,resources/views/components/settings/card.blade.php | .ai/rules/settings.md |
| app/{Support/ClientServiceHistory.php,Models/Client.php,Http/Controllers/ClientController.php}, app/{Support/MembershipCredits.php,Models/MembershipCreditRedemption.php,Http/Controllers/BookingQuoteController.php} | .ai/rules/support-controllers.md |
| app/Support/ClientVisitSummary.php | .ai/rules/support.md |
| tests/** | .ai/rules/tests.md |
| app/{Models/MembershipPlan.php,Models/MembershipPlanService.php,Http/Controllers/MembershipPlanController.php},resources/views/membership/** | .ai/rules/views-membership.md |
| app/Http/Controllers/Settings/**,resources/views/settings/** | .ai/rules/views-settings.md |
| resources/views/**/*.blade.php | .ai/rules/views.md |
