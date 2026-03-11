<?php
/**
 * Booking flow stepper partial.
 * Include in search.php, results.php, checkout.php, confirmation.php
 * Set $artechia_step = 1|2|3|4 before including.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$steps = [
    1 => 'Fechas',
    2 => 'Habitaciones',
    3 => 'Datos',
    4 => 'Reservar',
];
$current = isset( $artechia_step ) ? (int) $artechia_step : 1;
?>

<nav class="artechia-stepper" aria-label="Progreso de reserva">
    <ol class="artechia-stepper__list">
        <?php foreach ( $steps as $num => $label ) :
            $state = $num < $current ? 'completed' : ( $num === $current ? 'active' : '' );
        ?>
        <li class="artechia-stepper__item <?php echo $state ? 'artechia-stepper__item--' . $state : ''; ?>">
            <span class="artechia-stepper__dot"><?php echo $num < $current ? '✓' : $num; ?></span>
            <span class="artechia-stepper__label"><?php echo esc_html( $label ); ?></span>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
