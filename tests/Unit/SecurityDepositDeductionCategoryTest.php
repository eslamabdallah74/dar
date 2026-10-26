<?php

use App\Enums\SecurityDepositDeductionCategory;

test('only unpaid rent and unpaid dues deductions offset rent arrears', function (SecurityDepositDeductionCategory $category, bool $offsetsRentArrears) {
    expect($category->settlesRentArrears())->toBe($offsetsRentArrears);
})->with([
    'unpaid rent' => [SecurityDepositDeductionCategory::UnpaidRent, true],
    'unpaid dues' => [SecurityDepositDeductionCategory::UnpaidDues, true],
    'unit damage' => [SecurityDepositDeductionCategory::UnitDamage, false],
    'maintenance' => [SecurityDepositDeductionCategory::Maintenance, false],
    'cleaning' => [SecurityDepositDeductionCategory::Cleaning, false],
    'other' => [SecurityDepositDeductionCategory::Other, false],
    'legacy furniture damage' => [SecurityDepositDeductionCategory::FurnitureDamage, false],
    'legacy fixture damage' => [SecurityDepositDeductionCategory::FixtureDamage, false],
    'legacy missing items' => [SecurityDepositDeductionCategory::MissingItems, false],
    'legacy other damage' => [SecurityDepositDeductionCategory::OtherDamage, false],
]);
