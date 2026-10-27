<?php

namespace App\Http\Controllers\Api\V1\Buyer;

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FlashSaleReminderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\VoucherWalletController;
use App\Http\Controllers\WishlistController;
use App\Models\FlashSale;
use App\Models\UserAddress;
use App\Models\Voucher;
use App\Support\ApiJson;
use Illuminate\Http\JsonResponse;

/** My Account (B11, B14): profile, security, addresses, notifications, wishlist, vouchers, followed stores. */
class AccountController extends Controller
{
    use CallsWeb;

    // ── Profile ──────────────────────────────────────────────────

    public function profile(): JsonResponse
    {
        $user = auth()->user();
        return response()->json(['user' => AuthController::userPayload($user) + [
            'bio' => $user->bio, 'sex' => $user->sex, 'birthday' => $user->birthday?->format('Y-m-d'), 'username' => $user->username,
            'pending_email' => $user->pending_email, 'pending_phone' => $user->pending_phone,
            'unread_notifications' => $user->notifications()->whereNull('read_at')->count(),
            'cart_count' => \App\Models\CartItem::where('user_id', $user->id)->count(),
        ]]);
    }

    /** name, bio, sex (Male/Female), birthday, avatar (photo). */
    public function updateProfile(): JsonResponse
    {
        return $this->withUser($this->web(ProfileController::class, 'update'));
    }

    public function updateAccount(): JsonResponse
    {
        return $this->withUser($this->web(ProfileController::class, 'updateAccount'));
    }

    /** current_password, password, password_confirmation. */
    public function changePassword(): JsonResponse
    {
        return $this->actionJson($this->web(ProfileController::class, 'changePassword'), \App\Http\Controllers\Api\V1\PasswordCodeController::sentFlag());
    }

    /** New email or phone: a 6-digit code is sent; confirm with verify (code). */
    public function changeEmail(): JsonResponse  { return $this->withUser($this->web(ProfileController::class, 'changeEmail')); }
    public function changePhone(): JsonResponse  { return $this->withUser($this->web(ProfileController::class, 'changePhone')); }
    public function verifyContact(): JsonResponse { return $this->withUser($this->web(ProfileController::class, 'verifyContact')); }
    public function resendContact(): JsonResponse { return $this->withUser($this->web(ProfileController::class, 'resendContactCode')); }
    public function cancelContact(): JsonResponse { return $this->withUser($this->web(ProfileController::class, 'cancelContactChange')); }

    private function withUser(mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + ['user' => $this->profile()->getData(true)['user']]);
        }
        return $json;
    }

    // ── Addresses ────────────────────────────────────────────────

    public function addresses(): JsonResponse
    {
        $d = $this->pageData($this->web(ProfileController::class, 'addresses'));
        return response()->json(['addresses' => $d['addresses']->map(fn ($a) => ApiJson::address($a))->values()]);
    }

    /** label, full_name, phone, region, province, city, barangay, address_line, zip, *_code, is_default. */
    public function addAddress(): JsonResponse
    {
        return $this->withAddresses($this->web(AddressController::class, 'store'));
    }

    public function updateAddress(UserAddress $address): JsonResponse
    {
        return $this->withAddresses($this->web(AddressController::class, 'update', ['address' => $address]));
    }

    public function deleteAddress(UserAddress $address): JsonResponse
    {
        return $this->withAddresses($this->web(AddressController::class, 'destroy', ['address' => $address]));
    }

    public function defaultAddress(UserAddress $address): JsonResponse
    {
        return $this->withAddresses($this->web(AddressController::class, 'setDefault', ['address' => $address]));
    }

    private function withAddresses(mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->addresses()->getData(true));
        }
        return $json;
    }

    // ── Notifications ────────────────────────────────────────────

    public function notifications(): JsonResponse
    {
        $d = $this->pageData($this->web(ProfileController::class, 'notifications'));

        return response()->json(ApiJson::page($d['notifications'], fn ($n) => [
            'id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'type' => $n->type,
            'link' => $n->link ? $this->webPath($n->link) : null, 'read' => $n->read_at !== null, 'created_at' => $n->created_at?->toIso8601String(),
        ]) + ['unread' => auth()->user()->notifications()->whereNull('read_at')->count()]);
    }

    /** Open one: it is marked read; "redirect" says what it is about (e.g. /profile/orders/12). */
    public function openNotification(int $id): JsonResponse
    {
        return $this->actionJson($this->web(ProfileController::class, 'openNotification', ['id' => $id]));
    }

    public function readAllNotifications(): JsonResponse
    {
        return $this->actionJson($this->web(ProfileController::class, 'readAllNotifications'));
    }

    // ── Wishlist, vouchers, followed stores, flash sale reminders ─

    public function wishlist(): JsonResponse
    {
        $d = $this->pageData($this->web(WishlistController::class, 'index'));
        return response()->json(['items' => collect($d['items'])->map(fn ($w) => $w->product ? ApiJson::productCard($w->product) : null)->filter()->values()]);
    }

    /** state: on / off (or leave it out to switch). */
    public function toggleWish(int $productId): JsonResponse
    {
        request()->headers->set('Accept', 'application/json');
        $json = $this->actionJson($this->web(WishlistController::class, 'toggle', ['productId' => $productId]));
        $wished = (bool) $json->getData(true)['wished'];

        return response()->json(['wished' => $wished, 'message' => $wished ? 'Added to your wishlist.' : 'Removed from your wishlist.']);
    }

    public function vouchers(): JsonResponse
    {
        $d = $this->pageData($this->web(VoucherWalletController::class, 'index'));

        return response()->json([
            'claimed'   => $d['claims']->map(fn ($c) => ApiJson::voucher($c->voucher) + ['state' => $c->state])->values(),
            'claimable' => $d['claimable']->map(fn ($v) => ApiJson::voucher($v, false))->values(),
        ]);
    }

    public function claimVoucher(Voucher $voucher): JsonResponse
    {
        return $this->actionJson($this->web(VoucherWalletController::class, 'claim', ['voucher' => $voucher]));
    }

    public function following(): JsonResponse
    {
        $d = $this->pageData($this->web(StoreController::class, 'following'));
        return response()->json(['stores' => $d['follows']->map(fn ($f) => ApiJson::store($f->seller))->values()]);
    }

    public function toggleFollow(int $sellerId): JsonResponse
    {
        $json = $this->actionJson($this->web(StoreController::class, 'toggleFollow', ['sellerId' => $sellerId]));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + ['following' => \App\Models\StoreFollow::where('user_id', auth()->id())->where('seller_id', $sellerId)->exists()]);
        }
        return $json;
    }

    public function remindFlashSale(FlashSale $flashSale): JsonResponse
    {
        $json = $this->actionJson($this->web(FlashSaleReminderController::class, 'toggle', ['flashSale' => $flashSale]));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + ['reminded' => $flashSale->reminders()->where('user_id', auth()->id())->exists()]);
        }
        return $json;
    }
}
