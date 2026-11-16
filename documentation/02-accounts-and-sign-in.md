# 2 · Accounts and signing in

## Who can use ALVY

Everyone who uses ALVY has an account, and every account has a **role** that decides what they
can see:

- **Buyers** shop. They can start buying as soon as they confirm their email.
- **Sellers** run a store. An admin must approve the store first. Until then the seller only
  sees their application status (they may prepare products as drafts while they wait).
- **Sorting centers** are logistics companies. An admin approves them too.
- **Riders** deliver for a sorting center. Usually the sorting center creates their account; the
  rider then gets an email to set their own password. Riders sign in to what the code calls the
  *courier* portal.
- **Admins** are ALVY staff.

An admin can also **suspend** or **deactivate** an account; it then can't sign in, and is told why.

## Each part of ALVY has its own door

ALVY has five "portals", each with its own address and login page:

| Portal | Address | For |
|---|---|---|
| Shop | `/` | buyers (and guests who are just looking) |
| Seller Centre | `/seller` | sellers |
| Sorting center | `/sc` | logistics companies |
| Rider portal | `/courier` | riders |
| Admin | `/admin` | ALVY staff |

When someone signs in, ALVY sends them to the right portal for their role. A seller who logs in on
the shop's login page lands in the Seller Centre.

There is an optional setting (`SESSION_PER_PORTAL=true`) that gives each portal its own separate
login. With it on, you can be signed in as a buyer in one tab and as a seller in another tab of the
same browser. This is handy for testing. With it off, the whole site shares one login.

## Signing up

Each kind of account has its own sign-up form, but they all work the same way:

1. **Fill in the form.** Names, birthdays, phone numbers, plate numbers and passwords are checked
   with the same rules on every form, so a phone number valid on one is valid on all.
2. **Upload an ID** where needed. ALVY asks an AI to check that the picture really is a government
   ID. If the AI can't be reached, the upload is still accepted and marked for an admin to look at,
   so sign-up never gets stuck.
3. **Address inside the service area.** Addresses are picked from dropdowns (region → province →
   town → barangay) and must be in Laguna.
4. **Confirm the email.** ALVY emails a 6-digit code. The account only becomes real after the code
   is entered.
5. **Wait for approval** (sellers, sorting centers, rider applicants). Buyers skip this step.

**Continue with Google** works too. If the Google email already has an ALVY account, it signs in.
If not, the sign-up form opens with the email filled in and locked, and the email-code step is
skipped, because Google has already confirmed the address.

**Riders added by a sorting center** don't fill in a sign-up form. The company enters their details
on its Riders page, and ALVY emails the rider a link to set a password. The link works for 24 hours
and only once; the company can send a new one.

## Signing in

When someone signs in, ALVY checks, in this order:

1. Is the email and password right? (Too many wrong tries are blocked for a while.)
2. Is the account suspended or deactivated? Then it's refused, with the reason.
3. Did they finish confirming their email? If not, they go back to the code step.
4. Is it a seller, rider applicant or sorting center that isn't approved yet? Then they see their
   application status instead of the portal.
5. Otherwise they're in, and go to their portal. If they had pressed *Add to cart* or *Buy now*
   before signing in, ALVY finishes that for them now.

For safety, an account is signed out after a week without use, or a week after signing in,
whichever comes first. After logging out, the browser's Back button can't show the old pages.

## Passwords

- **Forgot password:** ALVY emails a one-time link to set a new password. The reply is always the
  same ("if the email exists, we sent a link"), so nobody can use this form to find out who has an
  account. After the reset, every place the account was signed in is signed out.
- **Changing your password while signed in:** you type your current password and a new one, then
  ALVY emails you a 6-digit code (or texts it, if SMS is set up). The new password only takes effect
  after you type the code. Until then the old one keeps working.
- Changing your email or phone number on My Account is confirmed with a code the same way.

## Private files

Uploads like IDs, permits, licences and proof-of-delivery photos are not public. They are kept in a
private folder and shown only through a special link that expires after a while. The page that
shows the link first checks that the person is allowed to see the file.

## The activity log

Every time anyone changes something (saves a form, presses a button), ALVY writes down who did it,
what it was, whether it worked, and from which IP address. Passwords and form contents are never
saved. Admins can read the whole log; each sorting center can read its own.

## Where to find it in the code

| What | File |
|---|---|
| Roles and the "is this a seller?" checks | [User](../app/Models/User.php) |
| Signing in | [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php) |
| Buyer sign-up | [RegisteredUserController](../app/Http/Controllers/Auth/RegisteredUserController.php) |
| Seller sign-up, Become a Seller | [Seller/RegistrationController](../app/Http/Controllers/Seller/RegistrationController.php), [Seller/SellerApplicationController](../app/Http/Controllers/Seller/SellerApplicationController.php), [SellerApplicationRules](../app/Support/SellerApplicationRules.php) |
| Sorting center sign-up | [SortingCenter/RegistrationController](../app/Http/Controllers/SortingCenter/RegistrationController.php) |
| Rider applicant sign-up (`/become-courier`) | [Courier/RegistrationController](../app/Http/Controllers/Courier/RegistrationController.php), [CourierReview](../app/Services/CourierReview.php) |
| Riders added by a company, activation links | [StaffAccounts](../app/Services/StaffAccounts.php), [AccountActivationController](../app/Http/Controllers/Shared/AccountActivationController.php) |
| Email code at sign-up | [LoginOtp](../app/Services/LoginOtp.php) |
| Google | [GoogleAuthController](../app/Http/Controllers/Auth/GoogleAuthController.php), [GoogleSignup](../app/Support/GoogleSignup.php) |
| Shared form rules | [PersonRules](../app/Support/PersonRules.php) |
| ID check | [GovernmentId](../app/Rules/GovernmentId.php), [IdDocumentChecker](../app/Services/IdCheck/IdDocumentChecker.php) |
| Laguna-only addresses | [InServiceArea](../app/Rules/InServiceArea.php), [ServiceArea](../app/Support/ServiceArea.php) |
| Portals and separate logins | [Portal](../app/Support/Portal.php), [StartPortalSession](../app/Http/Middleware/StartPortalSession.php), [PortalHandoff](../app/Http/Middleware/PortalHandoff.php) |
| Who may open which portal | [RoleMiddleware](../app/Http/Middleware/RoleMiddleware.php), [BuyerOnly](../app/Http/Middleware/BuyerOnly.php), [IsSortingCenter](../app/Http/Middleware/IsSortingCenter.php), [EnsureSellerApproved](../app/Http/Middleware/EnsureSellerApproved.php) (names set in [bootstrap/app.php](../bootstrap/app.php)) |
| Sign-out after a week | [EnforceLoginTimeLimit](../app/Http/Middleware/EnforceLoginTimeLimit.php) |
| Forgot password | [PasswordResetLinkController](../app/Http/Controllers/Auth/PasswordResetLinkController.php), [NewPasswordController](../app/Http/Controllers/Auth/NewPasswordController.php) |
| Change password with a code | [PasswordChangeController](../app/Http/Controllers/Shared/PasswordChangeController.php), [PasswordChangeCode](../app/Services/PasswordChangeCode.php) |
| Account pages layout | [account-shell](../resources/views/components/account-shell.blade.php), [AccountNav](../app/Support/AccountNav.php) |
| Private files | [ProtectedFile](../app/Support/ProtectedFile.php) |
| Activity log | [LogUserActivity](../app/Http/Middleware/LogUserActivity.php), [ActivityLog](../app/Models/ActivityLog.php) |
