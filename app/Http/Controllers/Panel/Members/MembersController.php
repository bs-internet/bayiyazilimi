<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Members;

use App\Enums\BillingType;
use App\Enums\Shop\CollectionType;
use App\Enums\Shop\CustomerType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Members\ChangeMemberEmailRequest;
use App\Http\Requests\Panel\Members\CreateMemberRequest;
use App\Http\Requests\Panel\Members\UpdateMemberStatusRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Members\MemberResource;
use App\Http\Resources\Panel\Members\MemberSessionResource;
use App\Http\Resources\Panel\Tools\Activity\ActivityResource;
use App\Models\DealerGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Panel\Members\MemberService;
use App\Services\Panel\TwoFactorResetService;
use App\Support\Ledger\CreditTerms;
use App\Support\Ledger\LedgerPresenter;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    public function index(Request $request, MemberService $service): Response
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
            'group' => $request->string('group')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        return Inertia::render('panel/members/index', [
            'users' => PaginatedResource::make($service->paginate($filters), MemberResource::class, $request),
            'filters' => $filters,
            'customerTypes' => CustomerType::options(),
            'dealerGroups' => $this->dealerGroupOptions(),
            'statuses' => $this->statusOptions(),
            'currency' => Money::baseCode(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/members/create', [
            'statuses' => $this->statusOptions(),
            'dealerGroups' => $this->dealerGroupOptions(),
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function store(CreateMemberRequest $request, MemberService $service): RedirectResponse
    {
        /** @var User $causer */
        $causer = $request->user();

        $user = $service->create($request->validated(), $causer);

        return to_route('panel.members.show', $user)
            ->with('toast', [
                'type' => 'success',
                'message' => __('Bayi oluşturuldu ve hoş geldin e-postası gönderildi.'),
            ]);
    }

    public function show(Request $request, User $user, MemberService $service, LedgerPresenter $ledger): Response
    {
        $this->ensureMember($user);

        // Cari sekmesi: yalnız cariyi görme yetkisi olana.
        $account = $user->account_id !== null ? $user->account : null;
        $canLedger = $account !== null && $request->user()?->can('panel.shop.ledger.index');

        return Inertia::render('panel/members/show', [
            'ledger' => $canLedger ? $ledger->forAccount($account, $request->query('ledger_from'), $request->query('ledger_to'), admin: true) : null,
            'collectionTypes' => $canLedger ? CollectionType::options() : [],
            'termOptions' => CreditTerms::options(),
            'customersEnabled' => CustomerType::customersEnabled(),
            'user' => MemberResource::make($user->load('account.dealerGroup', 'account.warehouse', 'account.address.country', 'account.address.city', 'account.address.district')),
            'overview' => $service->overview($user),
            'billing' => $this->billing($user),
            'activities' => ActivityResource::collection($service->activities($user)),
            'sessions' => MemberSessionResource::collection($service->authentications($user)),
            'dealerGroups' => $this->dealerGroupOptions(),
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function verifyEmail(Request $request, User $user, MemberService $service): RedirectResponse
    {
        $this->ensureMember($user);

        /** @var User $causer */
        $causer = $request->user();

        $service->verifyEmail($user, $causer);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('E-posta adresi onaylandı.'),
        ]);
    }

    public function requestEmailChange(ChangeMemberEmailRequest $request, User $user, MemberService $service): RedirectResponse
    {
        $this->ensureMember($user);

        /** @var User $causer */
        $causer = $request->user();

        $service->requestEmailChange($user, $causer, (string) $request->validated('email'));

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('E-posta değişikliği onay bağlantısı gönderildi.'),
        ]);
    }

    public function confirmEmailChange(Request $request, User $user, MemberService $service): RedirectResponse
    {
        $this->ensureMember($user);

        $email = $request->string('email')->toString();

        if ($email === '' || User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('login')->with('toast', [
                'type' => 'error',
                'message' => __('E-posta değişikliği bağlantısı artık geçerli değil.'),
            ]);
        }

        $service->confirmEmailChange($user, $email);

        return redirect()->route('login')->with('toast', [
            'type' => 'success',
            'message' => __('E-posta adresiniz değiştirildi. Yeni adresinizle giriş yapabilirsiniz.'),
        ]);
    }

    public function acceptWelcome(Request $request, User $user, MemberService $service): RedirectResponse
    {
        $this->ensureMember($user);

        $email = $request->string('email')->toString();
        $token = $request->string('token')->toString();

        if ($email === '' || $token === '' || $email !== $user->email) {
            return redirect()->route('login')->with('toast', [
                'type' => 'error',
                'message' => __('Hoş geldin bağlantısı artık geçerli değil.'),
            ]);
        }

        $service->acceptWelcome($user);

        return redirect()->route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function updateStatus(UpdateMemberStatusRequest $request, User $user, MemberService $service): RedirectResponse
    {
        $this->ensureMember($user);

        /** @var User $causer */
        $causer = $request->user();

        $service->updateStatus(
            $user,
            $causer,
            UserStatus::from((string) $request->validated('status')),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Üye durumu güncellendi.'),
        ]);
    }

    /**
     * Hesabın fatura bilgileri (üyenin "Hesabım" ekranında girdiği).
     *
     * @return array<string, mixed>|null
     */
    private function billing(User $user): ?array
    {
        $account = $user->account;

        if ($account === null) {
            return null;
        }

        $address = $account->address;

        return [
            'type' => $account->billing_type?->value ?? BillingType::Individual->value,
            'type_label' => ($account->billing_type ?? BillingType::Individual)->label(),
            'title' => $account->title,
            'tax_office' => $account->tax_office,
            'tax_number' => $account->tax_number,
            'identity_number' => $account->identity_number,
            'invoice_email' => $account->invoice_email,
            'address' => $address?->address,
            'postal_code' => $address?->postal_code,
            'district' => $address?->district?->name,
            'city' => $address?->city?->name,
            'country' => $address?->country?->name,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (UserStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], UserStatus::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function dealerGroupOptions(): array
    {
        return DealerGroup::query()->orderBy('name')->get()
            ->map(fn (DealerGroup $g) => ['value' => $g->id, 'label' => $g->name.' (%'.Money::decimal($g->discount_rate).')'])
            ->all();
    }

    /**
     * Bayinin bölge deposu seçenekleri: aktif, satılabilir depolar.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function warehouseOptions(): array
    {
        return Warehouse::query()->active()->where('is_sellable', true)->ordered()->get(['id', 'name', 'code'])
            ->map(fn (Warehouse $w) => ['value' => $w->id, 'label' => __(':name (:code)', ['name' => $w->name, 'code' => $w->code])])
            ->all();
    }

    public function resetTwoFactor(Request $request, User $user, TwoFactorResetService $service): RedirectResponse
    {
        $this->ensureMember($user);

        /** @var User $causer */
        $causer = $request->user();

        $service->reset($user, $causer);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('İki adımlı doğrulama sıfırlandı. Kullanıcı şifresiyle girip yeniden kurabilir.'),
        ]);
    }

    private function ensureMember(User $user): void
    {
        abort_unless($user->user_type === UserType::Member, 404);
    }
}
