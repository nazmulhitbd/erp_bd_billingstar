<?php
function bus_route_fare_quote($distance_km, $base_fare_bdt, $reference_fuel_price_bdt, $current_fuel_price_bdt, $fuel_l_per_100km, $allocation_seats)
{
    $distance_km = (float) $distance_km;
    $base_fare_bdt = (float) $base_fare_bdt;
    $reference_fuel_price_bdt = (float) $reference_fuel_price_bdt;
    $current_fuel_price_bdt = (float) $current_fuel_price_bdt;
    $fuel_l_per_100km = (float) $fuel_l_per_100km;
    $allocation_seats = (int) $allocation_seats;

    if ($distance_km <= 0 || $base_fare_bdt <= 0 || $reference_fuel_price_bdt <= 0
            || $current_fuel_price_bdt <= 0 || $fuel_l_per_100km <= 0 || $allocation_seats <= 0) {
                return false;
            }

    $estimated_fuel_litres = $distance_km * $fuel_l_per_100km / 100;
    $fuel_price_increase = max(0, $current_fuel_price_bdt - $reference_fuel_price_bdt);
    $fuel_adjustment_bdt = round(($estimated_fuel_litres * $fuel_price_increase) / $allocation_seats, 2);
    $suggested_fare_bdt = round($base_fare_bdt + $fuel_adjustment_bdt, 2);

    return array(
        'estimated_fuel_litres' => round($estimated_fuel_litres, 3),
        'fuel_price_increase_bdt' => round($fuel_price_increase, 2),
        'fuel_surcharge_bdt' => $fuel_adjustment_bdt,
        'suggested_fare_bdt' => $suggested_fare_bdt,
        'suggested_fare_per_km_bdt' => round($suggested_fare_bdt / $distance_km, 4)
    );
}