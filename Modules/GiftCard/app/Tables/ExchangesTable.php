<?php

namespace Modules\GiftCard\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Http\Requests\GiftCardTableRequest;
use Modules\GiftCard\Models\GiftCardExchange;
use Yajra\DataTables\DataTables;

class ExchangesTable
{
    private const ORDERABLE = ['id', 'points'];

    private const COLUMNS = ['date', 'customer', 'card', 'points', 'code', 'expires', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(GiftCardTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $query = GiftCardExchange::query()->select('gift_card_exchanges.*')
            ->with(['customer:id,name,email,phone', 'giftCard:id,name'])
            ->when($request->validated('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($request->validated('gift_card_id'), fn (Builder $q, $id) => $q->where('gift_card_id', $id));

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('code', 'like', "{$search}%")
                ->orWhereIn('customer_id', Customer::search($search)->select('id')))))
            ->whitelist(self::ORDERABLE)
            ->addColumn('date', fn (GiftCardExchange $x) => $x->created_at->format(setting('date_format').' H:i'))
            ->addColumn('customer', fn (GiftCardExchange $x) => view('loyalty::points.cells.member', ['customer' => $x->customer])->render())
            ->addColumn('card', fn (GiftCardExchange $x) => e($x->giftCard->name).'<div class="small text-muted">'.e(money($x->face_value, 2)).'</div>')
            ->editColumn('points', fn (GiftCardExchange $x) => number_format($x->points))
            ->editColumn('code', fn (GiftCardExchange $x) => $x->code ?? '—')
            ->addColumn('expires', fn (GiftCardExchange $x) => $x->expires_at?->format(setting('date_format')) ?? '—')
            ->addColumn('status', fn (GiftCardExchange $x) => view('giftcard::cells.exchange-status', ['exchange' => $x])->render())
            ->addColumn('actions', fn (GiftCardExchange $x) => view('giftcard::cells.exchange-actions', ['exchange' => $x])->render())
            ->rawColumns(['customer', 'card', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
