<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Interpretation;
use App\Models\LaboratoryResultParameter;
use App\Services\Laboratory\InterpretationEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The numeric flag follows the rule carried by the selected range.
 *
 * A null limit is not checked; the rule decides which of the remaining sides
 * are, for the critical limits exactly as for the reference ones.
 */
class InterpretationEvaluatorRuleTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, string, ?Interpretation}> */
    public static function cases(): iterable
    {
        $cholesterol = ['reference_high' => 200, 'abnormal_when' => 'above_high'];
        $hdl = ['reference_low' => 40, 'abnormal_when' => 'below_low'];

        yield 'CHOL high-only: above flags H' => [$cholesterol, '250', Interpretation::High];
        yield 'CHOL high-only: a low value is not checked' => [$cholesterol, '50', Interpretation::Normal];
        yield 'HDL low-only: below flags L' => [$hdl, '30', Interpretation::Low];
        yield 'HDL low-only: a high value is not checked' => [$hdl, '90', Interpretation::Normal];

        $both = ['reference_low' => 3.5, 'reference_high' => 5.1, 'critical_low' => 2.5, 'critical_high' => 6.5];

        yield 'outside_range checks the low side' => [[...$both, 'abnormal_when' => 'outside_range'], '3.0', Interpretation::Low];
        yield 'outside_range checks the high side' => [[...$both, 'abnormal_when' => 'outside_range'], '5.5', Interpretation::High];
        yield 'outside_range raises LL' => [[...$both, 'abnormal_when' => 'outside_range'], '2.0', Interpretation::CriticalLow];
        yield 'outside_range raises HH' => [[...$both, 'abnormal_when' => 'outside_range'], '7.0', Interpretation::CriticalHigh];
        yield 'no rule behaves as outside_range' => [$both, '7.0', Interpretation::CriticalHigh];

        yield 'above_high never raises LL' => [[...$both, 'abnormal_when' => 'above_high'], '2.0', Interpretation::Normal];
        yield 'above_high still raises HH' => [[...$both, 'abnormal_when' => 'above_high'], '7.0', Interpretation::CriticalHigh];
        yield 'below_low never raises HH' => [[...$both, 'abnormal_when' => 'below_low'], '7.0', Interpretation::Normal];
        yield 'below_low still raises LL' => [[...$both, 'abnormal_when' => 'below_low'], '2.0', Interpretation::CriticalLow];

        yield 'never gives no flag at all' => [[...$both, 'abnormal_when' => 'never'], '99', null];
        yield 'no limits gives no flag' => [['abnormal_when' => 'outside_range'], '5', null];
    }

    /** @param array<string, mixed> $range */
    #[Test]
    #[DataProvider('cases')]
    public function the_rule_decides_which_sides_are_flagged(array $range, string $value, ?Interpretation $expected): void
    {
        $evaluator = new InterpretationEvaluator;

        $row = new LaboratoryResultParameter([
            'parameter_name' => 'Test',
            'parameter_code' => 'TEST',
            'data_type' => 'numeric',
            ...$range,
            'result_value' => $value,
        ]);
        $row->result_numeric = $evaluator->toNumeric($value);

        $this->assertSame($expected, $evaluator->suggest($row));
    }
}
