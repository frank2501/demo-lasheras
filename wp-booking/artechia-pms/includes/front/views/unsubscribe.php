<?php
/**
 * Unsubscribe confirmation page.
 * Variables: $success (bool), $message (string)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name = get_bloginfo( 'name' );
$home      = esc_url( home_url( '/' ) );
$icon      = $success ? '✅' : '❌';
$title     = $success ? 'Baja confirmada' : 'Error';
$cls       = $success ? 'success' : 'error';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $title ); ?> — <?php echo esc_html( $site_name ); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f4f8;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}
        .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:480px;width:100%;padding:3rem 2rem;text-align:center}
        .icon{font-size:3rem;margin-bottom:1rem}
        h1{font-size:1.5rem;margin-bottom:.75rem}
        p{color:#64748b;line-height:1.6;margin-bottom:1.5rem}
        .btn{display:inline-block;padding:.75rem 2rem;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;transition:background .2s}
        .btn:hover{background:#1d4ed8}
        .success h1{color:#16a34a}
        .error h1{color:#dc2626}
    </style>
</head>
<body>
    <div class="card <?php echo $cls; ?>">
        <div class="icon"><?php echo $icon; ?></div>
        <h1><?php echo esc_html( $title ); ?></h1>
        <p><?php echo esc_html( $message ); ?></p>
        <a href="<?php echo $home; ?>" class="btn">Volver al sitio</a>
    </div>
</body>
</html>
