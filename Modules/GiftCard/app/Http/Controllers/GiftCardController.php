<?php

namespace Modules\GiftCard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\GiftCard\Http\Requests\GiftCardRequest;
use Modules\GiftCard\Http\Requests\GiftCardTableRequest;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Tables\GiftCardsTable;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Merchant\Models\Merchant;

class GiftCardController extends Controller
{
    public function index(): View
    {
        return view('giftcard::cards.index');
    }

    public function data(GiftCardTableRequest $request, GiftCardsTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function create(Request $request): View
    {
        return view('giftcard::cards.form', ['card' => new GiftCard(['is_active' => true, 'merchant_id' => $request->integer('merchant_id') ?: null]),
            'tiers' => TierLevel::cases(), 'merchants' => $this->merchants()]);
    }

    public function store(GiftCardRequest $request): RedirectResponse
    {
        $card = GiftCard::query()->create($request->validated());

        return redirect()->route('admin.gift-cards.index')->with('success', __('Gift card :name created.', ['name' => $card->name]));
    }

    public function edit(GiftCard $giftCard): View
    {
        return view('giftcard::cards.form', ['card' => $giftCard, 'tiers' => TierLevel::cases(), 'merchants' => $this->merchants()]);
    }

    public function update(GiftCardRequest $request, GiftCard $giftCard): RedirectResponse
    {
        $giftCard->update($request->validated());

        return redirect()->route('admin.gift-cards.index')->with('success', __('Gift card :name saved.', ['name' => $giftCard->name]));
    }

    /** Cards already exchanged stay (customers hold their codes): deactivate those instead. */
    public function destroy(GiftCard $giftCard): RedirectResponse
    {
        if ($giftCard->exchanges()->exists()) {
            return back()->with('error', __(':name has exchanges, so it can only be deactivated.', ['name' => $giftCard->name]));
        }
        $giftCard->delete();

        return redirect()->route('admin.gift-cards.index')->with('success', __('Gift card :name deleted.', ['name' => $giftCard->name]));
    }

    /** @return Collection<int, string> */
    private function merchants(): Collection
    {
        return Merchant::query()->orderBy('name')->pluck('name', 'id');
    }
}
