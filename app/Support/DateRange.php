<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The period picker on seller pages: Today, This week, This month, or a custom From–To range.
 * Built from ?period=today|week|month|custom&from=Y-m-d&to=Y-m-d, validated on the server.
 */
final class DateRange
{
    public const PERIODS = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'custom' => 'Custom'];

    private function __construct(
        public readonly string $period,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request, string $default = 'month'): self
    {
        $request->validate([
            'period' => 'nullable|in:' . implode(',', array_keys(self::PERIODS)),
            'from'   => 'nullable|required_if:period,custom|date_format:Y-m-d',
            'to'     => 'nullable|required_if:period,custom|date_format:Y-m-d|after_or_equal:from',
        ], [
            'from.required_if'  => 'Choose a start date for the custom range.',
            'to.required_if'    => 'Choose an end date for the custom range.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $period = $request->input('period', $default);
        $now = CarbonImmutable::now();

        return match ($period) {
            'today'  => new self('today', $now->startOfDay(), $now->endOfDay()),
            'week'   => new self('week', $now->startOfWeek(), $now->endOfDay()),
            'custom' => self::custom($request->input('from'), $request->input('to')),
            default  => new self('month', $now->startOfMonth(), $now->endOfDay()),
        };
    }

    private static function custom(string $from, string $to): self
    {
        $f = CarbonImmutable::createFromFormat('Y-m-d', $from)->startOfDay();
        $t = CarbonImmutable::createFromFormat('Y-m-d', $to)->endOfDay();
        if ($f->diffInDays($t) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a range of one year or less.']);
        }
        return new self('custom', $f, $t);
    }

    /** For whereBetween(). */
    public function bounds(): array
    {
        return [$this->from->toDateTimeString(), $this->to->toDateTimeString()];
    }

    public function label(): string
    {
        return match ($this->period) {
            'today' => 'Today, ' . $this->from->format('M j'),
            'week'  => 'This week (' . $this->from->format('M j') . ' – ' . $this->to->format('M j') . ')',
            'month' => $this->from->format('F Y'),
            default => $this->from->format('M j, Y') . ' – ' . $this->to->format('M j, Y'),
        };
    }

    /** Query string that reproduces this range (for links and pagination). */
    public function query(): array
    {
        return $this->period === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->period];
    }
}
