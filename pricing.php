<?php
/* ===== JobHive Company Promotions ===== */
const JH_BASE_FEE = 50000; // MMK per post

// Change your thresholds here if needed:
const JH_TIER_1_MIN = 5;   // >=5 posts -> 10%
const JH_TIER_2_MIN = 15;  // >=15 posts -> 15%
const JH_TIER_3_MIN = 25;  // >=25 posts -> 20%

/** Returns [discountRateFloat (e.g., 0.10), memberString] from a total post count */
function jh_company_discount_for_posts(int $totalPosts): array
{
    if ($totalPosts >= JH_TIER_3_MIN) return [0.20, 'diamond'];
    if ($totalPosts >= JH_TIER_2_MIN) return [0.15, 'platinum'];
    if ($totalPosts >= JH_TIER_1_MIN) return [0.10, 'gold'];
    return [0.00, 'normal'];
}

/** Price after discount (rounded) */
function jh_price_after_discount(int $base, float $rate): int
{
    return (int) round($base * (1 - $rate), 0);
}
