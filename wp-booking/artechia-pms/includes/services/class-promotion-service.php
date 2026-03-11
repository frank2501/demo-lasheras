<?php
namespace Artechia\PMS\Services;

use Artechia\PMS\Repositories\PromotionRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PromotionService {

    public function get_best_promotion( int $property_id, int $room_type_id, string $check_in, string $check_out, int $nights ): ?array {
        $repo = new PromotionRepository();
        $promos = $repo->find_active_for_search( $property_id, $check_in, $check_out );
        
        $best_promo = null;
        $max_discount = -1;

        foreach ( $promos as $promo ) {
            // Check room type restriction
            if ( ! empty( $promo['room_type_ids'] ) ) {
                $allowed_ids = json_decode( $promo['room_type_ids'], true );
                if ( is_array( $allowed_ids ) && ! in_array( $room_type_id, $allowed_ids ) ) {
                    continue;
                }
            }

            // Check min nights
            if ( $nights < (int) $promo['min_nights'] ) {
                continue;
            }

            // For now, we just pick the first applicable one or could implement logic to find the "best"
            // Let's assume the first one found that satisfies conditions is a good start, 
            // but we can refine "best" based on estimated discount later.
            return $promo;
        }

        return null;
    }

    public function apply_promotion_logic( array $promo, float $daily_rate, int $nights ): array {
        $discount_total = 0;
        $description = $promo['name'];

        switch ( $promo['rule_type'] ) {
            case 'percent':
                $pct = (float) $promo['rule_value'];
                $discount_total = ( $daily_rate * $nights ) * ( $pct / 100 );
                break;

            case 'fixed':
                $discount_total = (float) $promo['rule_value'];
                break;

            case 'stay_pay':
                // e.g. '3/2' -> Stay 3, Pay 2
                $parts = explode( '/', $promo['rule_value'] );
                if ( count( $parts ) === 2 ) {
                    $stay = (int) $parts[0];
                    $pay  = (int) $parts[1];
                    
                    if ( $nights >= $stay ) {
                        $free_nights = $stay - $pay;
                        // Simplistic: free nights are at the daily rate
                        $discount_total = $free_nights * $daily_rate;
                        $description = sprintf( 'Promoción: Quédate %d, paga %d', $stay, $pay );
                    }
                }
                break;
        }

        return [
            'discount_amount' => $discount_total,
            'description'     => $description,
            'promo_id'        => $promo['id']
        ];
    }
}
