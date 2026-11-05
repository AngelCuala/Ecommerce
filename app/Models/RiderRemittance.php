<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** COD cash a rider hands to their logistics company; only the company can confirm it. */
class RiderRemittance extends Model
{
    protected $fillable = ['rider_id', 'sorting_center_id', 'amount', 'pay_kept', 'expected_amount', 'status', 'request_token', 'confirmed_by', 'confirmed_at'];

    protected $casts = ['amount' => 'decimal:2', 'pay_kept' => 'decimal:2', 'expected_amount' => 'decimal:2', 'confirmed_at' => 'datetime'];

    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function confirmer(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }

    /** Receipt number, e.g. RR-261026-000001: the remittance's date (Philippine time) and its unique ID. */
    public function receiptNumber(): string
    {
        return 'RR-' . $this->created_at->copy()->tz(config('app.business_timezone'))->format('ymd') . '-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * The COD deliveries this remittance covers: collected after the rider's previous remittance, up to this one.
     * (Any balance left from before shows on the receipt as carried over, so the lines add up to expected_amount.)
     */
    public function coveredDeliveries()
    {
        $previous = self::where('rider_id', $this->rider_id)->where('id', '<', $this->id)->latest('id')->value('created_at');

        return ParcelDelivery::where('rider_id', $this->rider_id)->where('status', 'delivered')->where('cod_collected', '>', 0)
            ->where('delivered_at', '<=', $this->created_at)
            ->when($previous, fn ($q) => $q->where('delivered_at', '>', $previous))
            ->with('parcel.order')->orderBy('delivered_at')->get();
    }

    /** Less than the COD balance at the time, counting the pay the rider kept out of it. */
    public function shortfall(): float
    {
        return round(max(0, (float) $this->expected_amount - (float) $this->amount - (float) $this->pay_kept), 2);
    }
}
